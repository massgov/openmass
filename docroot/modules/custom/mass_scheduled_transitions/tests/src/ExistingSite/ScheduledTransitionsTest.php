<?php

namespace Drupal\Tests\mass_scheduled_transitions\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Class ScheduledTransitionsTest.
 */
class ScheduledTransitionsTest extends MassExistingSiteBase {

  const FORMAT = 'Y-m-d';

  private User|false $author;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->author = $this->createUser();
  }

  /**
   * Assert that ST is created as needed when alert+promo nodes are saved.
   */
  public function testCreateUpdateScheduledTransition() {
    $workflow = Workflow::load('editorial');

    // Create a draft alert. No ST is created.
    $node = $this->createNode([
      'type' => 'alert',
      'title' => 'Test Alert',
      'uid' => $this->author->id(),
      'moderation_state' => MassModeration::DRAFT,
    ]);
    $transitions = mass_scheduled_transitions_load_by_host_entity($node);
    $this->assertEmpty($transitions);

    // Publish. ST is auto-created.
    $node->setPublished()->set('moderation_state', MassModeration::PUBLISHED)->save();
    $transitions = mass_scheduled_transitions_load_by_host_entity($node);
    $this->assertCount(1, $transitions);
    $transition = current($transitions);
    $this->assertEquals($transition->getAuthor()->id(), 0);
    $this->assertEquals($transition->getTransitionDate()->format(self::FORMAT), $this->getExpectedDate($node));
    $this->assertEquals($transition->getState(), MassModeration::UNPUBLISHED);

    // Make an edit - no new ST.
    $node->setCreatedTime((new DrupalDateTime('now'))->getTimestamp())->save();
    $transitions = mass_scheduled_transitions_load_by_host_entity($node);
    $this->assertCount(1, $transitions);

    // Modify existing ST from unpublish to publish; assure that a new ST is created upon node save.
    $transition->setState($workflow, MassModeration::PUBLISHED)->save();
    $transitions = mass_scheduled_transitions_load_by_host_entity($node, FALSE, MassModeration::UNPUBLISHED);
    $this->assertCount(0, $transitions);
    $node->setCreatedTime((new DrupalDateTime('now'))->getTimestamp())->save();
    $transitions = mass_scheduled_transitions_load_by_host_entity($node, FALSE, MassModeration::UNPUBLISHED);
    $this->assertCount(1, $transitions);
    $transition = current($transitions);
    $this->assertEquals($transition->getTransitionDate()->format(self::FORMAT), $this->getExpectedDate($node));
  }

  /**
   * Get formatted date from node changed.
   */
  protected function getExpectedDate(NodeInterface $node): string {
    // 'c' is ISO 8601 date format.
    return (new DrupalDateTime('@' . $node->getChangedTime() . ' + ' . MASS_SCHEDULED_TRANSITIONS_ALERT_DEFAULT_DURATION))->format(self::FORMAT);
  }

}
