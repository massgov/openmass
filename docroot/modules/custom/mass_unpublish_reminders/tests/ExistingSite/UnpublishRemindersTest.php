<?php

namespace Drupal\Tests\mass_unpublish_reminders\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use DrupalTest\QueueRunnerTrait\QueueRunnerTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\DrupalTestTraits\Mail\MailCollectionAssertTrait;
use weitzman\DrupalTestTraits\Mail\MailCollectionTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Tests reminder emails functionality.
 */
class UnpublishRemindersTest extends ExistingSiteBase {

  use MailCollectionAssertTrait;
  use MailCollectionTrait;
  use UserCreationTrait;
  use TaxonomyCreationTrait;
  use QueueRunnerTrait;

  const MASS_UNPUBLISH_MODULENAME = 'mass_unpublish_reminders';
  const MASS_UNPUBLISH_MAILKEY = 'unpublish_reminder';

  protected $author;

  protected $organization;

  protected $users;

  protected $lastRun;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->startMailCollection();
    $this->clearQueue('mass_unpublish_reminders_queue');

    // mass.gov use a settings.vm.php config override. Override it so mail collection works.
    // No better way than GLOBALS. See \Drupal\Core\Config\ConfigFactory::doGet.
    $GLOBALS['config']['mailsystem.settings']['defaults']['sender'] = 'test_mail_collector';
    $this->container->get('config.factory')->clearStaticCache();

    $last_run = \Drupal::state()->get('mass_unpublish_reminders.last_cron', 0);
    $this->lastRun = $last_run;
    \Drupal::state()->set('mass_unpublish_reminder.last_cron', 0);

    $this->organization = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrganization',
    ]);
    $author_values = [
      'field_user_org' => $this->organization->tid->value,
      'mail' => 'testauthor@mass.local',
    ];
    $this->author = $this->createUser([], NULL, FALSE, $author_values);

    for ($x = 0; $x <= 2; $x++) {
      $users[] = $this->createUser([], NULL, FALSE, [
        'field_user_org' => $this->organization->tid->value,
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

    \Drupal::state()->set('mass_unpublish_reminder.last_cron', $this->lastRun);
    $this->clearQueue('mass_unpublish_reminders_queue');
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
      'created' => strtotime('-10 days'),
      'changed' => strtotime('-9 days'),
      'unpublish_on' => strtotime('+3 days', \Drupal::time()->getRequestTime()),
      'moderation_state' => 'published',
    ]);
    // Look at upcoming transitions and enqueue the emails.
    mass_unpublish_reminders_cron();
    // Send the emails.
    $this->runQueue('mass_unpublish_reminders_queue');

    $this->assertMailCollection()
      ->seekToModule(static::MASS_UNPUBLISH_MODULENAME)
      ->seekToRecipient($this->author->mail->value)
      ->countEquals(1);

    $mails = $this->getMails();
    $this->assertNotEmpty($mails);
    $this->assertNotEmpty($mails[0]['headers']);
    fwrite(STDERR, print_r($mails[0]['headers'], TRUE));
    $this->assertNotEmpty($mails[0]['headers']['cc']);

  }

  /**
   * Test For Promotional Page Content Type.
   */
  public function testUnpublishPromotionalPageReminders() {

    $this->createNode([
      'type' => 'campaign_landing',
      'title' => 'Test Promotional Page',
      'uid' => $this->author->id(),
      'created' => strtotime('-10 days'),
      'changed' => strtotime('-9 days'),
      'unpublish_on' => strtotime('+6 days', \Drupal::time()->getRequestTime()),
      'moderation_state' => 'published',
    ]);

    // Look at upcoming transitions and enqueue the emails.
    mass_unpublish_reminders_cron();
    // Send the emails.
    $this->runQueue('mass_unpublish_reminders_queue');

    $this->assertMailCollection()
      ->seekToModule(static::MASS_UNPUBLISH_MODULENAME)
      ->seekToRecipient($this->author->mail->value)
      ->countEquals(1);

    $mails = $this->getMails();
    $this->assertNotEmpty($mails);
    $this->assertNotEmpty($mails[0]['headers']);
    $this->assertNotEmpty($mails[0]['headers']['cc']);

  }

}
