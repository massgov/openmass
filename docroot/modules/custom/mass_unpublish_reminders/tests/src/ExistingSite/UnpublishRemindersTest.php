<?php

namespace Drupal\Tests\mass_unpublish_reminders\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\workflows\Entity\Workflow;
use DrupalTest\QueueRunnerTrait\QueueRunnerTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\DrupalTestTraits\Mail\MailCollectionAssertTrait;
use weitzman\DrupalTestTraits\Mail\MailCollectionTrait;

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
  protected function setUp(): void {
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
  protected function tearDown(): void {
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

    $node = $this->createNode([
      'type' => 'alert',
      'title' => 'Test Alert',
      'uid' => $this->author->id(),
      'created' => strtotime('-10 days'),
      'changed' => strtotime('-9 days'),
      'moderation_state' => 'published',
    ]);
    // Edit the auto-created transition to 3 days.
    $transitions = mass_scheduled_transitions_load_by_host_entity($node, FALSE, MassModeration::UNPUBLISHED);
    $transition = current($transitions);
    $date = new DrupalDateTime('+ 3 days');
    $transition->setTransitionDate($date->getPhpDateTime())->save();

    // Look at upcoming transitions and enqueue the emails.
    mass_unpublish_reminders_cron();
    // Send the emails.
    $this->runQueue('mass_unpublish_reminders_queue');

    $this->assertMailCollection()
      ->seekToModule(static::MASS_UNPUBLISH_MODULENAME)
      ->seekToRecipient($this->author->mail->value)
      ->countEquals(1);
  }

  /**
   * Test For Promotional Page Content Type.
   */
  public function testUnpublishPromotionalPageReminders() {

    $node = $this->createNode([
      'type' => 'campaign_landing',
      'title' => 'Test Promotional Page',
      'uid' => $this->author->id(),
      'created' => strtotime('-10 days'),
      'changed' => strtotime('-9 days'),
      'moderation_state' => 'published',
    ]);

    // Create transition to 6 days.
    $workflow = 'campaign_landing_page';
    $transition = ScheduledTransition::create([]);
    $datetime = (new DrupalDateTime("now + 6 days"))->getPhpDateTime();
    $transition->setTransitionDate($datetime)
      ->setAuthor($this->author)
      ->setState(Workflow::load($workflow), MassModeration::UNPUBLISHED)
      ->setEntity($node)
      // 0 means 'latest'.
      ->setEntityRevisionId(0)
      ->setOptions([MASS_SCHEDULED_TRANSITIONS_OPTIONS])
      ->save();

    // Look at upcoming transitions and enqueue the emails.
    mass_unpublish_reminders_cron();
    // Send the emails.
    $this->runQueue('mass_unpublish_reminders_queue');

    $this->assertMailCollection()
      ->seekToModule(static::MASS_UNPUBLISH_MODULENAME)
      ->seekToRecipient($this->author->mail->value)
      ->countEquals(1);
  }

}
