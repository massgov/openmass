<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_entity_usage\UsageTrackingBlocker;
use Drupal\paragraphs\Entity\Paragraph;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests to ensure block tracking for entity usage works as expected.
 */
class UsageTrackingBlockerTest extends MassExistingSiteBase {
  use MediaCreationTrait;

  /**
   * Provides content type data for testNodes().
   */
  public function contentTypes() {
    return [
      ['binder'],
      ['curated_list'],
      ['guide_page'],
      ['how_to_page'],
      ['service_page'],
      ['org_page'],
      ['topic_page'],
      ['news'],
      ['campaign_landing'],
      ['location'],
      ['person'],
      ['event'],
    ];
  }

  /**
   * Creates a nested paragpraph.
   */
  private function createNestedParagraph($content, $state) {
    $rich_text = Paragraph::create([
      'type' => 'rich_text',
      'field_body' => [
        'value' => $content,
        'format' => 'basic_html',
      ],
    ]);

    $organization_section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [
        $rich_text,
      ],
    ]);

    $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
      'moderation_state' => $state,
      'field_organization_sections' => [$organization_section],
    ]);
    return $rich_text;
  }

  /**
   * Tests topic page form.
   *
   * @dataProvider contentTypes
   */
  public function testNodes($type) {
    $tracking_blocker = new UsageTrackingBlocker(\Drupal::database(), \Drupal::service('entity_type.manager'));

    $node = $this->createNode([
      'type' => $type,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $check = $tracking_blocker->check('node', $node->getRevisionId());
    $this->assertTrue($check);

    $node = $this->createNode([
      'type' => $type,
      'moderation_state' => MassModeration::UNPUBLISHED,
    ]);
    $check = $tracking_blocker->check('node', $node->getRevisionId());
    $this->assertFalse($check);
  }

  /**
   * Tests if paragraphs track blocking works as expected.
   */
  public function testParagraphs() {
    $tracking_blocker = new UsageTrackingBlocker(\Drupal::database(), \Drupal::service('entity_type.manager'));

    $paragraph = $this->createNestedParagraph("Any content", MassModeration::UNPUBLISHED);
    $check = $tracking_blocker->check('paragraph', $paragraph->getRevisionId());
    $this->assertFalse($check);

    $paragraph = $this->createNestedParagraph("Any content", MassModeration::PUBLISHED);
    $check = $tracking_blocker->check('paragraph', $paragraph->getRevisionId());
    $this->assertTrue($check);
  }

}
