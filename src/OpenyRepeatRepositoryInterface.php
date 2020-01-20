<?php

namespace Drupal\openy_repeat;

/**
 * Interface OpenyRepeatRepository.
 *
 * @package Drupal\openy_repeat
 */
interface OpenyRepeatRepositoryInterface {

  /**
   * Return Locations from "Session" node type.
   *
   * @return array
   *   Locations titles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLocations();

}
