<?php

namespace Drupal\openy_repeat\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\openy_repeat\OpenyRepeatRepository;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a 'Repeat Schedules Locations' block.
 *
 * @Block(
 *   id = "repeat_schedules_loc_block",
 *   admin_label = @Translation("Repeat Schedules Locations Block"),
 *   category = @Translation("Paragraph Blocks")
 * )
 */
class RepeatSchedulesLocBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Constructs a BlockBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\openy_repeat\OpenyRepeatRepository $repository
   *   Repository.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OpenyRepeatRepository $repository, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->repository = $repository;
    $this->routeMatch = $route_match;
  }

  /**
   * Create.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   ContainerInterface.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return RepeatSchedulesLocBlock
   *   RepeatSchedulesLocBlock.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('openy_repeat.repository'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$node = $this->routeMatch->getParameter('node')) {
      return [
        '#theme' => 'openy_repeat_schedule_locations',
        '#locations' => $this->repository->getLocations(),
      ];
    }
    $paragraphs = $node->field_content->referencedEntities();
    $scheduleNode = NULL;
    $locations = NULL;
    foreach ($paragraphs as $paragraph) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      if ($paragraph->getType() == 'repeat_schedules_loc') {
        $scheduleNode = reset($paragraph->field_prgf_repeat_lschedules_prf->referencedEntities());
      }
    }
    $refParagraphs = $scheduleNode->field_content->referencedEntities();
    foreach ($refParagraphs as $paragraph) {
      if ($paragraph->getType() == 'repeat_schedules') {
        $locationsNode = $paragraph->field_prgf_repeat_loc->referencedEntities();
        foreach ($locationsNode as $location) {
          $locations[] = $location->title->value;
        }
      }
    }
    if (!$locations) {
      $locations = $this->repository->getLocations();
    }
    return [
      '#theme' => 'openy_repeat_schedule_locations',
      '#locations' => $locations,
    ];
  }

}
