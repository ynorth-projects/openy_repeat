<?php

namespace Drupal\openy_repeat\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\openy_repeat\OpenyRepeatRepository;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OpenyRepeatRepository $repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->repository = $repository;
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
      $container->get('openy_repeat.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'openy_repeat_schedule_locations',
      '#locations' => $this->repository->getLocations(),
    ];
  }

}
