<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Content.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate iframe paragraph fields.
 */
function mass_content_deploy_iframe_fields(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('type', 'info_details')
    ->condition('field_info_details_sections.entity:paragraph.field_section_long_form_content.entity:paragraph.field_iframe_admin_title.value', "", "!=");

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::service('entity_type.manager')->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    foreach ($node->field_info_details_sections as $info_details_section) {
      $info_details_section_paragraph = Paragraph::load($info_details_section->target_id);
      foreach ($info_details_section_paragraph->field_section_long_form_content as $section_long_form_content) {
        $section_long_form_content_paragraph = Paragraph::load($section_long_form_content->target_id);

        // Set alignment to 'left' for all.
        $section_long_form_content_paragraph->field_iframe_alignment->value = 'left';

        // Migrate and map iframe display size values.
        if ($section_long_form_content_paragraph->field_media_display->value == 'normal') {
          $section_long_form_content_paragraph->field_iframe_display_size->value = 'large';
        }
        elseif ($section_long_form_content_paragraph->field_media_display->value == 'full') {
          $section_long_form_content_paragraph->field_iframe_display_size->value = 'x-large';
        }

        // Migrate iframe caption values.
        if (!empty($section_long_form_content_paragraph->field_caption->value)) {
          $section_long_form_content_paragraph->field_iframe_caption->value = $section_long_form_content_paragraph->field_caption->value;
          $section_long_form_content_paragraph->field_iframe_caption->format = 'basic_html';
        }

        $section_long_form_content_paragraph->save();
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All iframe fields migrated.');
  }
}

/**
 * Migrate image paragraph caption fields.
 */
function mass_content_deploy_image_caption_fields(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('type', 'info_details')
    ->condition('field_info_details_sections.entity:paragraph.field_section_long_form_content.entity:paragraph.field_caption.value', "", "!=");

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    foreach ($node->field_info_details_sections as $info_details_section) {
      $info_details_section_paragraph = Paragraph::load($info_details_section->target_id);
      foreach ($info_details_section_paragraph->field_section_long_form_content as $section_long_form_content) {
        $section_long_form_content_paragraph = Paragraph::load($section_long_form_content->target_id);

        // Migrate image caption values.
        if (!empty($section_long_form_content_paragraph->field_caption->value)) {
          $section_long_form_content_paragraph->field_image_caption->value = $section_long_form_content_paragraph->field_caption->value;
          $section_long_form_content_paragraph->field_image_caption->format = 'basic_html';
        }

        // Migrate and map iframe display size values.
        if ($section_long_form_content_paragraph->field_media_display->value == 'normal') {
          $section_long_form_content_paragraph->field_image_display_size->value = 'large';
        }
        elseif ($section_long_form_content_paragraph->field_media_display->value == 'full') {
          $section_long_form_content_paragraph->field_image_display_size->value = 'x-large';
        }

        $section_long_form_content_paragraph->save();
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Info Details Section image caption fields migrated.');
  }
}

/**
 * Migrate Header Media image fields.
 */
function mass_content_deploy_header_media_image_fields(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('type', 'info_details')
    ->condition('field_info_details_header_media.entity:paragraph.field_caption.value', "", "!=");

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    foreach ($node->field_info_details_header_media as $info_details_header_media) {
      $info_details_header_media = Paragraph::load($info_details_header_media->target_id);
      // Migrate image caption values.
      if (!empty($info_details_header_media->field_caption->value)) {
        $info_details_header_media->field_image_caption->value = $info_details_header_media->field_caption->value;
        $info_details_header_media->field_image_caption->format = 'basic_html';
      }

      // Migrate and map iframe display size values.
      if ($info_details_header_media->field_media_display->value == 'normal') {
        $info_details_header_media->field_image_display_size->value = 'large';
      }
      elseif ($info_details_header_media->field_media_display->value == 'full') {
        $info_details_header_media->field_image_display_size->value = 'x-large';
      }

      $info_details_header_media->save();
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Header Media image caption fields migrated.');
  }
}

/**
 * Migrate followup for image paragraph caption fields.
 */
function mass_content_deploy_image_section_fields_followup(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('status', 1)
    ->condition('type', 'info_details')
    ->condition('field_info_details_sections.entity:paragraph.field_section_long_form_content.entity:paragraph.field_image.target_id', "", "!=");

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    foreach ($node->field_info_details_sections as $info_details_section) {
      $info_details_section_paragraph = Paragraph::load($info_details_section->target_id);
      foreach ($info_details_section_paragraph->field_section_long_form_content as $section_long_form_content) {
        $section_long_form_content_paragraph = Paragraph::load($section_long_form_content->target_id);

        // Set alignment to 'left' for all.
        if ($section_long_form_content_paragraph->field_image_alignment->value == '') {
          $section_long_form_content_paragraph->field_image_alignment->value = 'left';
        }

        // Migrate and map iframe display size values.
        if ($section_long_form_content_paragraph->field_image_display_size->value == '') {
          if ($section_long_form_content_paragraph->field_media_display->value == 'normal') {
            $section_long_form_content_paragraph->field_image_display_size->value = 'large';
          }
          elseif ($section_long_form_content_paragraph->field_media_display->value == 'full') {
            $section_long_form_content_paragraph->field_image_display_size->value = 'x-large';
          }
        }

        $section_long_form_content_paragraph->save();
      }
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Info Details Section image caption fields migrated.');
  }
}

/**
 * Migrate followup for Header Media image fields.
 */
function mass_content_deploy_header_media_images_followup(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('type', 'info_details')
    ->condition('field_info_details_header_media.entity:paragraph.field_image.target_id', "", "!=");

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    foreach ($node->field_info_details_header_media as $info_details_header_media) {
      $info_details_header_media = Paragraph::load($info_details_header_media->target_id);

      // Set alignment to 'left' for all.
      if ($info_details_header_media->field_image_alignment->value == '') {
        $info_details_header_media->field_image_alignment->value = 'left';
      }

      // Migrate and map iframe display size values.
      if ($info_details_header_media->field_media_display->value == 'normal') {
        $info_details_header_media->field_image_display_size->value = 'x-large';
      }
      elseif ($info_details_header_media->field_media_display->value == 'full') {
        $info_details_header_media->field_image_display_size->value = 'x-large';
      }

      $info_details_header_media->save();
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Header Media image caption fields migrated.');
  }
}

/**
 * Migrate both published and unpublished Header Media image wrapping fields.
 */
function mass_content_deploy_header_media_images_all(&$sandbox) {
  mass_content_deploy_header_media_images_followup($sandbox);
}

/**
 * Set default text for how-to page flexible headers.
 */
function mass_content_deploy_how_to_headers(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'how_to_page');

  if (empty($sandbox)) {
    // Get a list of all nodes of type how_to_page.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $memory_cache = \Drupal::service('entity.memory_cache');

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();
    // Set the Customize header text field to unchecked.
    $node->set('field_customize_header_text', 0);
    // Set the default header field values for existing content.
    $node->set('field_downloads_header', 'Downloads');
    $node->set('field_fees_header', 'Fees');
    $node->set('field_manage_your_account_header', 'Manage Your Account');
    $node->set('field_more_info_header', 'More info');
    $node->set('field_next_steps_header', 'Next steps');
    $node->set('field_what_you_need_header', 'What you need');

    // Save the node.
    // Save without updating the last modified date. This requires a core patch
    // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
    $node->setSyncing(TRUE);
    $node->save();

    $sandbox['progress']++;
  }

  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All How-to nodes have default values for flexible header fields.');
  }
}

/**
 * Create EOTSS Service Catalog, Collection terms.
 */
function mass_content_deploy_eotss_service_catalog_terms() {
  $vocabulary = 'collections';
  $terms = [
    [
      'name' => 'EOTSS Service Catalog',
      'parent' => '',
      'weight' => 0,
    ],
    [
      'name' => 'Desktop Software',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 0,
    ],
    [
      'name' => 'Email, Calendar & Collaboration',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 1,
    ],
    [
      'name' => 'HR and Provisioning',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 2,
    ],
    [
      'name' => 'Secure Applications and Access',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 3,
    ],
    [
      'name' => 'Standard Service Offerings',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 4,
    ],
    [
      'name' => 'Support Services',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 5,
    ],
    [
      'name' => 'Telephone & Mobile Devices',
      'parent' => 'EOTSS Service Catalog',
      'weight' => 6,
    ],
  ];
  $term_ids = [];
  foreach ($terms as $data) {
    $term = Term::create([
      'parent' => !empty($data['parent']) ? $term_ids[$data['parent']] : [],
      'name' => $data['name'],
      'vid' => $vocabulary,
    ]);
    $term->save();
    // Save the term id keyed by name.
    $term_ids[$data['name']] = $term->id();
  }
}

/**
 * Add URL Name values for Data Topic terms.
 */
function mass_content_deploy_data_topic_url_name() {
  $query = \Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', 'data_topic');

  $tids = $query->sort('tid')->execute();

  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

  $terms = $term_storage->loadMultiple($tids);
  foreach ($terms as $term) {
    $field_url_name = strtolower(Html::cleanCssIdentifier($term->label()));
    $term->set('field_url_name', $field_url_name);
    $term->save();
  }
}

/**
 * Migrate data for the org_page sections.
 */
function mass_content_deploy_org_page_section_migration(&$sandbox) {
  // Include migration functions.
  require_once __DIR__ . '/includes/mass_content.org_migration.inc';

  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'org_page');

  if (empty($sandbox)) {
    // Get a list of all nodes of type org_page.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $memory_cache = \Drupal::service('entity.memory_cache');

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    $subtype = $node->field_subtype->value;
    switch ($subtype) {
      case 'General Organization':
        // Featured Message.
        _mass_content_org_page_migration_featured_message($node);

        // Featured Items Mosaic.
        _mass_content_org_page_migration_featured_items_mosaic($node);

        // Contact and Org Logo.
        _mass_content_org_page_migration_contact_logo($node);

        // Who we serve.
        _mass_content_org_page_migration_who_we_serve($node);

        // Organization Grid.
        _mass_content_org_page_migration_organization_grid($node);

        // What would you like to do?
        _mass_content_org_page_migration_what_would_you_like_to_do($node);

        // Featured Topics.
        _mass_content_org_page_migration_featured_topics($node);

        // News.
        _mass_content_org_page_migration_news($node);

        // Events.
        _mass_content_org_page_migration_events($node);

        // Locations.
        _mass_content_org_page_migration_locations($node);

        // Related Organizations.
        _mass_content_org_page_migration_related_orgs($node);

        break;

      case 'Elected Official':
        // Featured Message.
        _mass_content_org_page_migration_featured_message($node);

        // Featured Items Mosaic.
        _mass_content_org_page_migration_featured_items_mosaic($node);

        // About.
        _mass_content_org_page_migration_about($node);

        // Who we serve.
        _mass_content_org_page_migration_who_we_serve($node);

        // Contact and Org Logo.
        _mass_content_org_page_migration_contact_logo($node);

        // Organization Grid.
        _mass_content_org_page_migration_organization_grid($node);

        // What would you like to do?
        _mass_content_org_page_migration_what_would_you_like_to_do($node);

        // Featured Topics.
        _mass_content_org_page_migration_featured_topics($node);

        // News.
        _mass_content_org_page_migration_news($node);

        // Events.
        _mass_content_org_page_migration_events($node);

        // Locations.
        _mass_content_org_page_migration_locations($node);

        // Related Organizations.
        _mass_content_org_page_migration_related_orgs($node);

        break;

      case 'Boards':
        // Featured Message.
        _mass_content_org_page_migration_featured_message($node);

        // Featured Items Mosaic.
        _mass_content_org_page_migration_featured_items_mosaic($node);

        // About.
        _mass_content_org_page_migration_about($node);

        // Contact and Org Logo.
        _mass_content_org_page_migration_contact_logo($node);

        // About us.
        _mass_content_org_page_migration_who_we_serve($node);

        // Organization Grid.
        _mass_content_org_page_migration_organization_grid($node);

        // Board Members.
        _mass_content_org_page_migration_board($node);

        // Events.
        _mass_content_org_page_migration_events($node);

        // What would you like to do?
        _mass_content_org_page_migration_what_would_you_like_to_do($node);

        // News.
        _mass_content_org_page_migration_news($node);

        // Featured Topics.
        _mass_content_org_page_migration_featured_topics($node);

        // Locations.
        _mass_content_org_page_migration_locations($node);

        // Related Organizations.
        _mass_content_org_page_migration_related_orgs($node);

        break;

    }

    // Save the node.
    // Save without updating the last modified date. This requires a core patch
    // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
    $node->setSyncing(TRUE);
    $node->save();

    $sandbox['progress']++;
  }

  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Organization node data has migrated to Organization Sections.');
  }
}

/**
 * Set default value for the updated date field on events.
 */
function mass_content_deploy_event_updated_date(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'event');
  // Only update public meeting events.
  $query->condition('field_event_type_list', 'public_meeting');

  if (empty($sandbox)) {
    // Get a list of all nodes of type event.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $memory_cache = \Drupal::service('entity.memory_cache');

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();
    // Set the updated date for events.
    $timestamp = $node->getChangedTime();
    // Convert the timestamp to the proper date format including timezone.
    $date_time = DrupalDatetime::createFromTimestamp((int) $timestamp);
    $date_time->setTimezone(new \Datetimezone('EST'));
    $updated_date = \Drupal::service('date.formatter')->format($date_time->getTimestamp(), 'custom', 'Y-m-d\TH:i:s');
    // Set the value of the new field.
    $node->set('field_updated_date', $updated_date);
    // Save the node.
    // Save without updating the last modified date. This requires a core patch
    // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
    $node->setSyncing(TRUE);
    $node->save();

    $sandbox['progress']++;
  }

  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Event "Updated date" fields have been set.');
  }
}
