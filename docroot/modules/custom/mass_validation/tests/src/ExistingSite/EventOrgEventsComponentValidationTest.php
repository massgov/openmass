<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests org_events detection for associated service page validation.
 *
 * @see https://jira.mass.gov/browse/DP-46833
 */
class EventOrgEventsComponentValidationTest extends MassExistingSiteBase {

  /**
   * Direct org_events on field_service_sections is detected.
   */
  public function testLayoutParagraphsOrgEventsDetectedOnServicePage() {
    $org_events = Paragraph::create(['type' => 'org_events']);
    $org_events->save();
    $this->markEntityForCleanup($org_events);

    $service_section = Paragraph::create(['type' => 'service_section']);
    $service_section->save();
    $this->markEntityForCleanup($service_section);

    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page Event Validation',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page Event Validation',
      'field_service_lede' => 'Test lede for event validation.',
      'field_organizations' => [$org_node],
      'field_service_sections' => [
        ['entity' => $service_section],
        ['entity' => $org_events],
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->assertTrue(_mass_validation_check_paragraph_bundle($service_page, 'org_events'));
  }

  /**
   * Service pages without org_events still report the component as missing.
   */
  public function testMissingOrgEventsStillReportedOnServicePage() {
    $service_section = Paragraph::create(['type' => 'service_section']);
    $service_section->save();
    $this->markEntityForCleanup($service_section);

    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page Without Events',
      'field_service_lede' => 'Test lede.',
      'field_service_sections' => [['entity' => $service_section]],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->assertFalse(_mass_validation_check_paragraph_bundle($service_page, 'org_events'));
  }

}
