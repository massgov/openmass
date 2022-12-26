<?php

namespace Drupal\tfa_unblock;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class TFAUnblockManager.
 *
 * @package Drupal\tfa_unblock
 */
class TFAUnblockManager {

  // Provides access to the t function.
  use StringTranslationTrait;

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Configuration service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * User Data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * TFAUnblockAdminForm constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   To search for blocked users.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   To retrieve TFA settings.
   * @param \Drupal\user\UserDataInterface $userData
   *   To retrieve and store updated user data.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   To log errors.
   */
  public function __construct(Connection $database,
                              ConfigFactoryInterface $configFactory,
                              UserDataInterface $userData,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->database = $database;
    $this->configFactory = $configFactory;
    $this->userData = $userData;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Generate list of users who have not set up TFA.
   *
   * @return array
   *   User TFA blocked entries.
   */
  public function getUsersNotSetup() {
    $entries = [];

    /*
     * NOTE: the raw SQL looks something like this:
     *
     * SELECT *  FROM `users_data`
     * WHERE `module` LIKE 'tfa' AND `name` LIKE 'tfa_user_settings'
     * AND uid NOT IN
     *  (SELECT DISTINCT uid FROM users_data
     *  WHERE module LIKE 'tfa' AND name LIKE 'tfa_totp_seed')
     *
     * So if validation plugins use something other than tfa_totp_seed,
     * this will need to be adjusted!
     */

    $query = $this->database->select('users_data', 'ud')
      ->fields('ud');
    $query->condition('module', 'tfa');
    $query->condition('name', 'tfa_user_settings');

    // Subquery finds users who have set up a totp seed.
    // @todo Alter/extend this query to cover additional plugins.
    $sub_query = $this->database->select('users_data', 'ud2');
    $sub_query->addField('ud2', 'uid');
    $sub_query->distinct();
    $sub_query->condition('ud2.module', 'tfa');
    $sub_query->condition('ud2.name', 'tfa_totp_seed');
    $query->condition('ud.uid', $sub_query, 'NOT IN');

    $result = $query->execute();
    // @todo Consider adding pagination.
    $records = $result->fetchAllAssoc('uid');
    $users = User::loadMultiple(array_keys($records));

    foreach ($records as $uid => $record) {
      $settings = $record->serialized ? unserialize($record->value) : $record->value;
      $user_name = $users[$uid]->getAccountName();
      $entries[$uid] = [
        'uid' => $uid,
        'user_name' => $user_name,
        'user_link' => $users[$uid]->toLink($user_name),
        'skipped' => $settings['validation_skipped'],
        'settings' => $settings,
      ];
    }

    return $entries;
  }

  /**
   * Resets the user's validation skip counter.
   *
   * @param int $uid
   *   User id to reset.
   */
  public function reset($uid) {
    // Retrieve the user's settings using the UserData service.
    $tfa_settings = $this->userData->get('tfa', $uid, 'tfa_user_settings');
    if (!empty($tfa_settings)) {
      $tfa_settings['validation_skipped'] = 0;
      try {
        $this->userData->set('tfa', $uid, 'tfa_user_settings', $tfa_settings);
        $user_name = User::load($uid)->getUsername();
        \Drupal::messenger()->addStatus($this->t('Login unblocked for @account.', ['@account' => $user_name]));
      }
      catch (\Exception $e) {
        watchdog_exception('tfa_unblock', $e);
        \Drupal::messenger()->addError($this->t('Unable to reset user. Error: @error', ['@error' => (string) $e]));
      }
    }
    else {
      $msg = $this->t('Unable to load tfa settings for user.');
      $this->loggerFactory->get('tfa_unblock')->error($msg);
      \Drupal::messenger()->addStatus($msg);
    }
  }

  /**
   * Return the TFA skip limit.
   *
   * @return int
   *   Number of times TFA setup can be skipped before user is blocked.
   */
  public function getSkipLimit() {
    $tfa_config = $this->configFactory->get('tfa.settings');
    return intval($tfa_config->get('validation_skip'));
  }

}
