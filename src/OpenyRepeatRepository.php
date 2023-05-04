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
   * Node.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|object
   */
  protected $nodeStorage;

  /**
   * Constructs a OpenyRepeatRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   EntityTypeManager.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   ConfigFactory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManager $entity_type_manager, ConfigFactory $configFactory, CacheBackendInterface $cache_backend) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
    $this->cacheBackend = $cache_backend;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   *
   */
  public function getActivitiesIds() {
    $gxpActivity = $this->configFactory->get('openy_gxp.settings')->get('activity');
    $activitiesIds = $this->nodeStorage->getQuery()->condition('type', 'activity');
    if ($gxpActivity) {
      $activitiesIds = $activitiesIds->condition('field_activity_category', $gxpActivity);
    }
    $activitiesIds = $activitiesIds->execute();
    return $activitiesIds;
  }

  /**
   * {@inheritdoc}
   */
  public function getClassesIdsByActivity($activityId) {
    return $this->nodeStorage->getQuery()
      ->condition('type', 'class')
      ->condition('field_class_activity', $activityId)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getClassesIds() {
    $activitiesIds = $this->getActivitiesIds();

    $classesIds = [];
    $chunks = array_chunk($activitiesIds, self::CHUNK_NODE_SIZE, TRUE);
    foreach ($chunks as $activityIds) {
      foreach ($activityIds as $activityId) {
        $classesIdsByActivity = $this->getClassesIdsByActivity($activityId);
        $classesIds = array_merge($classesIds, $classesIdsByActivity);
      }
    }
    return $classesIds;
  }

  /**
   * Obtains sessions from the PEF storage.
   */
  public function getSessionsIds() {
    $classesIds = $this->getClassesIds();
    $sessionsIds = [];
    foreach ($classesIds as $classId) {
      $sessionsIdsByClassId = $this->nodeStorage->getQuery()
        ->condition('type', 'session')
        ->condition('field_session_class', $classId)
        ->execute();
      $sessionsIds = array_merge($sessionsIds, $sessionsIdsByClassId);
    }
    return $sessionsIds;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsIds() {
    $sessionsIds = $this->getSessionsIds();
    $locationsIds = [];
    $chunks = array_chunk($sessionsIds, self::CHUNK_NODE_SIZE, TRUE);
    foreach ($chunks as $chunk) {
      $sessions = $this->nodeStorage->loadMultiple($chunk);
      foreach ($sessions as $session) {
        /** @var \Drupal\node\Entity\Node $session */
        $locationId = $session->get('field_session_location')->getString();
        $locationsIds[$locationId] = $locationId;
      }
    }
    return $locationsIds;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocations() {
    if ($cache = $this->cacheGet('openy_repeat_locations')) {
      return $cache->data;
    }

    // Get locations used by schedules.
    $locationsIds = $this->getLocationsIds();

    $tags = [];
    $locationsTitles = [];
    $locationsTypes = [];

    $chunks = array_chunk($locationsIds, self::CHUNK_NODE_SIZE, TRUE);
    foreach ($chunks as $chunk) {
      $locationsNodes = $this->nodeStorage->loadMultiple($chunk);
      foreach ($locationsNodes as $locationNode) {
        /** @var \Drupal\node\Entity\Node $locationNode */
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
    $tags[] = 'node:type:session';

    // Reset cache only when locations data and list sessions change.
    $this->cacheSet('openy_repeat_locations', $locationsTitles, Cache::PERMANENT, $tags);
    return $locationsTitles;
  }

}
