<?php

namespace Drupal\Tests\mass_unpublish_reminders\ExistingSite;

use Drupal\mailsystem\MailsystemManager;
use Drupal\taxonomy\Entity\Vocabulary;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\DrupalTestTraits\Mail\MailCollectionAssertTrait;
use weitzman\DrupalTestTraits\Mail\MailCollectionTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests reminder emails functionality.
 */
class UnPublishRemindersTest extends ExistingSiteBase {

  use MailCollectionAssertTrait;

  use MailCollectionTrait;

  use UserCreationTrait;

  use TaxonomyCreationTrait;

  use CronRunTrait;

  const MASS_UNPUBLISH_MODULENAME = 'mass_unpublish_reminders';
  const MASS_UNPUBLISH_MAILKEY = 'unpublish_reminder';

  protected $author;

  protected $organization;

  protected $users;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->startMailCollection();

    $module_key = static::MASS_UNPUBLISH_MODULENAME . '.' . (static::MASS_UNPUBLISH_MAILKEY ?: 'none');
    $prefix = MailsystemManager::MAILSYSTEM_MODULES_CONFIG . '.' . $module_key;

    $config = $this->container->get('config.factory')->getEditable('mailsystem.settings');
    $config->set($prefix . '.' . MailsystemManager::MAILSYSTEM_TYPE_FORMATTING, 'test_mail_collector');
    $config->set($prefix . '.' . MailsystemManager::MAILSYSTEM_TYPE_SENDING, 'test_mail_collector');
    $config->save();

    $this->organization = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrganization',
    ]);
    $author_values = [
      'field_user_org' => $this->organization->tid->value,
      'mail' => 'testauthor@mass.local',
    ];
    $this->author = $this->createUser([], 'TestNodeAuthor', FALSE, $author_values);

    for ($x = 0; $x <= 4; $x++) {
      $users[] = $this->createUser([], NULL, FALSE, [
        'field_user_org' => $this->organization->tid->value,
        'mail' => 'user' . $x . '@mass.local',
        'roles' => 'editor',
      ]);
    }
    $this->users = $users;
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->organization = NULL;
    $this->author = NULL;
    $this->users = NULL;

    $this->restoreMailSettings();
    parent::tearDown();
  }

  /**
   * Test For Alert Content Type.
   */
  public function testUnpublishAlertReminders() {

    $this->createNode([
      'type' => 'alert',
      'title' => 'Test Alert',
      'uid' => $this->author->id(),
      'unpublish_on' => strtotime('+3 days', \Drupal::time()->getRequestTime()),
      'moderation_state' => 'published',
    ]);

    $this->cronRun();

    $this->assertMailCollection()
      ->seekToModule(static::MASS_UNPUBLISH_MODULENAME)
      ->seekToRecipient($this->author->mail->value)
      ->countEquals(1);

    $mails = $this->getMails();

    foreach ($this->users as $user) {
      $cc_mails[] = $user->mail->value;
    }
    $this->assertIdentical($mails[0]['headers']['cc'], implode(',', $cc_mails));

  }

  /**
   * Test For Promotional Page Content Type.
   */
  public function testUnpublishPromotionalPageReminders() {

    $this->createNode([
      'type' => 'campaign_landing',
      'title' => 'Test Promotional Page',
      'uid' => $this->author->id(),
      'unpublish_on' => strtotime('+6 days', \Drupal::time()->getRequestTime()),
      'moderation_state' => 'published',
    ]);

    $this->cronRun();

    $this->assertMailCollection()
      ->seekToModule(static::MASS_UNPUBLISH_MODULENAME)
      ->seekToRecipient($this->author->mail->value)
      ->countEquals(1);

    $mails = $this->getMails();

    foreach ($this->users as $user) {
      $cc_mails[] = $user->mail->value;
    }
    $this->assertIdentical($mails[0]['headers']['cc'], implode(',', $cc_mails));

  }

}
