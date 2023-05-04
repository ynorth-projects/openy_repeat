<?php

namespace Drupal\openy_repeat;

/**
 * Interface OpenyRepeatRepository.
 *
 * @package Drupal\openy_repeat
 */
interface OpenyRepeatRepositoryInterface {

  /**
   * Return Locations from "Session" node type for schedules.
   *
   * @return array
   *   Locations titles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLocations();

  /**
   * Return Locations ids from "session" node type for schedules.
   *
   * @return array
   *   Locations ids.
   */
  public function getLocationsIds();

  /**
   * Return Sessions ids for schedules.
   *
   * @return array
   *   Sessions ids.
   */
  public function getSessionsIds();

  /**
   * Return all classes ids for schedules.
   *
   * @return array
   *   Classes ids.
   */
  public function getClassesIds();

  /**
   * Return Classes ids for schedules by activity id.
   *
   * @param $activityId
   *   Activity ID.
   *
   * @return array
   *   Classes ids.
   */
  public function getClassesIdsByActivity($activityId);

  /**
   * Return all activities ids for schedules.
   *
   * @return array
   *   Activities ids.
   */
  public function getActivitiesIds();

}
