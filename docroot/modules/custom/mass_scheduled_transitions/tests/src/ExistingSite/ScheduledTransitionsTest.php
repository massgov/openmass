<?php
namespace Drupal\Tests\mass_scheduled_transitions\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\workflows\Entity\Workflow;
use weitzman\DrupalTestTraits\ExistingSiteBase;

class ScheduledTransitionsTest extends ExistingSiteBase {

  const FORMAT = 'Y-m-d';

  protected function setUp() {
    parent::setUp();
    $this->author = $this->createUser();
  }

  public function testCreateUpdateScheduledTransition() {
    $workflow = Workflow::load('editorial');

    // Create a draft alert. No ST is created.
    $node = $this->createNode([
      'type' => 'alert',
      'title' => 'Test Alert',
      'uid' => $this->author->id(),
      'moderation_state' => MassModeration::DRAFT,
    ]);
    $transitions = mass_scheduled_transitions_loadByHostEntity($node);
    $this->assertEmpty($transitions);

    // Publish. ST is auto-created.
    $node->setPublished()->set('moderation_state', MassModeration::PUBLISHED)->save();
    $transitions = mass_scheduled_transitions_loadByHostEntity($node);
    $this->assertCount(1, $transitions);
    $transition = current($transitions);
    $this->assertEqual($transition->getAuthor()->id(), $this->author->id());
    $this->assertEqual($transition->getTransitionDate()->format(self::FORMAT), $this->getExpectedDate($node));
    $this->assertEqual($transition->getState(), MassModeration::UNPUBLISHED);

    // Make an edit - no new ST.
    $node->setCreatedTime((new DrupalDateTime('now'))->getTimestamp())->save();
    $transitions = mass_scheduled_transitions_loadByHostEntity($node);
    $this->assertCount(1, $transitions);

    // Modify existing ST from unpublish to publish; assure that a new ST is created upon node save.
    $transition->setState($workflow, MassModeration::PUBLISHED)->save();
    $transitions = mass_scheduled_transitions_loadByHostEntity($node, FALSE, MassModeration::UNPUBLISHED);
    $this->assertCount(0, $transitions);
    $node->setCreatedTime((new DrupalDateTime('now'))->getTimestamp())->save();
    $transitions = mass_scheduled_transitions_loadByHostEntity($node, FALSE, MassModeration::UNPUBLISHED);
    $this->assertCount(1, $transitions);
    $transition = current($transitions);
    $this->assertEqual($transition->getTransitionDate()->format(self::FORMAT), $this->getExpectedDate($node));
  }

  /**
   * @param \Drupal\node\NodeInterface $node
   *
   * @return string
   */
  protected function getExpectedDate(\Drupal\node\NodeInterface $node): string {
    // 'c' is ISO 8601 date format.
    return (new DrupalDateTime('@' . $node->getChangedTime() . ' + ' . MASS_SCHEDULED_TRANSITIONS_ALERT_DEFAULT_DURATION))->format(self::FORMAT);
  }

}