<?php

namespace Drupal\openy_repeat\Plugin\Block;

use Drupal\block\BlockInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\openy_repeat\OpenyRepeatRepository;

/**
 * Provides a 'Repeat Schedules' block.
 *
 * @Block(
 *   id = "repeat_schedules_block",
 *   admin_label = @Translation("Repeat Schedules Block"),
 *   category = @Translation("Paragraph Blocks")
 * )
 */
class RepeatSchedulesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;

  /**
   * The database object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * OpenyRepeatRepository.
   *
   * @var \Drupal\openy_repeat\OpenyRepeatRepository
   */
  protected $repository;
  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The custom block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContentStorage;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RepeatSchedulesBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\openy_repeat\OpenyRepeatRepository $repository
   *   Repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, RouteMatchInterface $route_match, RequestStack $request_stack, OpenyRepeatRepository $repository, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->routeMatch = $route_match;
    $this->request = $request_stack->getCurrentRequest();
    $this->repository = $repository;
    $this->entityTypeManager = $entityTypeManager;
    $this->blockContentStorage = $this->entityTypeManager->getStorage('block_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('openy_repeat.repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Return Location from "Session" node type.
   *
   * @return array
   */
  public function getLocations() {

    $query = $this->database->select('node', 'n');
    $query->join('node__field_session_location', 'l', "n.nid = l.entity_id AND l.bundle = 'session'");
    $query->join('node_field_data', 'nfd', 'l.field_session_location_target_id = nfd.nid');
    $query->condition('n.type', 'session');
    $query->fields('nfd', ['title']);
    $query->orderBy('nfd.title');
    $query->addTag('repeat_schedules_block_locations');
    $result = $query->distinct()->execute()->fetchCol();

    natsort($result);
    return $result;
  }

  /**
   * Return Categories from chain "Session" -> "Class" -> "Activity" -> "Program sub-category".
   *
   * @param array $nids
   *   Array that contains categories nids to exclude.
   *
   * @return array
   */
  public function getCategories(array $nids) {
    $query = $this->database->select('node_field_data', 'nfd');
    $query->fields('nfd', ['title']);
    $query->condition('type', 'activity');
    $query->condition('status', 1);
    if ($nids) {
      $query->condition('nid', $nids, 'NOT IN');
    }
    $query->orderBy('title');
    $query->distinct(TRUE);
    $query->addTag('repeat_schedules_block_categories');
    $result = $query->execute()->fetchCol();

    natsort($result);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $query = $this->request->query;
    $locations = $query->get('locations');
    $categories = $query->get('categories');
    $excluded_categories = [];
    $checked_categories = [];
    if (!empty($categories)) {
      $checked_categories = explode(',', $categories);
    }
    $checked_locations = [];
    if (!empty($locations)) {
      $checked_locations = explode(',', $locations);
    }
    // Find repeat_schedules paragraph.
    if (!$node = $this->routeMatch->getParameter('node')) {
      return [];
    }
    if (!$node instanceof NodeInterface) {
      return [];
    }
    $paragraphs = [];
    if ($node->hasField('layout_builder__layout') &&
      !$node->get('field_use_layout_builder')->isEmpty()) {
      $node_sections = $node->get('layout_builder__layout')->getValue();
      foreach ($node_sections as $sections) {
        foreach ($this->getInlineBlockComponents($sections) as $component) {
          $configuration = $component->getPlugin()->getConfiguration();
          if ($configuration['id'] == 'inline_block:lb_repeat_schedules' && isset($configuration['block_revision_id'])) {
            $block = $this->blockContentStorage->loadByProperties(['revision_id' => $configuration['block_revision_id']]);
            if (!empty($block)) {
              $block = reset($block);
              if ($block instanceof BlockInterface &&
                  $block->hasField('field_rs_block') &&
                  !$block->get('field_rs_block')->isEmpty()) {
                $paragraphs = $block->get('field_rs_block')->referencedEntities();
                break 2;
              }
            }
          }
        }
      }
    }
    if (empty($paragraphs)) {
      $paragraphs = $node->field_content->referencedEntities();
    }
    foreach ($paragraphs as $p) {
      if ($p->bundle() == 'repeat_schedules') {
        $filters = self::getFiltersSettings($p);
        $pdf_only = !$p->field_prgf_rs_pdf_only_view->isEmpty() ? $p->field_prgf_rs_pdf_only_view->getValue()[0]['value'] : '';
        $schedule_excl = !$p->field_prgf_repeat_schedule_excl->isEmpty() ? $p->field_prgf_repeat_schedule_excl->getValue() : '';
        // Setup redirect to PDF generation route if pdf only option is enabled.
        if ($pdf_only) {
          $p_categories = $p->field_prgf_repeat_schedule_categ->referencedEntities();
          $q_p_categories = [];
          foreach ($p_categories as $p_category) {
            $q_p_categories[] = $p_category->getTitle();
          }
          $query = [
            'category' => implode(',', $q_p_categories),
            'mode' => 'day',
          ];
          $path = Url::fromRoute('openy_repeat.pdf', [], ['query' => $query])->toString();
          $response = new RedirectResponse($path, 302);
          $response->send();
        }
        if ($schedule_excl) {
          foreach ($schedule_excl as $item) {
            $excluded_categories[] = $item['target_id'];
          }
        }
      }
    }

    return [
      '#theme' => 'openy_repeat_schedule_dashboard',
      '#locations' => $this->repository->getLocations(),
      '#categories' => $this->getCategories($excluded_categories),
      '#checked_locations' => $checked_locations,
      '#checked_categories' => $checked_categories,
      '#filters' => $filters,
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
  }

  /**
   * Gets value of paragraph filters field.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $p
   *   The paragraph to take data from.
   *
   * @return array
   *   An associative array of values.
   */
  public static function getFiltersSettings(Paragraph $p) {
    if ($p->field_prgf_repeat_schedule_filt->isEmpty()) {
      return [];
    }

    $filters = [];
    foreach ($p->field_prgf_repeat_schedule_filt->getValue() as $f) {
      $filters[$f['value']] = $f['value'];
    }

    return $filters;
  }

}
