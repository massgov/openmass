<?php

/**
 * @file
 * Functions needed for the migration of service_page data into dynamic sections.
 */

use Drupal\mayflower\Helper;
use Drupal\mayflower\Prepare\Organisms;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Drush;

/**
 * Add a new service pages paragraph to the service sections field.
 */
function _mass_content_service_page_migration_add_section(&$node, $new_section_paragraph) {
  $field_service_sections = [];
  if (!$node->field_service_sections->isEmpty()) {
    // Get the field_organization_sections value.
    $field_service_sections = $node->get('field_service_sections')->getValue();
  }
  // Create a value array for the new section paragraph.
  $new_section_paragraph_value = [
    'target_id' => $new_section_paragraph->id(),
    'target_revision_id' => $new_section_paragraph->getRevisionId(),
  ];
  // Add the new section paragraph value to the end of the section field value.
  array_push($field_service_sections, $new_section_paragraph_value);
  // Update the node field value.
  $node->set('field_service_sections', $field_service_sections);
}

/**
 * Migrate data for the featured tasks section.
 */
function _mass_content_service_page_migration_custom_link_group(&$node) {
  $flexible_link_groups = [];
  // Migrate data if the field has a value.
  if (!$node->field_service_ref_actions_2->isEmpty()) {
    // Get the field value.
    $field_service_ref_actions_2 = $node->get('field_service_ref_actions_2')->getValue();
    // Remove the old field values.
    // $node->set('field_service_ref_actions_2', []); // @TODO uncomment this.

    $link_group_links = [];
    foreach ($field_service_ref_actions_2 as $link) {
      // Create a new link_group_link paragraph.
      $link_group_link = Paragraph::create([
        'type' => 'link_group_link',
      ]);

      $link_group_link->set('field_link_group_link', $link);
      $link_group_link->save();
      $link_group_links[] = $link_group_link;
    }

    // Create a new flexible_link_group paragraph.
    $flexible_link_group = Paragraph::create([
      'type' => 'flexible_link_group',
    ]);

    $flexible_link_group->set('field_featured', 1);
    $flexible_link_group->set('field_display_type', 2);
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', 'Featured');
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;

  }
  if (!$node->field_link_group->isEmpty()) {
    // Get the field value.
    $field_link_group = $node->get('field_link_group')->getValue();
    $old_link_group = Paragraph::load($field_link_group[0]['target_id']);
    $values = $old_link_group->get('field_links_documents')->getValue();
    $title = $old_link_group->get('field_section_title')->getValue();

    // Remove the old field values.
    // $node->set('field_service_ref_actions_2', []); // @TODO uncomment this.

    // Create a new flexible_link_group paragraph.
    $flexible_link_group = Paragraph::create([
      'type' => 'flexible_link_group',
    ]);

    $flexible_link_group->set('field_featured', 0);
    $flexible_link_group->set('field_display_type', 2);
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', $title);
    $flexible_link_group->set('field_link_group', $values);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;
  }

  // Create a new Service Section paragraph.
  $new_service_section_form_paragraph = Paragraph::create([
    'type' => 'service_section',
  ]);
  // Set the field values.
  $new_service_section_form_paragraph->set('field_service_section_content', $flexible_link_groups);
  $new_service_section_form_paragraph->set('field_service_section_heading', 'What would you like to do?');
  $new_service_section_form_paragraph->set('field_hide_heading', 1);
  // Save the new paragraph.
  $new_service_section_form_paragraph->save();
  // Add the new section to the org sections field.
  _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
}

/**
 * Migrate data for the featured tasks section.
 */
function _mass_content_service_page_migration_default_link_group(&$node) {
  $flexible_link_groups = [];
  // Migrate data if the field has a value.
  if (!$node->field_service_ref_actions_2->isEmpty()) {
    // Get the field value.
    $field_service_ref_actions_2 = $node->get('field_service_ref_actions_2')->getValue();
    // Remove the old field values.
    // $node->set('field_service_ref_actions_2', []); // @TODO uncomment this.

    $link_group_links = [];
    foreach ($field_service_ref_actions_2 as $link) {
      // Create a new link_group_link paragraph.
      $link_group_link = Paragraph::create([
        'type' => 'link_group_link',
      ]);

      $link_group_link->set('field_link_group_link', $link);
      $link_group_link->save();
      $link_group_links[] = $link_group_link;
    }

    // Create a new flexible_link_group paragraph.
    $flexible_link_group = Paragraph::create([
      'type' => 'flexible_link_group',
    ]);

    $flexible_link_group->set('field_featured', 1);
    $flexible_link_group->set('field_display_type', 2);
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', 'Top tasks');
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;

  }
  if (!$node->field_service_ref_actions->isEmpty()) {
    // Get the field value.
    $field_service_ref_actions = $node->get('field_service_ref_actions')->getValue();
    // Remove the old field values.
    // $node->set('field_service_ref_actions_2', []); // @TODO uncomment this.

    $link_group_links = [];
    foreach ($field_service_ref_actions as $link) {
      // Create a new link_group_link paragraph.
      $link_group_link = Paragraph::create([
        'type' => 'link_group_link',
      ]);

      $link_group_link->set('field_link_group_link', $link);
      $link_group_link->save();
      $link_group_links[] = $link_group_link;
    }

    // Create a new flexible_link_group paragraph.
    $flexible_link_group = Paragraph::create([
      'type' => 'flexible_link_group',
    ]);

    $flexible_link_group->set('field_featured', 0);
    $flexible_link_group->set('field_display_type', 2);
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', 'All other tasks');
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;

  }
  if (!$node->field_service_key_info_links_6->isEmpty()) {
    // Get the field value.
    $field_service_key_info_links_6 = $node->get('field_service_key_info_links_6')->getValue();
    // Remove the old field values.
    // $node->set('field_service_ref_actions_2', []); // @TODO uncomment this.

    $link_group_links = [];
    foreach ($field_service_key_info_links_6 as $link) {
      // Create a new link_group_link paragraph.
      $link_group_link = Paragraph::create([
        'type' => 'link_group_link',
      ]);

      $link_group_link->set('field_link_group_link', $link);
      $link_group_link->save();
      $link_group_links[] = $link_group_link;
    }

    // Create a new flexible_link_group paragraph.
    $key_information_group = Paragraph::create([
      'type' => 'flexible_link_group',
    ]);

    $key_information_group->set('field_featured', 0);
    $key_information_group->set('field_display_type', 2);
    // 2 = Buttons.
    $key_information_group->set('field_flexible_link_group_title', '');
    $key_information_group->set('field_link_group', $link_group_links);
    $key_information_group->save();

  }

  // Create a new Service Section paragraph.
  $new_service_section_form_paragraph = Paragraph::create([
    'type' => 'service_section',
  ]);
  // Set the field values.
  $new_service_section_form_paragraph->set('field_service_section_content', $flexible_link_groups);
  $new_service_section_form_paragraph->set('field_service_section_heading', 'What would you like to do?');
  $new_service_section_form_paragraph->set('field_hide_heading', 1);
  // Save the new paragraph.
  $new_service_section_form_paragraph->save();
  // Add the new section to the org sections field.
  _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);

  // Create a new Service Section paragraph.
  $new_service_section_form_paragraph_key = Paragraph::create([
    'type' => 'service_section',
  ]);
  // Set the field values.
  $new_service_section_form_paragraph_key->set('field_service_section_content', $key_information_group);
  $new_service_section_form_paragraph_key->set('field_service_section_heading', 'What you need to know');
  $new_service_section_form_paragraph_key->set('field_hide_heading', 1);
  // Save the new paragraph.
  $new_service_section_form_paragraph_key->save();
  // Add the new section to the org sections field.
  _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph_key);
}
