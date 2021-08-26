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
  // Migrate data if the field has a value.
  if (!$node->field_sub_brand->isEmpty()
    || !$node->field_ref_contact_info_1->isEmpty()) {

    // Create a new Organization News paragraph.
    $new_organization_contact_logo_paragraph = Paragraph::create([
      'type' => 'organization_contact_logo',
    ]);
    // Save the new paragraph.
    $new_organization_contact_logo_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [
      'target_id' => $new_organization_contact_logo_paragraph->id(),
      'target_revision_id' => $new_organization_contact_logo_paragraph->getRevisionId(),
    ];
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Contact Us');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the about section.
 */
function _mass_content_org_page_migration_about(&$node) {
  // Migrate data if the field has a value.
  if (!$node->field_about->isEmpty()) {
    // Get the field value.
    $field_about = $node->get('field_about')->getValue();
    $field_short_name_value = $node->get('field_short_name')
      ->getValue()[0]['value'];
    // Remove the old field values.
    $node->set('field_about', []);
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_about);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'About the ' . $field_short_name_value);
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}

/**
 * Migrate data for the about section.
 */
function _mass_content_org_page_migration_who_we_serve(&$node) {
  // Migrate data if the field has a value.
  if (!$node->body->isEmpty()) {
    // Get the field value.
    $body = $node->get('body')->getValue();
    $field_subtype = $node->get('field_subtype')->getValue()[0]['value'];
    // Remove the old field values.
    $node->set('body', []);
    // Create a new Rich Text paragraph.
    $new_rich_text_paragraph = Paragraph::create([
      'type' => 'rich_text',
    ]);
    // Set the field values.
    $new_rich_text_paragraph->set('field_body', $body);
    // Save the new paragraph.
    $new_rich_text_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [];
    $field_section_long_form_content[] = [
      'target_id' => $new_rich_text_paragraph->id(),
      'target_revision_id' => $new_rich_text_paragraph->getRevisionId(),
    ];
    // If there are social links on the org_page, add a Social Media paragraph.
    if (!$node->field_social_links->isEmpty()) {
      // Create a new Social Media paragraph.
      $new_social_media_paragraph = Paragraph::create([
        'type' => 'social_media',
      ]);
      // Save the new paragraph.
      $new_social_media_paragraph->save();
      // Add the new paragraph to the field value array.
      $field_section_long_form_content[] = [
        'target_id' => $new_social_media_paragraph->id(),
        'target_revision_id' => $new_social_media_paragraph->getRevisionId(),
      ];
    }
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    // Set the section heading based on the org subtype.
    if ($field_subtype === 'Boards') {
      $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'About Us');
    }
    else {
      $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Who we serve');
    }
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
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
  // Migrate data if the field has a value.
  if (!$node->field_links_actions_3->isEmpty() ||
    !$node->field_ref_actions_6->isEmpty()) {
    $field_links_actions_3 = $node->get('field_links_actions_3')->getValue();
    $field_ref_actions_6 = $node->get('field_ref_actions_6');

    // Set the link group link values.
    $field_wwyltd_more_services = [];
    if (!$node->field_ref_actions_6->isEmpty()) {
      $field_links_documents = [];
      foreach ($field_ref_actions_6->referencedEntities() as $service) {
        // Create a new Link Group Link paragraph to host each "more services".
        $new_link_group_link_paragraph = Paragraph::create([
          'type' => 'link_group_link',
        ]);
        // Get the internal URI for each service.
        $new_link_group_link_paragraph->set('field_link_group_link', ['uri' => "entity:" . $service->toUrl()->getInternalPath(), "title" => ""]);
        // Save the new paragraph.
        $new_link_group_link_paragraph->save();

        $field_links_documents[] = [
          'target_id' => $new_link_group_link_paragraph->id(),
          'target_revision_id' => $new_link_group_link_paragraph->getRevisionId(),
        ];
      }

      // Create a new Link Group Accordion paragraph to host "more services".
      $new_link_group_accordion_paragraph = Paragraph::create([
        'type' => 'link_group_accordion',
      ]);
      // Set the link group accordion values
      $new_link_group_accordion_paragraph->set('field_section_title', "More actions & services");
      $new_link_group_accordion_paragraph->set('field_links_documents', $field_links_documents);
      // Save the new paragraph.
      $new_link_group_accordion_paragraph->save();
      // Create a value array for the new section paragraph.
      $field_wwyltd_more_services = [
        'target_id' => $new_link_group_accordion_paragraph->id(),
        'target_revision_id' => $new_link_group_accordion_paragraph->getRevisionId(),
      ];
    }

    // Create a new What Would You Like To Do paragraph.
    $new_wwyltd_paragraph = Paragraph::create([
      'type' => 'what_would_you_like_to_do',
    ]);
    // Set the field values.
    $new_wwyltd_paragraph->set('field_wwyltd_heading', "What would you like to do?");
    $new_wwyltd_paragraph->set('field_wwyltd_top_s_links', $field_links_actions_3);
    $new_wwyltd_paragraph->set('field_wwyltd_more_services', $field_wwyltd_more_services);
    // Save the new paragraph.
    $new_wwyltd_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [
      'target_id' => $new_wwyltd_paragraph->id(),
      'target_revision_id' => $new_wwyltd_paragraph->getRevisionId(),
    ];

    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Remove the old field values.
    $node->set('field_links_actions_3', []);
    $node->set('field_ref_actions_6', []);
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
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
  // Migrate data if the field has a value.
  if (!$node->field_ref_orgs->isEmpty()) {
    $field_ref_orgs = $node->get('field_ref_orgs')->getValue();
    // Remove the old field values.
    $node->set('field_ref_orgs', []);
    // Create a new Organization Related Organization paragraph.
    $new_org_related_orgs_paragraph = Paragraph::create([
      'type' => 'org_related_orgs',
    ]);
    // Set the field values.
    $new_org_related_orgs_paragraph->set('field_ref_orgs', $field_ref_orgs);
    // Save the new paragraph.
    $new_org_related_orgs_paragraph->save();
    // Create a value array for the new section paragraph.
    $field_section_long_form_content = [
      'target_id' => $new_org_related_orgs_paragraph->id(),
      'target_revision_id' => $new_org_related_orgs_paragraph->getRevisionId(),
    ];
    // Create a new Organization Section paragraph.
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    // Set the field values.
    $new_org_section_long_form_paragraph->set('field_section_long_form_content', $field_section_long_form_content);
    $new_org_section_long_form_paragraph->set('field_section_long_form_heading', 'Related organizations');
    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Add the new section to the org sections field.
    _mass_content_org_page_migration_add_section($node, $new_org_section_long_form_paragraph);
  }
}
