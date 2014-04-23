<?php

/**
 * @file
 * Contains \Drupal\search_api\Service\ServiceExtraInfoInterface.
 */

namespace Drupal\search_api\Service;

/**
 * Defines the interface which exposes extra information about a service.
 */
interface ServiceExtraInfoInterface extends ServiceInterface {

  /**
   * Returns additional, service-specific information about this server.
   *
   * If a service class implements this method and supports the
   * "search_api_service_extra" option, this method will be used to add extra
   * information to the server's "View" tab.
   *
   * In the default theme implementation this data will be output in a table
   * with two columns along with other, generic information about the server.
   *
   * @return array
   *   An array of additional server information, with each piece of information
   *   being an associative array with the following keys:
   *   - label: The human-readable label for this data.
   *   - info: The information, as HTML.
   *   - status: (optional) The status associated with this information. One of
   *     "info", "ok", "warning" or "error". Defaults to "info".
   *
   * @see \Drupal\search_api\Service\ServiceSpecificInterface::supportsFeature()
   */
  public function getExtraInformation();

}
