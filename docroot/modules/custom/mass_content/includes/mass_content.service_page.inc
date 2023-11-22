<?php

/**
 * @file
 * Functions needed for the migration of service_page data into dynamic sections.
 */

use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Helper function to check if paragraph of given type is already populated.
 */
function check_paragraph_bundle($node, $bundle) {
  $p_sections = Helper::getReferencedEntitiesFromField($node, 'field_service_sections');
  if ($p_sections) {
    foreach ($p_sections as $p_section) {
      $p_contents = Helper::getReferencedEntitiesFromField($p_section, 'field_service_section_content');
      if ($p_contents) {
        foreach ($p_contents as $p_content) {
          if ($p_content->bundle() == $bundle) {
            return TRUE;
          }
        }
      }
    }
  }
  return FALSE;
}

/**
 * Helper function to check if Additional Resources is already populated.
 */
function check_additional_resources($node) {
  $p_sections = Helper::getReferencedEntitiesFromField($node, 'field_service_sections');
  if ($p_sections) {
    foreach ($p_sections as $p_section) {
      $p_contents = Helper::getReferencedEntitiesFromField($p_section, 'field_service_section_content');
      if ($p_contents) {
        foreach ($p_contents as $p_content) {
          if ($p_content->bundle() == 'flexible_link_group') {
            if (Helper::isFieldPopulated($p_content, 'field_flexible_link_group_title')) {
              $value = $p_content->get('field_flexible_link_group_title')->value;
              if ($value == 'Additional Resources') {
                return TRUE;
              }
            }
          }
        }
      }
    }
  }
  return FALSE;
}

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
    $flexible_link_group->set('field_display_type', 'buttons');
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', 'Featured');
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;

  }
  if (!$node->field_link_group->isEmpty()) {
    // Get the field value.
    $field_link_groups = $node->get('field_link_group')->getValue();
    foreach ($field_link_groups as $field_link_group) {
      $old_link_group = Paragraph::load($field_link_group['target_id']);
      $values = $old_link_group->get('field_links_documents')->getValue();
      $title = $old_link_group->get('field_section_title')->getValue();
      $description = $old_link_group->get('field_description')->getValue();

      // Create a new flexible_link_group paragraph.
      $flexible_link_group = Paragraph::create([
        'type' => 'flexible_link_group',
      ]);

      $flexible_link_group->set('field_featured', 0);
      $flexible_link_group->set('field_display_type', 'links');
      $flexible_link_group->set('field_group_expanded', 1);
      // 1 = Links.
      $flexible_link_group->set('field_flexible_link_group_title', $title);
      $flexible_link_group->set('field_flexible_link_group_desc', $description);
      $flexible_link_group->set('field_link_group', $values);
      $flexible_link_group->save();
      $flexible_link_groups[] = $flexible_link_group;
    }
  }

  if ($flexible_link_groups) {
    // Create a new Service Section paragraph.
    $new_service_section_form_paragraph = Paragraph::create([
      'type' => 'service_section',
    ]);
    // Set the field values.
    $new_service_section_form_paragraph->set('field_service_section_content', $flexible_link_groups);
    $new_service_section_form_paragraph->set('field_service_section_heading', 'What would you like to do?');
    $new_service_section_form_paragraph->set('field_hide_heading', 0);
    $new_service_section_form_paragraph->set('field_section_style', 'enhanced');
    // Save the new paragraph.
    $new_service_section_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
  }
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
    $flexible_link_group->set('field_display_type', 'buttons');
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', 'Top tasks');
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;

  }
  if (!$node->field_service_ref_actions->isEmpty()) {
    // Get the field value.
    $field_service_ref_actions = $node->get('field_service_ref_actions')->getValue();

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
    $flexible_link_group->set('field_display_type', 'buttons');
    // 2 = Buttons.
    $flexible_link_group->set('field_flexible_link_group_title', 'All other tasks');
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;

  }
  if (!$node->field_service_key_info_links_6->isEmpty()) {
    // Get the field value.
    $field_service_key_info_links_6 = $node->get('field_service_key_info_links_6')->getValue();
    $key_information_groups = [];
    $link_group_links = [];
    foreach ($field_service_key_info_links_6 as $link) {
      if ($link) {
        // Create a new link_group_link paragraph.
        $link_group_link = Paragraph::create([
          'type' => 'link_group_link',
        ]);
        $link_group_link->set('field_link_group_link', $link);
        $link_group_link->save();
        $link_group_links[] = $link_group_link;
      }
    }

    if ($link_group_links) {
      // Create a new flexible_link_group paragraph.
      $key_information_group = Paragraph::create([
        'type' => 'flexible_link_group',
      ]);

      $key_information_group->set('field_featured', 0);
      $key_information_group->set('field_display_type', 'buttons');
      // 2 = Buttons.
      $key_information_group->set('field_flexible_link_group_title', '');
      $key_information_group->set('field_link_group', $link_group_links);
      $key_information_group->save();
      $key_information_groups[] = $key_information_group;
    }
  }

  if (empty($flexible_link_groups) && !empty($key_information_groups)) {
    $additional_resources = _mass_content_service_page_migrate_additional_resources($node, FALSE);
    if ($additional_resources) {
      $key_information_groups[] = $additional_resources;
    }
    $new_service_section_form_paragraph_key = create_service_section($key_information_groups, 'What you need to know', 'enhanced', 0);
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph_key);
  }
  elseif (!empty($flexible_link_groups) && empty($key_information_groups)) {
    $additional_resources = _mass_content_service_page_migrate_additional_resources($node, FALSE);
    if ($additional_resources) {
      $flexible_link_groups[] = $additional_resources;
    }
    $new_service_section_form_paragraph = create_service_section($flexible_link_groups, 'What would you like to do?', 'enhanced', 0);
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
  }
  elseif (!empty($flexible_link_groups) && !empty($key_information_groups)) {
    $new_service_section_form_paragraph = create_service_section($flexible_link_groups, 'What would you like to do?', 'enhanced', 0);
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);

    $additional_resources = _mass_content_service_page_migrate_additional_resources($node, FALSE);
    if ($additional_resources) {
      $key_information_groups[] = $additional_resources;
    }
    $new_service_section_form_paragraph_key = create_service_section($key_information_groups, 'What you need to know', 'enhanced', 0);
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph_key);
  }
}

/**
 * Helper function to populate service sections.
 */
function create_service_section(array $content, string $heading, string $style, bool $hide) {
  // Create a new Service Section paragraph.
  $new_service_section_form_paragraph = Paragraph::create([
    'type' => 'service_section',
  ]);
  // Set the field values.
  $new_service_section_form_paragraph->set('field_service_section_content', $content);
  $new_service_section_form_paragraph->set('field_service_section_heading', $heading);
  $new_service_section_form_paragraph->set('field_section_style', $style);
  $new_service_section_form_paragraph->set('field_hide_heading', $hide);
  // Save the new paragraph.
  $new_service_section_form_paragraph->save();
  return $new_service_section_form_paragraph;
}

/**
 * Migrate locations related data.
 */
function _mass_content_service_page_migration_locations(&$node) {
  if (!$node->field_service_ref_locations->isEmpty()) {
    $location_p = Paragraph::create([
      'type' => 'org_locations',
    ]);
    // Get the field value.
    $locations = $node->get('field_service_ref_locations')->getValue();
    $location_p->set('field_org_ref_locations', $locations);
    if (!$node->field_location_button_label->isEmpty()) {
      $location_p->set('field_location_button_label', $node->get('field_location_button_label')
        ->getValue());
    }
    if (!$node->field_location_button_short_desc->isEmpty()) {
      $location_p->set('field_location_button_short_desc', $node->get('field_location_button_short_desc')
        ->getValue());
    }
    if (!$node->field_related_organization_type->isEmpty()) {
      $location_p->set('field_related_organization_type', $node->get('field_related_organization_type')
        ->getValue());
    }
    $location_p->save();

    // Create a new Service Section paragraph.
    $new_service_section_form_paragraph = Paragraph::create([
      'type' => 'service_section',
    ]);
    // Set the field values.
    $new_service_section_form_paragraph->set('field_service_section_content', $location_p);
    $new_service_section_form_paragraph->set('field_service_section_heading', 'Locations');
    $new_service_section_form_paragraph->set('field_hide_heading', 0);
    $new_service_section_form_paragraph->set('field_section_style', 'simple');
    // Save the new paragraph.
    $new_service_section_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
  }
}

/**
 * Create empty contact placeholder paragraph.
 */
function _mass_content_service_page_add_contact_information(&$node) {
  $check = check_paragraph_bundle($node, 'contact_placeholder');
  if (!$node->field_ref_contact_info->isEmpty() && !$check) {
    $contact = Paragraph::create([
      'type' => 'contact_placeholder',
    ]);
    $contact->save();

    // Create a new Service Section paragraph.
    $new_service_section_form_paragraph = Paragraph::create([
      'type' => 'service_section',
    ]);
    // Set the field values.
    $new_service_section_form_paragraph->set('field_service_section_content', $contact);
    $new_service_section_form_paragraph->set('field_service_section_heading', 'Contact Information');
    $new_service_section_form_paragraph->set('field_hide_heading', 0);
    $new_service_section_form_paragraph->set('field_section_style', 'simple');
    // Save the new paragraph.
    $new_service_section_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
  }
}

/**
 * Add social links section.
 */
function _mass_content_service_page_add_social_links(&$node) {
  $check = check_paragraph_bundle($node, 'social_media');
  if (!$node->field_services_social_links->isEmpty() && !$check) {
    $social_media = Paragraph::create([
      'type' => 'social_media',
    ]);
    $social_media->save();

    // Create a new Service Section paragraph.
    $new_service_section_form_paragraph = Paragraph::create([
      'type' => 'service_section',
    ]);
    // Set the field values.
    $new_service_section_form_paragraph->set('field_service_section_content', $social_media);
    $new_service_section_form_paragraph->set('field_service_section_heading', 'Social');
    $new_service_section_form_paragraph->set('field_hide_heading', 0);
    $new_service_section_form_paragraph->set('field_section_style', 'simple');
    // Save the new paragraph.
    $new_service_section_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
  }
}

/**
 * Migrate additional resources section.
 */
function _mass_content_service_page_migrate_additional_resources(&$node, bool $section = TRUE) {
  $link_groups = [];
  $check_additional = check_additional_resources($node);
  if (!$check_additional) {
    if (!$node->field_service_links->isEmpty()) {
      // Get the field value.
      $field_service_links = $node->get('field_service_links')->getValue();
      foreach ($field_service_links as $link) {
        // Create a new link_group_link paragraph.
        $link_group_link = Paragraph::create([
          'type' => 'link_group_link',
        ]);

        $link_group_link->set('field_link_group_link', $link);
        $link_group_link->save();
        $link_groups[] = $link_group_link;
      }
    }

    if (!$node->field_service_file->isEmpty()) {
      // Get the field value.
      $field_service_file = $node->get('field_service_file')->getValue();

      foreach ($field_service_file as $file) {
        // Create a new link_group_document paragraph.
        $link_group_document = Paragraph::create([
          'type' => 'link_group_document',
        ]);

        $link_group_document->set('field_file_download_single', $file);
        $link_group_document->save();
        $link_groups[] = $link_group_document;
      }
    }

    if (!empty($link_groups)) {
      // Create a new flexible_link_group paragraph.
      $additional_resources = Paragraph::create([
        'type' => 'flexible_link_group',
      ]);

      $additional_resources->set('field_featured', 0);
      $additional_resources->set('field_display_type', 'links');
      $additional_resources->set('field_group_expanded', 1);
      // 1 = Links.
      $additional_resources->set('field_flexible_link_group_title', 'Additional Resources');
      $additional_resources->set('field_link_group', $link_groups);
      $additional_resources->save();

      if ($section) {
        // Create a new Service Section paragraph.
        $new_service_section_form_paragraph = Paragraph::create([
          'type' => 'service_section',
        ]);
        // Set the field values.
        $new_service_section_form_paragraph->set('field_service_section_content', $additional_resources);
        $new_service_section_form_paragraph->set('field_service_section_heading', 'Additional Resources');
        $new_service_section_form_paragraph->set('field_hide_heading', 1);
        $new_service_section_form_paragraph->set('field_section_style', 'simple');
        // Save the new paragraph.
        $new_service_section_form_paragraph->save();
        // Add the new section to the org sections field.
        _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
      }
      else {
        return $additional_resources;
      }
    }
  }
}

/**
 * Add events section.
 */
function _mass_content_service_page_add_event_section(&$node) {
  $event = Paragraph::create([
    'type' => 'org_events',
  ]);
  $event->save();

  // Create a new Service Section paragraph.
  $new_service_section_form_paragraph = Paragraph::create([
    'type' => 'service_section',
  ]);
  // Set the field values.
  $new_service_section_form_paragraph->set('field_service_section_content', $event);
  $new_service_section_form_paragraph->set('field_service_section_heading', 'Upcoming Events');
  $new_service_section_form_paragraph->set('field_hide_heading', 0);
  $new_service_section_form_paragraph->set('field_section_style', 'simple');
  // Save the new paragraph.
  $new_service_section_form_paragraph->save();
  // Add the new section to the org sections field.
  _mass_content_service_page_migration_add_section($node, $new_service_section_form_paragraph);
}

/**
 * Cleanup old values from the fields.
 */
function _mass_content_service_page_cleanup_field_values(&$node) {
  // Remove the old field values.
  $node->set('field_service_ref_actions_2', []);
  $node->set('field_service_ref_actions', []);
  $node->set('field_link_group', []);
  $node->set('field_service_key_info_links_6', []);
  $node->set('field_template', '');
  $node->set('field_service_ref_locations', []);
  $node->set('field_related_organization_type', '');
  $node->set('field_location_button_short_desc', '');
  $node->set('field_location_button_label', '');
  $node->set('field_service_links', []);
  $node->set('field_service_file', []);

}
