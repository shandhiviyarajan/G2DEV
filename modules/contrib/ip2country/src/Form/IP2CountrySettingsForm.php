<?php

/**
 * @file
 * Contains \Drupal\ip2country\Form\IP2CountrySettingsForm.
 */

namespace Drupal\ip2country\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Unicode;

/** Utility functions for loading IP/Country DB from external sources */
\Drupal::moduleHandler()->loadInclude('ip2country', 'inc', 'ip2country');

/**
 * Configure ip2country settings for this site.
 */
class IP2CountrySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'ip2country_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ip2country.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $ip2country_config = $this->config('ip2country.settings');

    $form['#attached']['library'][] = 'ip2country/ip2country.settings';

    // Container for database update preference forms.
    $form['ip2country_database_update'] = array(
      '#type'  => 'details',
      '#title' => t('Database updates'),
      '#open'  => TRUE,
    );

    // Form to enable watchdog logging of updates.
    $form['ip2country_database_update']['ip2country_watchdog'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Log database updates to watchdog'),
      '#default_value' => $ip2country_config->get('watchdog'),
    );

    // Form to choose RIR.
    $form['ip2country_database_update']['ip2country_rir'] = array(
      '#type'          => 'select',
      '#title'         => t('Regional Internet Registry'),
      '#options'       => array('afrinic' => 'AFRINIC', 'apnic' => 'APNIC', 'arin' => 'ARIN', 'lacnic' => 'LACNIC', 'ripe' => 'RIPE'),
      '#default_value' => $ip2country_config->get('rir'),
      '#description'   => t('Database will be downloaded from the selected RIR. You may find that the regional server nearest you has the best response time, but note that AFRINIC provides only its own subset of registration data.'),
    );

    // Form to enable MD5 checksum of downloaded databases.
    $form['ip2country_database_update']['ip2country_md5_checksum'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Perform MD5 checksum comparison'),
      '#description'   => t("Compare MD5 checksum downloaded from the RIR with MD5 checksum calculated locally to ensure the data has not been corrupted. RIRs don't always store current checksums, so if this option is checked your database updates may sometimes fail."),
      '#default_value' => $ip2country_config->get('md5_checksum'),
    );


    $intervals = array(86400, 302400, 604800, 1209600, 2419200);
    $period = array_map(array(\Drupal::service('date.formatter'), 'formatInterval'), array_combine($intervals, $intervals));
    $period[0] = t('Never');

    // Form to set automatic update interval.
    $form['ip2country_database_update']['ip2country_update_interval'] = array(
      '#type'          => 'select',
      '#title'         => t('Database update frequency'),
      '#default_value' => $ip2country_config->get('update_interval'),
      '#options'       => $period,
      '#description'   => t('Database will be automatically updated via cron.php. Cron must be enabled for this to work. Default period is 1 week (604800 seconds).'),
    );

    $update_time = \Drupal::state()->get('ip2country_last_update');
    if (!empty($update_time)) {
      $message = t(
        'Database last updated on @date at @time from @registry server.',
        [
          '@date' => format_date($update_time, 'ip2country_date'),
          '@time' => format_date($update_time, 'ip2country_time'),
          '@registry' => Unicode::strtoupper(\Drupal::state()->get('ip2country_last_update_rir'))
        ]
      );
    }
    else {
      $message = t('Database is empty. You may fill the database by pressing the @update button.', ['@update' => t('Update')]);
    }

    // Form to initiate manual updating of the IP-Country database.
    $form['ip2country_database_update']['ip2country_update_database'] = array(
      '#type'        => 'button',
      '#value'       => t('Update'),
      '#executes_submit_callback' => FALSE,
      '#prefix'      => '<p>' . t('The IP to Country Database may be updated manually by pressing the "Update" button below. Note, this may take several minutes. Changes to the above settings will not be permanently saved unless you press the "Save configuration" button at the bottom of this page.') . '</p>',
      '#suffix'      => '<span id="dbthrobber" class="message">' . $message . '</span>',
    );

    // Container for manual lookup.
    $form['ip2country_manual_lookup'] = array(
      '#type'        => 'details',
      '#title'       => t('Manual lookup'),
      '#description' => t('Examine database values'),
      '#open'        => TRUE,
    );

    // Form for IP address for manual lookup.
    $form['ip2country_manual_lookup']['ip2country_lookup'] = array(
      '#type'        => 'textfield',
      '#title'       => t('Manual lookup'),
      '#description' => t('Enter IP address'),
      //'#element_validate' => array(array($this, 'validateIP')),
    );

    // Form to initiate manual lookup.
    $form['ip2country_manual_lookup']['ip2country_lookup_button'] = array(
      '#type'        => 'button',
      '#value'       => t('Lookup'),
      '#executes_submit_callback' => FALSE,
      '#prefix'      => '<div>' . t('An IP address may be looked up in the database by entering the address above then pressing the Lookup button below.') . '</div>',
      '#suffix'      => '<span id="lookup-message" class="message"></span>',
    );

    // Container for debugging preference forms.
    $form['ip2country_debug_preferences'] = array(
      '#type'        => 'details',
      '#title'       => t('Debug preferences'),
      '#description' => t('Set debugging values'),
      '#open'        => TRUE,
    );

    // Form to turn on debugging.
    $form['ip2country_debug_preferences']['ip2country_debug'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Admin debug'),
      '#default_value' => $ip2country_config->get('debug'),
      '#description'   => t('Enables administrator to spoof an IP Address or Country for debugging purposes.'),
    );

    // Form to select Dummy Country or Dummy IP Address for testing.
    $form['ip2country_debug_preferences']['ip2country_test_type'] = array(
      '#type'          => 'radios',
      '#title'         => t('Select which parameter to spoof'),
      '#default_value' => $ip2country_config->get('test_type'),
      '#options'       => array(t('Country'), t('IP Address')),
    );

    $ip_current = \Drupal::request()->getClientIp();

    // Form to enter Country to spoof.
    $default_country = $ip2country_config->get('test_country');
    $default_country = empty($default_country) ? ip2country_get_country($ip_current) : $default_country;
    $form['ip2country_debug_preferences']['ip2country_test_country'] = array(
      '#type'          => 'select',
      '#title'         => t('Country to use for testing'),
      '#default_value' => $default_country,
      '#options'       => \Drupal::service('country_manager')->getList(),
    );

    // Form to enter IP address to spoof.
    $test_ip_address = $ip2country_config->get('test_ip_address');
    $test_ip_address = empty($test_ip_address) ? $ip_current : $test_ip_address;
    $form['ip2country_debug_preferences']['ip2country_test_ip_address'] = array(
      '#type'          => 'textfield',
      '#title'         => t('IP address to use for testing'),
      '#default_value' => $test_ip_address,
      '#element_validate' => array(array($this, 'validateIP')),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $ip2country_config = $this->config('ip2country.settings');

    $ip2country_config
      ->setData(array(
        'watchdog' => (boolean) $values['ip2country_watchdog'],
        'rir' => (string) $values['ip2country_rir'],
        'md5_checksum' => (boolean) $values['ip2country_md5_checksum'],
        'update_interval' => (integer) $values['ip2country_update_interval'],
        'debug' => (boolean) $values['ip2country_debug'],
        'test_type' => (integer) $values['ip2country_test_type'],
        'test_country' => (string) $values['ip2country_test_country'],
        'test_ip_address' => (string) $values['ip2country_test_ip_address'],
      ))
      ->save();

    // Check to see if debug set.
    if ($values['ip2country_debug']) {
      // Debug on.
      if ($values['ip2country_test_type']) {
        // Dummy IP Address.
        $ip = $values['ip2country_test_ip_address'];
        $country_code = ip2country_get_country($ip);
      }
      else {
        // Dummy Country.
        $country_code = $values['ip2country_test_country'];
      }
      $country_list = \Drupal::service('country_manager')->getList();
      $country_name = $country_list[$country_code];
      drupal_set_message(t('Using DEBUG value for Country - @country (@code)', ['@country' => $country_name, '@code'    => $country_code]));
    }
    else {
      // Debug off - make sure we set/reset IP/Country to their real values.
      $ip = \Drupal::request()->getClientIp();
      $country_code = ip2country_get_country($ip);
      drupal_set_message(t('Using ACTUAL value for Country - @country', ['@country' => $country_code]));
    }

    // Finally, save country, if it has been determined.
    if ($country_code) {
      // Store the ISO country code in the $user object.
      $account_id = \Drupal::currentUser()->id();
      $account = user_load($account_id);
      $account->country_iso_code_2 = $country_code;
      \Drupal::service('user.data')->set('ip2country', $account_id, 'country_iso_code_2', $country_code);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Element validation handler for IP address input.
   */
  public function validateIP($element, FormStateInterface $form_state) {
    $ip_address = $element['#value'];
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
      $form_state->setError($element, t('The IP address you entered is invalid. Please enter an address in the form xxx.xxx.xxx.xxx where xxx is between 0 and 255 inclusive.'));
    }
  }
}
