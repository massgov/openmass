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
    // Remove the old field values.
    $node->set('field_org_featured_message', []);
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
  // Migrate data if the field has a value.
  if (!$node->field_org_featured_items->isEmpty()) {
    // Get the field value.
    $field_org_featured_items = $node->get('field_org_featured_items')->getValue();
    // Remove the old field values.
    $node->set('field_org_featured_items', []);
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_org_featured_items);
    // Get the heading from the paragraph (There can only be one paragraph and
    // the heading field is required).
    $featured_item_mosaic_paragraph = Paragraph::load($field_org_featured_items[0]['target_id']);
    $field_mosaic_heading = $featured_item_mosaic_paragraph->get('field_mosaic_heading')->getValue();
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', $field_mosaic_heading);
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the contact and org logo section.
 */
function _mass_content_org_page_migration_contact_logo(&$node) {

}

/**
 * Migrate data for the about section.
 */
function _mass_content_org_page_migration_about(&$node) {

}

/**
 * Migrate data for the about section.
 */
function _mass_content_org_page_migration_who_we_serve(&$node) {

}

/**
 * Migrate data for the our organizations (organization grid) section.
 */
function _mass_content_org_page_migration_organization_grid(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_org_our_orgs->isEmpty()) {
    // Get the field value.
    $field_org_our_orgs = $node->get('field_org_our_orgs')->getValue();
    // Remove the old field values.
    $node->set('field_org_our_orgs', []);
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_org_our_orgs);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Our Organizations');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
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
  // Migrate data if the field has a value.
  if (!$node->field_ref_card_view_6->isEmpty()) {
    // Get the field value.
    $field_ref_card_view_6 = $node->get('field_ref_card_view_6')->getValue();
    // Remove the old field values.
    $node->set('field_ref_card_view_6', []);

    $paragraph = Paragraph::create([
      'type' => 'featured_topics',
      'field_featured_topics_topics' => $field_ref_card_view_6,
    ]);

    $paragraph->save();
    $field_section_long_form_content = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Featured Topics');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the news section.
 */
function _mass_content_org_page_migration_news(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_org_featured_news_items->isEmpty()
    || !$node->field_number_of_news_items->isEmpty()) {
    $field_org_featured_news_items = $node->get('field_org_featured_news_items')->getValue();
    $field_number_of_news_items = $node->get('field_number_of_news_items')->getValue();
    $field_org_show_news_images = $node->get('field_org_show_news_images')->getValue();
    // Remove the old field values.
    $node->set('field_org_featured_news_items', []);
    $node->set('field_number_of_news_items', []);
    $node->set('field_org_show_news_images', []);
    // Create a new Organization News paragraph.
    $new_org_news_paragraph = Paragraph::create([
      'type' => 'org_news',
    ]);
    // Set the field values.
    $new_org_news_paragraph->set('field_org_featured_news_items', $field_org_featured_news_items);
    $new_org_news_paragraph->set('field_number_of_news_items', $field_number_of_news_items);
    $new_org_news_paragraph->set('field_org_show_news_images', $field_org_show_news_images);
    // Save the new paragraph.
    $new_org_news_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [
      'target_id' => $new_org_news_paragraph->id(),
      'target_revision_id' => $new_org_news_paragraph->getRevisionId(),
    ];
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Recent news & announcements');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the board members section.
 */
function _mass_content_org_page_migration_board(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_boards->isEmpty()) {
    // Get the field value.
    $field_boards = $node->get('field_boards')->getValue();
    // Remove the old field values.
    $node->set('field_boards', []);
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_boards);
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the events section.
 */
function _mass_content_org_page_migration_events(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_event_quantity->isEmpty()) {
    $field_event_quantity = $node->get('field_event_quantity')->getValue();
    // Remove the old field values.
    $node->set('field_event_quantity', []);
    // Create a new Organization Events paragraph.
    $new_org_events_paragraph = Paragraph::create([
      'type' => 'org_events',
    ]);
    // Set the field values.
    $new_org_events_paragraph->set('field_event_quantity', $field_event_quantity);
    // Save the new paragraph.
    $new_org_events_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [
      'target_id' => $new_org_events_paragraph->id(),
      'target_revision_id' => $new_org_events_paragraph->getRevisionId(),
    ];
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Upcoming Events');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the locations section.
 */
function _mass_content_org_page_migration_locations(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_org_ref_locations->isEmpty()) {
    $field_location_button_short_desc = $node->get('field_location_button_short_desc')->getValue();
    $field_location_button_label = $node->get('field_location_button_label')->getValue();
    $field_org_ref_locations = $node->get('field_org_ref_locations')->getValue();
    $field_related_organization_type = $node->get('field_related_organization_type')->getValue();
    // Remove the old field values.
    $node->set('field_location_button_short_desc', []);
    $node->set('field_location_button_label', []);
    $node->set('field_org_ref_locations', []);
    $node->set('field_related_organization_type', []);
    // Create a new Organization Locations paragraph.
    $new_org_locations_paragraph = Paragraph::create([
      'type' => 'org_locations',
    ]);
    // Set the field values.
    $new_org_locations_paragraph->set('field_location_button_short_desc', $field_location_button_short_desc);
    $new_org_locations_paragraph->set('field_location_button_label', $field_location_button_label);
    $new_org_locations_paragraph->set('field_org_ref_locations', $field_org_ref_locations);
    $new_org_locations_paragraph->set('field_related_organization_type', $field_related_organization_type);
    // Save the new paragraph.
    $new_org_locations_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [
      'target_id' => $new_org_locations_paragraph->id(),
      'target_revision_id' => $new_org_locations_paragraph->getRevisionId(),
    ];
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Locations');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the related organizations section.
 */
function _mass_content_org_page_migration_related_orgs(&$node) {

}
