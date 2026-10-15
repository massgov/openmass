<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use DrupalTest\QueueRunnerTrait\QueueRunnerTrait;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\ConfigTrait;

/**
 * Deleting old node revisions must not destroy usage of the live revision.
 *
 * Revision cleanup (node_revision_delete or any deleteRevision() caller) used
 * to enqueue an entity_usage_tracker item that carried no revision ID. The
 * queue worker then loaded the current default revision and deleted the usage
 * records keyed to it — wiping "Pages linking here" data for live content.
 */
class RevisionDeleteUsagePreservationTest extends MassExistingSiteBase {

  use ConfigTrait;
  use QueueRunnerTrait;

  protected function setUp(): void {
    parent::setUp();
    $this->setConfigValues([
      'entity_usage_queue_tracking.settings' => [
        'queue_tracking' => TRUE,
      ],
    ]);
    $this->container->get('config.factory')->clearStaticCache();
    $this->clearQueue('entity_usage_tracker');
  }

  protected function tearDown(): void {
    $this->restoreConfigValues();
    parent::tearDown();
  }

  /**
   * Counts raw entity_usage rows for a target entity.
   */
  private function countUsageRecords($entity): int {
    return (int) \Drupal::database()
      ->select('entity_usage', 'eu')
      ->condition('target_type', $entity->getEntityTypeId())
      ->condition('target_id', $entity->id())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Usage of the live revision survives deletion of an old revision.
   */
  public function testRevisionDeletionPreservesCurrentUsage(): void {
    $target = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Usage target for revision cleanup',
      'field_topic_lede' => 'Short description',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $link = '<a href="' . $target->toUrl()->toString() . '">LINK</a>';

    $rich_text = Paragraph::create([
      'type' => 'rich_text',
      'field_body' => [
        'value' => $link,
        'format' => 'basic_html',
      ],
    ]);
    $section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [$rich_text],
    ]);
    $source = $this->createNode([
      'type' => 'org_page',
      'title' => 'Usage source',
      'moderation_state' => MassModeration::PUBLISHED,
      'field_organization_sections' => [$section],
    ]);
    $first_vid = $source->getRevisionId();

    $this->runQueue('entity_usage_tracker');
    $this->assertGreaterThan(0, $this->countUsageRecords($target), 'Usage is tracked after the initial save.');

    // Create a second revision, modifying the paragraph so it also gets a new
    // revision — the old node revision then exclusively owns the old paragraph
    // revision, exactly the shape revision cleanup deletes.
    $source = Node::load($source->id());
    $section = $source->get('field_organization_sections')->entity;
    $rich_text = $section->get('field_section_long_form_content')->entity;
    $rich_text->set('field_body', [
      'value' => $link . ' updated',
      'format' => 'basic_html',
    ]);
    $section->set('field_section_long_form_content', [$rich_text]);
    $source->set('field_organization_sections', [$section]);
    $source->set('moderation_state', MassModeration::PUBLISHED);
    $source->setNewRevision(TRUE);
    $source->save();

    $this->runQueue('entity_usage_tracker');
    $usage_before_cleanup = $this->countUsageRecords($target);
    $this->assertGreaterThan(0, $usage_before_cleanup, 'Usage is present after the second revision.');

    // Delete the old revision the same way node_revision_delete does, then
    // process everything the deletion put into the tracking queue.
    $this->container->get('entity_type.manager')->getStorage('node')->deleteRevision($first_vid);
    $this->runQueue('entity_usage_tracker');

    $this->assertSame($usage_before_cleanup, $this->countUsageRecords($target), 'Usage of the live revision survives old revision cleanup and queue processing.');
  }

}
