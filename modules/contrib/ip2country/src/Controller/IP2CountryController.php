<?php

/**
 * @file
 * Contains \Drupal\ip2country\Controller\IP2CountryController.
 */

namespace Drupal\ip2country\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;

/** Utility functions for loading IP/Country DB from external sources */
\Drupal::moduleHandler()->loadInclude('ip2country', 'inc', 'ip2country');

/**
 * Controller routines for user routes.
 */
class IP2CountryController extends ControllerBase {

  /**
   * AJAX callback to update the IP to Country database.
   *
   * @param string $rir
   *   String with name of IP registry. One of 'afrinic', 'arin', 'lacnic',
   *   'ripe'. Not case sensitive.
   *
   * @return
   *   JSON object for display by jQuery script.
   */
  public function updateDatabaseAction($rir) {

    $ip2country_config = \Drupal::config('ip2country.settings');

    // Update DB from RIR.
    $status = ip2country_update_database($rir);

    if ($status != FALSE) {
      if ($ip2country_config->get('watchdog')) {
        \Drupal::logger('ip2country')->notice('Manual database update from @registry server.', ['@registry' => Unicode::strtoupper($rir)]);
      }
      print Json::encode(array(
        'count'   => t('@rows rows affected.',
                        ['@rows' => ip2country_get_count()]),
        'server'  => $rir,
        'message' => t('The IP to Country database has been updated from @server.',
                       ['@server' => Unicode::strtoupper($rir)]),
      ));
    }
    else {
      if ($ip2country_config->get('watchdog')) {
        \Drupal::logger('ip2country')->notice('Manual database update from @registry server FAILED.', ['@registry' => Unicode::strtoupper($rir)]);
      }
      print Json::encode(array(
        'count'   => t('@rows rows affected.', ['@rows' => 0]),
        'server'  => $rir,
        'message' => t('The IP to Country database update failed.'),
      ));
    }
    exit();
  }


  /**
   * AJAX callback to lookup an IP address in the database.
   *
   * @param string $ip_address
   *   String with IP address.
   *
   * @return
   *   JSON object for display by jQuery script.
   */
  public function lookupAction($ip_address) {

    // Return results of manual lookup.
    $country_code = ip2country_get_country($ip_address);
    if ($country_code) {
      $country_list = \Drupal::service('country_manager')->getList();
      $country_name = $country_list[$country_code];
      print Json::encode(array(
        'message' => t('IP Address @ip is assigned to @country (@code).',
                       ['@ip'      => $ip_address,
                        '@country' => $country_name,
                        '@code'    => $country_code]),
      ));
    }
    else {
      print Json::encode(array(
        'message' => t('IP Address @ip is not assigned to a country.',
                       ['@ip' => $ip_address]),
      ));
    }
    exit();
  }

}
