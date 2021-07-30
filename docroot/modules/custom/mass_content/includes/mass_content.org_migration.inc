<?php

/**
 * @file
 * Functions needed for the migration of org_page data into dynamic sections.
 */

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Add a new org section paragraph to the org sections field.
 */
function _mass_content_org_page_migration_add_section(&$node, $new_section_paragraph) {
  $field_organization_sections = [];
  if (!$node->field_organization_sections->isEmpty()) {
    // Get the field_organization_sections value.
    $field_organization_sections = $node->get('field_organization_sections')->getValue();
  }
  // Create a value array for the new section paragraph.
  $new_section_paragraph_value = [
    'target_id' => $new_section_paragraph->id(),
    'target_revision_id' => $new_section_paragraph->getRevisionId(),
  ];
  // Add the new section paragraph value to the end of the section field value.
  array_push($field_organization_sections, $new_section_paragraph_value);
  // Update the node field value.
  $node->set('field_organization_sections', $field_organization_sections);
}

/**
 * Migrate data for the featured message section.
 */
function _mass_content_org_page_migration_featured_message(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_org_featured_message->isEmpty()) {
    // Get the field value.
    $field_org_featured_message = $node->get('field_org_featured_message')->getValue();
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
      ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_org_featured_message);
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the featured items (mosaic) section.
 */
function _mass_content_org_page_migration_featured_items_mosaic(&$node) {

}

/**
 * Migrate data for the contact and org logo section.
 */
function _mass_content_org_page_migration_contact_logo(&$node) {
  // @todo: Ensure this has a destination paragraph.
}

/**
 * Migrate data for the about section.
 */
function _mass_content_org_page_migration_about(&$node) {
  // @todo: Ensure this has a destination paragraph.

}

/**
 * Migrate data for the about section.
 */
function _mass_content_org_page_migration_who_we_serve(&$node) {
  // @todo: Ensure this has a destination paragraph.

}

/**
 * Migrate data for the our organizations (organization grid) section.
 */
function _mass_content_org_page_migration_organization_grid(&$node) {

}

/**
 * Migrate data for the services offered (what would you like to do) section.
 */
function _mass_content_org_page_migration_what_would_you_like_to_do(&$node) {

}

/**
 * Migrate data for the featured topics section.
 */
function _mass_content_org_page_migration_featured_topics(&$node) {

}

/**
 * Migrate data for the news section.
 */
function _mass_content_org_page_migration_news(&$node) {

}

/**
 * Migrate data for the board members section.
 */
function _mass_content_org_page_migration_board(&$node) {

}

/**
 * Migrate data for the events section.
 */
function _mass_content_org_page_migration_events(&$node) {

}

/**
 * Migrate data for the locations section.
 */
function _mass_content_org_page_migration_locations(&$node) {

}

/**
 * Migrate data for the related organizations section.
 */
function _mass_content_org_page_migration_related_orgs(&$node) {

}
