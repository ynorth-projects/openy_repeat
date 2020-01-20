<?php

namespace Drupal\openy_repeat;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class OpenyRepeatRepository.
 */
class OpenyRepeatRepository implements OpenyRepeatRepositoryInterface {

  use UseCacheBackendTrait;

  const CHUNK_NODE_SIZE = 50;

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a OpenyRepeatRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   EntityTypeManager.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   ConfigFactory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(EntityTypeManager $entity_type_manager, ConfigFactory $configFactory, CacheBackendInterface $cache_backend) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocations() {
    if ($cache = $this->cacheGet('openy_repeat_locations')) {
      return $cache->data;
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');

    $sessionsIds = [];

    // Get general activities.
    $gxpActivity = $this->configFactory->get('openy_gxp.settings')->get('activity');
    $activitiesIds = $nodeStorage->getQuery()->condition('type', 'activity');
    if ($gxpActivity) {
      $activitiesIds = $activitiesIds->condition('field_activity_category', $gxpActivity);
    }
    $activitiesIds = $activitiesIds->execute();

    // Get all ids schedules.
    foreach ($activitiesIds as $activityId) {
      $classIds = $nodeStorage->getQuery()
        ->condition('type', 'class')
        ->condition('field_class_activity', $activityId)
        ->execute();
      $chunks = array_chunk($classIds, self::CHUNK_NODE_SIZE, TRUE);
      foreach ($chunks as $classIds) {
        foreach ($classIds as $classId) {
          $sessionsIdsByClassId = $nodeStorage->getQuery()
            ->condition('type', 'session')
            ->condition('field_session_class', $classId)
            ->execute();
          $sessionsIds = array_merge($sessionsIds, $sessionsIdsByClassId);
        }
      }
    }

    // Get locations used by schedules.
    $locationsIds = [];
    $chunks = array_chunk($sessionsIds, self::CHUNK_NODE_SIZE, TRUE);
    foreach ($chunks as $chunk) {
      $sessions = $nodeStorage->loadMultiple($chunk);
      foreach ($sessions as $session) {
        /* @var $session \Drupal\node\Entity\Node */
        $locationId = $session->get('field_session_location')->getString();
        $locationsIds[$locationId] = $locationId;
      }
    }

    $chunks = array_chunk($locationsIds, self::CHUNK_NODE_SIZE, TRUE);

    $locationsTitles = [];
    $locationsTypes = [];
    $tags = [];
    foreach ($chunks as $chunk) {
      $locationsNodes = $nodeStorage->loadMultiple($chunk);
      foreach ($locationsNodes as $locationNode) {
        /* @var $locationNode \Drupal\node\Entity\Node */
        $locationsTitles[] = $locationNode->get('title')->getString();
        $locationType = $locationNode->get('type')->getString();
        $locationsTypes[$locationType] = $locationType;
        // Add cache tag by node.
        $tags[] = 'node:' . $locationNode->id();
      }
    }
    sort($locationsTitles);

    // Add list cache tags by node type.
    foreach ($locationsTypes as $locationType) {
      $tags[] = 'node:type:' . $locationType;
    }

    // Reset cache only when locations data change.
    $this->cacheSet('openy_repeat_locations', $locationsTitles, Cache::PERMANENT, $tags);
    return $locationsTitles;
  }

}
