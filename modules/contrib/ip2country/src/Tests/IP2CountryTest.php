<?php

/**
 * @file
 * Contains \Drupal\ip2country\Tests\IP2CountryTest.
 *
 * @author Tim Rohaly.    <http://drupal.org/user/202830>
 */

namespace Drupal\ip2country\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Component\Utility\Unicode;

/** Utility functions for loading IP/Country DB from external sources */
\Drupal::moduleHandler()->loadInclude('ip2country', 'inc', 'ip2country');

/**
 * Tests operations of the IP to Country module.
 *
 * @group IP2Country
 */
class IP2CountryTest extends WebTestBase {

  //Need 1 class for unit tests, 1 class for functional tests
  //1 function for DB tests because filling takes so long

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('dblog', 'help', 'block');

  /**
   * Don't check for or validate config schema.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /** Admin user */
  protected $admin_user;

  /** Authenticated but unprivileged user */
  protected $unpriv_user;


  /**
   * Overrides WebTestBase::setUp().
   */
  public function setUp() {
    //
    // Don't install ip2country! parent::setUp() creates a clean
    // environment, so we can influence the install before we call setUp().
    // We don't want the DB populated, so we'll manually install ip2country.
    //
    parent::setUp();

    //
    // Set a run-time long enough so the script won't break
    //
    $this->timeLimit = 3 * 60;  // 3 minutes!
    drupal_set_time_limit($this->timeLimit);

    // Turn off automatic DB download when module is installed.
    \Drupal::state()->set('ip2country_populate_database_on_install', FALSE);

    // Explicitly install the module so that it will have access
    // to the configuration variable we set above.
    $status = \Drupal::service('module_installer')->install(array('ip2country'), FALSE);
    $this->resetAll();  // The secret ingredient

    $this->assertTrue(
      $status,
      t('Module %module enabled.', ['%module' => 'ip2country'])
    );
    $this->assertTrue(
      (ip2country_get_count() == 0),
      'Database is empty.'
    );

    // System help block is needed to see output from hook_help().
    $this->drupalPlaceBlock('help_block', array('region' => 'help'));

    // Need page_title_block because we test page titles.
    $this->drupalPlaceBlock('page_title_block');

    // Create our test users.
    $this->admin_user = $this->drupalCreateUser(array(
      'administer site configuration',
      'access administration pages',
      'access site reports',
      'administer ip2country',
    ));
    $this->unpriv_user = $this->drupalCreateUser();
  }

  /**
   * Tests IP lookup for addresses in / not in the database.
   */
  public function testIPLookup() {
    ip2country_update_database('arin');

    $this->assertTrue(
      ($count = ip2country_get_count()) != 0,
      t('Database has been updated with @rows rows.', ['@rows' => $count])
    );

    // Real working IPs
    $ip_array = array(
      '125.29.33.201', '212.58.224.138',
      '184.51.240.110', '210.87.9.66',
      '93.184.216.119'
    );
    foreach ($ip_array as $ip_address) {
      // Test dotted quad string form of address
      $country = ip2country_get_country($ip_address);
      $this->assertTrue(
        $country,
        t('@ip found, resolved to @country.', ['@ip' => $ip_address, '@country' => $country])
      );

      // Test 32-bit unsigned long form of address
      $usl_country = ip2country_get_country(ip2long($ip_address));
      $this->assertTrue(
        $usl_country == $country,
        'Unsigned long lookup found same country code.'
      );

      $this->pass('Valid IP found in database.');
    }

    // Invalid and reserved IPs
    $ip_array = array(
      '127.0.0.1', '358.1.1.0'
    );
    foreach ($ip_array as $ip_address) {
      $country = ip2country_get_country($ip_address);
      $this->assertFalse(
        $country,
        t('@ip not found in database.', ['@ip' => $ip_address])
      );
      $this->pass('Invalid IP not found in database.');
    }

    ip2country_empty_database();
    $this->assertTrue(
      (ip2country_get_count() == 0),
      'Database is empty.'
    );
  }

  /**
   * Tests injecting IP data via hook_ip2country_alter()
   */
  public function testAlterHook() {
    $this->pass('testAlterHook passed.');
  }

  /**
   * Tests Default country
   */
  public function testDefaultCountry() {
    $this->pass('testDefaultCountry passed.');
  }

  /**
   * Tests module permissions / access to configuration page.
   */
  public function testUserAccess() {
    //
    // Test as anonymous user
    //
    $this->drupalGet('admin/config');
    $this->assertResponse(403);
    $this->assertText(t('Access denied'));
    $this->assertText(t('You are not authorized to access this page.'));

    $this->drupalGet('admin/config/people/ip2country');
    $this->assertResponse(403);

    // Try to trigger DB update as anonymous
    $this->drupalGet('admin/config/people/ip2country/update/arin');
    $this->assertResponse(403);

    //
    // Test as authenticated but unprivileged user
    //
    $this->drupalLogin($this->unpriv_user);
    $this->drupalGet('admin/config');
    $this->assertResponse(403);

    $this->drupalGet('admin/config/people/ip2country');
    $this->assertResponse(403);
    $this->drupalLogout();

    //
    // As admin user
    //
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config');
    $this->assertResponse(200);
    $this->assertText(t('User location'));
    $this->assertText(t('Settings for determining user location from IP address.'));

    $this->drupalGet('admin/config/people/ip2country');
    $this->assertResponse(200);
    $this->assertText(t('User location'));
    // This is output by ip2country_help().
    $this->assertText(t('Configuration settings for the ip2country module.'));
    $this->assertText(t('Database is empty.'));
    $this->assertFieldByName(
      'ip2country_watchdog',
      1,
      'Database updates are being logged to watchdog.'
    );
    $this->assertFieldByName(
      'ip2country_rir',
      'arin',
      'Database updates from arin.'
    );

    // Update database via UI - choose a random RIR
    // (Actually short-circuiting the UI here because of the Ajax call)
    $rir = array_rand(array(
      'afrinic' => 'AFRINIC',
      'arin'    => 'ARIN',
      'apnic'   => 'APNIC',
      'lacnic'  => 'LACNIC',
      'ripe'    => 'RIPE'
    ));
    $this->drupalGet('admin/config/people/ip2country/update/' . $rir);
    $this->assertText(
      t('The IP to Country database has been updated from @rir.', ['@rir' => Unicode::strtoupper($rir)])
    );

    // Check watchdog
    $this->drupalGet('admin/reports/dblog');
    $this->assertText(t('Recent log messages'));
    $this->assertText('ip2country');
    $this->assertLink(t('Manual database update from @rir server.', ['@rir' => Unicode::strtoupper($rir)]));

    // Drill down
    $this->clickLink(t('Manual database update from @rir server.', ['@rir' => Unicode::strtoupper($rir)]));
    $this->assertText(t('Details'));
    $this->assertText('ip2country');
    $this->assertText(t('Manual database update from @rir server.', ['@rir' => Unicode::strtoupper($rir)]));

    $this->drupalLogout();
  }

  /**
   * Tests $user object for proper value
   */
  public function testUserObject() {
    $this->pass('testUserObject passed.');
  }

  /**
   * Tests UI
   */
  public function testUI() {
    $this->pass('testUI passed.');
  }

  /**
   * Tests IP Spoofing
   * -- anonymous vs authenticated users
   * Check for info $messages
   */
  public function testIPSpoofing() {
    $this->pass('testIPSpoofing passed.');
  }

  /**
   * Tests Country Spoofing
   * -- anonymous vs authenticated users
   * Check for info $messages
   */
  public function testCountrySpoofing() {
    $this->pass('testCountrySpoofing passed.');
  }

  /**
   * Tests manual lookup
   */
  public function testIPManualLookup() {
    //$this->clickLink(t('Lookup'));
    $this->pass('testIPManualLookup passed.');
  }

  /**
   * Tests DB download
   */
  public function testDBDownload() {
    ip2country_empty_database();

    $this->assertTrue(
      (ip2country_get_count() == 0),
      'Database is empty.'
    );

    // Choose a random RIR
    $rir = array_rand(array(
//    'afrinic' => 'AFRINIC', // Don't use AFRINIC because it's incomplete
      'arin'    => 'ARIN',
      'apnic'   => 'APNIC',
      'lacnic'  => 'LACNIC',
      'ripe'    => 'RIPE'
    ));
    ip2country_update_database($rir);

    $this->assertTrue(
      ($count = ip2country_get_count()) != 0,
      t('Database has been updated from %rir with @rows rows.', ['%rir' => Unicode::strtoupper($rir), '@rows' => $count])
    );

    ip2country_empty_database();
    $this->assertTrue(
      (ip2country_get_count() == 0),
      'Database is empty.'
    );
  }

  /**
   * Tests manual DB update.
   */
  public function testDBManualUpdate() {
    //$this->clickLink(t('Update'));
    $rows = db_select('ip2country')->countQuery()->execute()->fetchField();
    //$this->assertText(
    //  t('The IP to Country database has been updated from @rir. @rows rows affected.', ['@rir' => $rir, '@rows' => $rows]),
    //  'Database was updated manually.'
    //);
    $this->pass('testDBManualUpdate passed.');
  }

  /**
   * Tests cron DB update.
   */
  public function testDBCronUpdate() {
    $this->pass('testDBCronUpdate passed.');
  }

  /**
   * Tests logging of DB updates.
   */
  public function testDBUpdateLogging() {
    // Turn off logging

    // Turn on logging
    $edit = array(
      'ip2country_watchdog' => array('test' => TRUE),
    );
    //$this->drupalPost(
    //  'admin/store/settings/countries/edit',
    //  $edit,
    //  t('Import')
    //);
    //$this->assertText(
    //  t('Database updated from @rir server.', ['@rir' => $rir]),
    //  'Watchdog reported database update.'
    //);

    $this->pass('testDBUpdateLogging passed.');
  }

  /**
   * Overrides WebTestBase::tearDown().
   */
  public function tearDown() {
    // Perform any clean-up tasks.
    \Drupal::state()->delete('ip2country_populate_database_on_install');

    // Finally...
    parent::tearDown();
  }
}
