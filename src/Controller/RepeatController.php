<?php

namespace Drupal\openy_repeat\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\openy_repeat\OpenyRepeatRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * {@inheritdoc}
 */
class RepeatController extends ControllerBase {

  const SCHEDULER_DAYS_LIMIT = 7;

  /**
   * Cache default.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Open Y Repeat Repository.
   *
   * @var OpenyRepeatRepository
   */
  protected $repository;

  /**
   * Creates a new RepeatController.
   *
   * @param CacheBackendInterface $cache
   *   Cache default.
   * @param Connection $database
   *   The Database connection.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager.
   * @param DateFormatterInterface $date_formatter
   *   The Date formatter.
   * @param OpenyRepeatRepository $repository
   *    OpenyRepeatRepository.
   */
  public function __construct(CacheBackendInterface $cache, Connection $database, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, OpenyRepeatRepository $repository) {
    $this->cache = $cache;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->repository = $repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('openy_repeat.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxScheduler(Request $request, $location, $date, $category) {
    $category = str_replace('U+002F', '/', $category);
    $result = $this->getData($request, $location, $date, $category);
    return new JsonResponse($result);
  }

  /**
   * Get schedules by session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string $session
   *   Session.
   * @param string $location
   *   Location.
   * @param string $date
   *   Date.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function ajaxSchedulerBySession(Request $request, $session, $location, $date) {
    $event = $this->entityTypeManager
      ->getStorage('repeat')
      ->loadByProperties(['session' => $session]);

    $event = array_shift($event);
    $class = $event->class->referencedEntities();
    $class = array_shift($class);
    return $this->ajaxSchedulerByClass($request, $class->id(), $location, $date);
  }

  /**
   * Get schedules by instructor.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string $instructor
   *   Instructor.
   * @param string $location
   *   Location.
   * @param $date
   *   String date.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function ajaxSchedulerByInstructor(Request $request, $instructor, $location, $date) {
    $result = [];
    $date = new \DateTime('now');
    $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $date->setTime(0, 0, 0);
    foreach (range(0, self::SCHEDULER_DAYS_LIMIT) as $index) {
      $date_string = $date->format('Y-m-d');
      $data = $this->getData($request, $location, $date_string, $category = 0, $instructor);
      foreach ($data as $schedule) {
        $schedule->short_date = $date->format('M j');
        $schedule->day_name = $date->format('l');
        $result[] = $schedule;
      }
      $date->modify('+1 day');
    }
    return new JsonResponse($result);
  }

  /**
   * Get schedules by class.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string $class
   *   Class.
   * @param string $location
   *   Location.
   * @param string $date
   *   date.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Exception
   */
  public function ajaxSchedulerByClass(Request $request, $class, $location, $date) {
    $result = [];
    $date = new \DateTime('now');
    $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $date->setTime(0, 0, 0);
    foreach (range(0, self::SCHEDULER_DAYS_LIMIT) as $index) {
      $date_string = $date->format('Y-m-d');
      $data = $this->getData($request, $location, $date_string, $category = 0, '', $class);
      foreach ($data as $schedule) {
        $schedule->short_date = $date->format('M j');
        $schedule->day_name = $date->format('l');
        $result[] = $schedule;
      }
      $date->modify('+1 day');
    }
    return new JsonResponse($result);
  }

  /**
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function ajaxSchedulerHasWeekResults(Request $request, $location, $date, $category) {

    $result = count(array_filter($this->getPdfWeekResults($request, $location, $date, $category))) > 0;

    return new JsonResponse($result);
  }

  /**
   * Gets events data for given location, date, category, instructor or class.
   *
   * @param Request $request
   * @param string $location
   * @param string $date
   * @param string $category
   * @param string $instructor
   * @param string $class
   *
   * @return array
   */
  public function getData($request, $location, $date, $category, $instructor = '', $class = '') {
    $initDate = new \DateTime('now');
    $initDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $initDate->setTime(0, 0, 0);
    if (!empty($date)) {
      $initDate = \DateTime::createFromFormat('Y-m-d', $date);
      $initDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
      $initDate->setTime(0, 0, 0);
    }

    $year = $initDate->format('Y');
    $month = $initDate->format('m');
    $day = $initDate->format('d');
    $week = $initDate->format('W');
    $weekday = $initDate->format('N');

    $timestamp_start = $initDate->getTimestamp();
    // Next day.
    $nextDay = clone $initDate;
    $nextDay->modify('+1 day');
    $timestamp_end = $nextDay->getTimestamp();

    $query = $this->database->select('repeat_event', 're');
    $query->leftJoin('node', 'n', 're.session = n.nid');
    $query->innerJoin('node_field_data', 'nd', 're.location = nd.nid');
    $query->innerJoin('node_field_data', 'nds', 'n.nid = nds.nid');
    $query->addField('n', 'nid');
    $query->addField('nd', 'title', 'location');
    $query->addField('nds', 'title', 'name');
    $query->fields('re', [
      'class',
      'session',
      'room',
      'instructor',
      'category',
      'register_url',
      'register_text',
      'duration',
      'productid',
    ]);
    $query->addField('re', 'start', 'start_timestamp');
    $query->addField('re', 'end', 'end_timestamp');
    // Query conditions.
    $query->distinct();
    $year_condition_group = $query->orConditionGroup()
      ->condition('re.year', $year)
      ->condition('re.year', '*');
    $month_condition_group = $query->orConditionGroup()
      ->condition('re.month', $month)
      ->condition('re.month', '*');
    $day_condition_group = $query->orConditionGroup()
      ->condition('re.day', $day)
      ->condition('re.day', '*');
    $week_condition_group = $query->orConditionGroup()
      ->condition('re.week', $week)
      ->condition('re.week', '*');
    $weekday_condition_group = $query->orConditionGroup()
      ->condition('re.weekday', $weekday)
      ->condition('re.weekday', '*');
    $query->condition('n.type', 'session');
    $query->condition($year_condition_group);
    $query->condition($month_condition_group);
    $query->condition($day_condition_group);
    $query->condition($week_condition_group);
    $query->condition($weekday_condition_group);
    $query->condition('re.start', $timestamp_end, '<=');
    $query->condition('re.end', $timestamp_start, '>=');
    if (!empty($category)) {
      $query->condition('re.category', explode(';', $category), 'IN');
    }
    if (!empty($location)) {
      $query->condition('nd.title', explode(';', rawurldecode($location)), 'IN');
    }
    $exclusions = $request->get('excl');
    if (!empty($exclusions)) {
      $query->condition('re.category', explode(';', $exclusions), 'NOT IN');
    }
    $limit = $request->get('limit');
    if (!empty($limit)) {
      $query->condition('re.category', explode(';', $limit), 'IN');
    }
    if (!empty($instructor)) {
      $query->condition('re.instructor', $instructor);
    }
    if (!empty($class)) {
      $query->condition('re.class', $class);
    }
    $query->addTag('openy_repeat_get_data');

    $result = $query->execute()->fetchAll();

    $locations_info = $this->getLocationsInfo();

    $classesIds = [];
    foreach ($result as $key => $item) {
      $classesIds[$item->class] = $item->class;
    }
    $classes_info = $this->getClassesInfo($classesIds);

    $class_name = [];
    foreach ($result as $key => $item) {
      $result[$key]->location_info = $locations_info[$item->location];

      if (isset($classes_info[$item->class]['path'])) {
        $query = UrlHelper::buildQuery([
          'location' => $locations_info[$item->location]['nid'],
        ]);
        if (!in_array($item->name, $class_name)) {
          $classes_info[$item->class]['path'] .= '?' . $query;
          $class_name[] = html_entity_decode($item->name);
        }
      }

      $result[$key]->class_info = $classes_info[$item->class];

      $result[$key]->time_start_sort = $this->dateFormatter->format((int)$item->start_timestamp, 'custom', 'Hi');

      // Convert timezones for start_time and end_time.
      $time_start = new \DateTime();
      $time_start->setTimestamp($item->start_timestamp);
      $time_start->setTimezone(new \DateTimeZone(date_default_timezone_get()));
      $time_end = new \DateTime();
      $time_end->setTimestamp($item->end_timestamp);
      $time_end->setTimezone(new \DateTimeZone(date_default_timezone_get()));
      $result[$key]->time_start = $time_start->format('g:iA');
      $result[$key]->time_end = $time_end->format('g:iA');

      // Example of calendar format 2018-08-21 14:15:00.
      $result[$key]->time_start_calendar = $this->dateFormatter->format((int)$item->start_timestamp, 'custom', 'Y-m-d H:i:s');
      $result[$key]->time_end_calendar = $this->dateFormatter->format((int)$item->start_timestamp + $item->duration * 60, 'custom', 'Y-m-d H:i:s');
      $result[$key]->timezone = date_default_timezone_get();

      // Durations.
      $result[$key]->duration_minutes = $item->duration % 60;
      $result[$key]->duration_hours = ($item->duration - $result[$key]->duration_minutes) / 60;
    }

    usort($result, function($item1, $item2){
      if ((int) $item1->time_start_sort == (int) $item2->time_start_sort) {
        return 0;
      }
      return (int) $item1->time_start_sort < (int) $item2->time_start_sort ? -1 : 1;
    });

    $this->moduleHandler()->alter('openy_repeat_results', $result, $request, $timestamp_start);

    return $result;
  }

  /**
   * Return Location from "Session" node type.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLocations() {
    return $this->repository->getLocations();
  }

  /**
   * Get detailed info about Location (aka branch), camps, facilities.
   */
  public function getLocationsInfo() {
    $data = [];
    $tags = ['node_list'];
    $cid = 'openy_repeat:locations_info';
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      $nids = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', ['branch', 'location', 'camp', 'facility'], 'IN')
        ->execute();
      $nids_chunked = array_chunk($nids, 20, TRUE);
      foreach ($nids_chunked as $chunk) {
        $branches = $this->entityTypeManager->getStorage('node')->loadMultiple($chunk);
        if (!empty($branches)) {
          /** @var Node $node */
          foreach ($branches as $node) {
            $days = $node->hasField('field_branch_hours') ?
              $node->get('field_branch_hours')->getValue() : [];
            $address = $node->get('field_location_address')->getValue();
            if (!empty($address[0])) {
              $address = array_filter($address[0]);
              $address_order = [
                'address_line1' => '',
                'locality' => '',
                'administrative_area' => '',
                'country_code' => '',
                'postal_code' => '',
              ];
              $diff_address = array_diff_key($address, $address_order);
              $address = "{$address['address_line1']}, {$address['locality']}, {$address['administrative_area']}, {$address['country_code']}, {$address['postal_code']}";
              $address = !empty($diff_address) ? $address . ', ' . implode(', ', $diff_address) : $address;
            }
            $data[$node->title->value] = [
              'nid' => $node->nid->value,
              'title' => $node->title->value,
              'email' => $node->field_location_email->value,
              'phone' => $node->field_location_phone->value,
              'address' => $address,
              'days' => !empty($days[0]) ? $this->getFormattedHours($days[0]) : [],
            ];
            $tags[] = 'node:' . $node->nid->value;
          }
        }
      }

      $this->moduleHandler()->alter('openy_repeat_locations_info', $data);

      $this->cache->set($cid, $data, CacheBackendInterface::CACHE_PERMANENT, $tags);
    }

    return $data;
  }

  /**
   * Get detailed info about Class.
   */
  public function getClassesInfo($nids) {
    $data = [];
    $tags = [];
    $cid = 'openy_repeat:classes_info' . md5(json_encode($nids));
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      $nids_chunked = array_chunk($nids, 20, TRUE);
      foreach ($nids_chunked as $chunk) {
        $classes = $this->entityTypeManager->getStorage('node')->loadMultiple($chunk);
        if (!empty($classes)) {
          /** @var Node $node */
          foreach ($classes as $node) {
            $data[$node->nid->value] = [
              'nid' => $node->nid->value,
              'path' => $node->toUrl()->setAbsolute()->toString(),
              'title' => $node->title->value,
              'description' => html_entity_decode(strip_tags(text_summary($node->field_class_description->value, $node->field_class_description->format, 600))),
            ];
            $tags[] = 'node:' . $node->nid->value;
          }
        }
      }
      $this->cache->set($cid, $data, CacheBackendInterface::CACHE_PERMANENT, $tags);
    }

    return $data;
  }


  public function getFormattedHours($data) {
    $lazy_hours = $groups = $rows = [];
    foreach ($data as $day => $value) {
      // Do not process label. Store it name for later usage.
      if ($day == 'hours_label') {
        continue;
      }

      $day = str_replace('hours_', '', $day);
      $value = $value ? $value : 'closed';
      $lazy_hours[$day] = $value;
      if ($groups && end($groups)['value'] == $value) {
        $array_keys = array_keys($groups);
        $group = &$groups[end($array_keys)];
        $group['days'][] = $day;
      }
      else {
        $groups[] = [
          'value' => $value,
          'days' => [$day],
        ];
      }
    }

    foreach ($groups as $group_item) {
      $title = sprintf('%s - %s', ucfirst(reset($group_item['days'])), ucfirst(end($group_item['days'])));
      if (count($group_item['days']) == 1) {
        $title = ucfirst(reset($group_item['days']));
      }
      $hours = $group_item['value'];
      $rows[] = [$title . ':', $hours];
    }

    return $rows;
  }

  /**
   * Returns PDF for specific parameters.
   */
  public function getPdf(Request $request) {
    $content = $this->getPdfContent($request);
    $settings = [
      'body' => [
        '#content' => [
          'logo_url' => \Drupal::service('extension.list.module')->getPath('openy_repeat') . '/img/ymca_logo_black.png',
          'result' => $content['content']['content'],
          'header' => $content['content']['header'],
        ],
        '#theme' => $content['theme'],
        '#cache' => [
          'max-age' => 0
        ],
      ],
      'title' => $this->t('Download PDF schedule'),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    \Drupal::service('openy_repeat_pdf_generator')->generatePDF($settings);
  }

  /**
   * Returns content for a PDF.
   *
   * @param /Symfony/Component/HttpFoundation/Request $request
   *   Request service.
   *
   * @return array
   */
  public function getPdfContent($request) {
    $rooms = !empty($parameters['rooms']) ? $parameters['rooms'] : '';
    $classnames = !empty($parameters['cn']) ? $parameters['cn'] : [];
    $mode = !empty($parameters['mode']) ? $parameters['mode'] : 'activity';
    $hideInstructor = !empty($parameters['hide-instructor']) ? $parameters['hide-instructor'] : 0;
    $hidePrograms = !empty($parameters['hide-programs']) ? $parameters['hide-programs'] : 0;

    $result = $this->getPdfWeekResults($request);
    if (!empty($rooms)) {
      $rooms = explode(';', $rooms);
    }
    // Group by activity.
    if ($mode == 'activity') {
      $result = $this->groupByActivity($result, $rooms, $hideInstructor, $hidePrograms, $classnames);
      $theme = 'openy_repeat__pdf__table__activity';
    }
    // Group by day.
    if ($mode == 'day') {
      $result = $this->groupByDay($result, $rooms, $classnames);
      $theme = 'openy_repeat__pdf__table__day';
    }

    $content = [
      'content' => $result,
      'theme' => $theme,
    ];

    return $content;
  }

  /**
   * Group results by Activity & Location.
   */
  public function groupByActivity($result, $rooms, $hideInstructor, $hidePrograms, $classnames = []) {
    if (empty($result)) {
      return FALSE;
    }
    $date_keys = $formatted_result = [];
    if (!is_array($classnames) && $classnames) {
      $classnames = [$classnames];
    }

    // Create weekdays array.
    foreach ($result as $day => $data) {
      $date_keys[$day] = [];
    }
    $arr_date_keys = array_keys($date_keys);
    $first = reset($arr_date_keys);
    $last = end($arr_date_keys);
    $first = DrupalDateTime::createFromFormat('Y-m-d', $first)->format('F jS');
    $last = DrupalDateTime::createFromFormat('Y-m-d', $last)->format('F jS');
    $formatted_result['header'] = [
      'dates' => $first . ' - ' . $last,
      'hide_instructor' => $hideInstructor,
      'hide_programs' => $hidePrograms,
    ];
    // Create activities array pass weekdays array to each.
    foreach ($result as $day => $data) {
      foreach ($data as $key => $session) {
        // Additionally filter by room.
        if (!empty($rooms) && !in_array($session->location . '||' . $session->room, $rooms)) {
          $location_found = FALSE;
          foreach ($rooms as $room) {
            // Keep locations with no selected rooms (means all are selected).
            if (strpos($room, $session->location) !== FALSE) {
              $location_found = TRUE;
            }
          }
          if ($location_found) {
            unset($result[$day][$key]);
            continue;
          }
        }
        if ($classnames && !in_array(trim($session->name), $classnames)) {
          unset($result[$day][$key]);
          continue;
        }

        $formatted_result['content'][$session->location][$session->name . $session->room] = [
          'name' => html_entity_decode($session->name),
          'room' => $session->room,
          'dates' => $date_keys,
          'url' => $session->register_url,
          'url_text' => $session->register_text,
        ];
      }
    }

    foreach ($result as $day => $data) {
      foreach ($data as $session) {
        $words = explode(' ' , $session->instructor);
        $short_name = $words[0];
        if (isset($words[1])) {
          $short_name .= ' ' . substr($words[1], 0 ,1);
        }
        $short_name = !empty($words[1]) ? $short_name . '.' : $short_name;
        $formatted_result['content'][$session->location][$session->name . $session->room]['dates'][$day][] = [
          'time' => $session->time_start . '-' . $session->time_end,
          'category' => $session->category,
          'instructor' => $short_name,
        ];
      }
    }

    return $formatted_result;
  }

  /**
   * @param $result
   *   Results array.
   * @param $rooms
   *   Rooms array.
   * @param array $classnames
   *   Classnames array
   *
   * @return array|bool
   */
  public function groupByDay($result, $rooms, $classnames = []) {

    if (empty($result)) {
      return FALSE;
    }

    $date_keys = $formatted_result = [];

    // Create weekdays array.
    foreach ($result as $day => $data) {
      $date_keys[$day] = [];
    }
    $arr_date_keys = array_keys($date_keys);
    $first = reset($arr_date_keys);
    $last = end($arr_date_keys);
    $first = DrupalDateTime::createFromFormat('Y-m-d', $first)->format('F jS');
    $last = DrupalDateTime::createFromFormat('Y-m-d', $last)->format('F jS');
    $formatted_result['header'] = [
      'dates' => $first . ' - ' . $last,
    ];

    foreach ($result as $day => $data) {
      foreach ($data as $key => $session) {
        // Additionally filter by room.
        if (!empty($rooms) && !in_array($session->location . '||' . $session->room, $rooms)) {
          $location_found = FALSE;
          foreach ($rooms as $room) {
            // Keep locations with no selected rooms (means all are selected).
            if (strpos($room, $session->location) !== FALSE) {
              $location_found = TRUE;
            }
          }
          if ($location_found) {
            unset($result[$day][$key]);
            continue;
          }
        }
        if ($classnames && !in_array(trim($session->name), $classnames)) {
          unset($result[$day][$key]);
          continue;
        }

        $weekday = DrupalDateTime::createFromFormat('Y-m-d', $day)->format('l');
        $formatted_result['content'][$session->category . '|' .$session->location][$weekday][$session->time_start . '-' . $session->time_end][] = [
          'room' => $session->room,
          'name' => html_entity_decode($session->name),
          'category' => $session->category,
          'instructor' => $session->instructor,
        ];
      }
    }
    return $formatted_result;
  }

  /**
   * Helper function to return
   *
   * @param $request
   *
   * @return array
   */
  protected function getPdfWeekResults(Request $request, $location = '0', $date = '', $category = '0'){
    // Get all parameters from query.
    $parameters = $request->query->all();
    $category = !empty($parameters['categories']) ? $parameters['categories'] : $category;

    $location = !empty($parameters['locations']) ? $parameters['locations'] : $location;
    $date = !empty($parameters['date']) ? $parameters['date'] : $date;

    if (empty($date)) {
      $date = time();
    }
    else {
      $date = strtotime($date);
    }

    // Calculate first day of a week.
    $monday_timestamp = strtotime("last Monday", $date);
    if (date('D', $date) === 'Mon') {
      $monday_timestamp = $date;
    }
    $timestamp_start = $monday_timestamp;

    $result = [];
    // Create weekly schedule by getting results for every weekday.
    for ($i = 1; $i <= 7; $i++) {
      $date = DrupalDateTime::createFromTimestamp($timestamp_start);
      $result[$date->format('Y-m-d')] = $this->getData($request, $location, $date->format('Y-m-d'), $category);
      $timestamp_start += 86400;
    }

    return $result;
  }

}
