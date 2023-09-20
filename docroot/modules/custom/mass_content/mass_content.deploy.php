<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Content.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate iframe paragraph fields.
 */
function mass_content_deploy_iframe_fields(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE)
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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
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

  $tids = $query->sort('tid')->accessCheck(FALSE)->execute();

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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
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

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
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

/**
 * Migrate Secondary Header field to Related Information field on Search.
 */
function mass_content_deploy_search_related_info(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $query = \Drupal::entityQuery('paragraph');
  $query->condition('type', 'collection_search');
  $query->condition('field_secondary_heading', '', '<>');
  $query->accessCheck(FALSE);

  if (empty($sandbox)) {
    // Initialize other variables.
    $sandbox['current'] = 0;
    $sandbox['progress'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->accessCheck(FALSE)->execute();
  }

  $batch_size = 50;

  $pids = $query->condition('id', $sandbox['current'], '>')
    ->sort('id')
    ->range(0, $batch_size)
    ->execute();

  $storage_handler = \Drupal::entityTypeManager()->getStorage('paragraph');
  $entities = $storage_handler->loadMultiple($pids);
  if (!empty($entities)) {
    foreach ($entities as $entity) {
      $sandbox['current'] = $entity->id();
      $secondary_heading = '<h3>' . $entity->field_secondary_heading->value . '</h3>';

      $entity = $storage_handler->load($entity->id());
      $see_all_items_link_rendered = '';
      if ($url_name = $entity->field_collection->referencedEntities()[0]->field_url_name->value) {
        $see_all_items_link_url = Url::fromUserInput('/collections/' . $url_name);
        $see_all_items_link_text = $entity->field_see_all_items_link_text->value;
        $see_all_items_link_rendered = '<p>' . Link::fromTextAndUrl($see_all_items_link_text, $see_all_items_link_url)->toString() . '</p>';
      }

      $entity->set('field_search_related_info', [
        'value' => $secondary_heading . $see_all_items_link_rendered,
        'format' => 'basic_html',
      ]);

      $entity->set('field_search_type', 'collection');

      $entity->save();
      $sandbox['progress']++;
    }
    Drupal::logger('Mass Content')->info('Migrated !count collection_search paragraphs from !max.', ['!count' => $sandbox['progress'], '!max' => $sandbox['max']]);
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('Migrated collection_search paragraphs.');
  }
}

/**
 * Migrate "Organization Communications page" data for feedback.
 */
function mass_content_deploy_feedback_com(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
  $query->condition('type', 'org_page');

  if (empty($sandbox)) {
    // Get a list of all nodes of type event.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();
    try {
      mass_content_set_feedback_fields($node);
    }
    catch (\Exception $e) {
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }
    if (!$node->isLatestRevision()) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $query = $storage->getQuery()->accessCheck(FALSE);
      $query->condition('nid', $node->id());
      $query->latestRevision();
      $rids = $query->execute();
      foreach ($rids as $rid) {
        $latest_revision = $storage->loadRevision($rid);
        if (isset($latest_revision)) {
          try {
            mass_content_set_feedback_fields($latest_revision);
          }
          catch (\Exception $e) {
            \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
          }
        }
      }
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('Migrated "Organization Communications page" data for feedback. Processed @total items.', ['@total' => $sandbox['progress']]);
  }
  return "Processed {$sandbox['progress']} items.";
}

/**
 * Set feedback fields and save entity.
 */
function mass_content_set_feedback_fields($node) {
  $uri = $node->field_feedback_com_link->uri ?? 'entity:node/' . $node->id();
  $contact = $node->field_org_sentence_phrasing->value ?? $node->label();
  $title = sprintf('contact the %s', trim(preg_replace('#^the #i', '', $contact)));
  $node->set('field_feedback_com_link', [
    'uri' => $uri,
    'title' => $title,
  ]);

  if ($node->field_constituent_communication->value == 'link' || $node->field_constituent_communication->value == 'contact') {
    $node->set('field_org_always_show_help_page', TRUE);
  }
  // Save the node.
  // Save without updating the last modified date. This requires a core patch
  // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
  $node->setSyncing(TRUE);
  $node->save();
}

/**
 * Migrate "Related content" paragraph data between fields.
 */
function mass_content_deploy_related_content(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('paragraph')->accessCheck(FALSE);
  $query->condition('type', 'related_content');

  if (empty($sandbox)) {
    // Get a list of all nodes of type event.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->accessCheck(FALSE)->execute();
  }

  $batch_size = 50;

  $pids = $query->condition('id', $sandbox['current'], '>')
    ->sort('id')
    ->range(0, $batch_size)
    ->execute();

  $storage = \Drupal::entityTypeManager()->getStorage('paragraph');

  $paragraphs = $storage->loadMultiple($pids);

  foreach ($paragraphs as $paragraph) {
    $sandbox['current'] = $paragraph->id();
    if (!$paragraph->field_related_content->isEmpty()) {
      $new_values = [];
      $old_values = $paragraph->field_related_content->getValue();
      foreach ($old_values as $index => $old_value) {
        $new_values[$index] = 'entity:node/' . $old_value['target_id'];

      }
      $paragraph->set('field_related_content_link', $new_values);
      $paragraph->save();
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('Migrated "Related content" paragraph data between fields. Processed @total items.', ['@total' => $sandbox['progress']]);
  }
  return "Processed {$sandbox['progress']} items.";
}

/**
 * Set "Section style" default value for the org sections.
 */
function mass_content_deploy_org_section_style(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('paragraph')->accessCheck(FALSE);
  $query->condition('type', 'org_section_long_form');

  if (empty($sandbox)) {
    // Get a list of all nodes of type event.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->accessCheck(FALSE)->execute();
  }

  $batch_size = 50;

  $pids = $query->condition('id', $sandbox['current'], '>')
    ->sort('id')
    ->range(0, $batch_size)
    ->execute();

  $storage = \Drupal::entityTypeManager()->getStorage('paragraph');

  $paragraphs = $storage->loadMultiple($pids);

  foreach ($paragraphs as $paragraph) {
    $sandbox['current'] = $paragraph->id();
    if (!Helper::isParagraphOrphan($paragraph)) {
      $paragraph->set('field_section_style', 'simple');
      $paragraph->save();
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('Section style default value set for the  org sections. Processed @total items.', ['@total' => $sandbox['progress']]);
  }
  return "Processed {$sandbox['progress']} items.";
}

/**
 * Migrate "What would you like to do" to be flexible links.
 */
function mass_content_deploy_org_wwyltd_flexible_links(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('paragraph')->accessCheck(FALSE);
  $query->condition('type', 'what_would_you_like_to_do');

  if (empty($sandbox)) {
    // Get a list of all nodes of type event.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->accessCheck(FALSE)->execute();
  }

  $batch_size = 50;

  $pids = $query->condition('id', $sandbox['current'], '>')
    ->sort('id')
    ->range(0, $batch_size)
    ->execute();

  $storage = \Drupal::entityTypeManager()->getStorage('paragraph');

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  $memory_cache = \Drupal::service('entity.memory_cache');

  $paragraphs = $storage->loadMultiple($pids);
  foreach ($paragraphs as $paragraph) {
    $sandbox['current'] = $paragraph->id();
    if ($paragraph instanceof Paragraph) {
      if (!Helper::isParagraphOrphan($paragraph)) {
        if ($parent = $paragraph->getParentEntity()) {
          if ($parent instanceof Paragraph && $parent->bundle() == 'org_section_long_form') {
            if ($parent->getParentEntity() instanceof Node) {
              $node = $parent->getParentEntity();
              try {
                mass_content_org_wwyltd_flexible_links_helper($node, $parent, $paragraph);
              }
              catch (\Exception $e) {
                \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
              }
              if (!$node->isLatestRevision()) {
                $storage = \Drupal::entityTypeManager()->getStorage('node');
                $query = $storage->getQuery()->accessCheck(FALSE);
                $query->condition('nid', $node->id());
                $query->latestRevision();
                $rids = $query->execute();
                foreach ($rids as $rid) {
                  $latest_revision = $storage->loadRevision($rid);
                  if (isset($latest_revision)) {
                    try {
                      mass_content_org_wwyltd_flexible_links_helper($latest_revision, $parent, $paragraph);
                    }
                    catch (\Exception $e) {
                      \Drupal::state()
                        ->set('entity_hierarchy_disable_writes', FALSE);
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    $sandbox['progress']++;
  }

  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('Migrate "What would you like to do" to flexible links. Processed @total items.', ['@total' => $sandbox['progress']]);
  }
  return "Processed {$sandbox['progress']} items.";
}

/**
 * Helper function to call during migration.
 */
function mass_content_org_wwyltd_flexible_links_helper($node, $parent, $paragraph) {
  $node_field_name = $parent->parent_field_name->value;
  $parent_field_name = $paragraph->parent_field_name->value;
  if ($parent = $paragraph->getParentEntity()) {
    $content_items = $parent->get($parent_field_name)->getValue();
    if ($parent->get($parent_field_name)->count() > 1) {
      foreach ($content_items as $i => $content_item) {
        if ($content_item['target_id'] == $paragraph->id() && $content_item['target_revision_id'] == $paragraph->getRevisionId()) {
          $content_index = $i;
          break;
        }
      }
    }
  }

  $items = $node->get($node_field_name)->getValue();
  foreach ($items as $index => $item) {
    if ($item['target_id'] == $parent->id() && $item['target_revision_id'] == $parent->getRevisionId()) {
      $section_index = $index;
      break;
    }
  }

  $flexible_link_groups = [];
  if (!$paragraph->get('field_wwyltd_top_s_links')->isEmpty()) {
    $top_links = $paragraph->get('field_wwyltd_top_s_links')
      ->getValue();

    $link_group_links = [];
    foreach ($top_links as $link) {
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
    $flexible_link_group->set('field_link_group', $link_group_links);
    $flexible_link_group->save();
    $flexible_link_groups[] = $flexible_link_group;
  }

  if (!$paragraph->get('field_wwyltd_more_services')->isEmpty()) {
    $more_paragraphs = $paragraph->get('field_wwyltd_more_services')
      ->referencedEntities();

    foreach ($more_paragraphs as $more_paragraph) {
      if (!$more_paragraph->get('field_links_documents')->isEmpty()) {
        $link_group_links = $more_paragraph->get('field_links_documents')
          ->referencedEntities();

        // Create a new flexible_link_group paragraph.
        $flexible_link_group = Paragraph::create([
          'type' => 'flexible_link_group',
        ]);

        $flexible_link_group->set('field_featured', 0);
        $flexible_link_group->set('field_display_type', 'links');
        $flexible_link_group->set('field_group_expanded', $more_paragraph->get('field_group_expanded')->value);
        if (!$more_paragraph->get('field_group_expanded')->isEmpty()) {
          $flexible_link_group->set('field_group_expanded', $more_paragraph->get('field_group_expanded')->value);
        }
        else {
          $flexible_link_group->set('field_group_expanded', 1);
        }
        if (!$more_paragraph->get('field_section_title')->isEmpty()) {
          $title = $more_paragraph->get('field_section_title')->value;
          $flexible_link_group->set('field_flexible_link_group_title', $title);
        }
        $flexible_link_group->set('field_link_group', $link_group_links);
        $flexible_link_group->save();
        $flexible_link_groups[] = $flexible_link_group;
      }
    }
  }

  if ($flexible_link_groups) {
    $new_org_section_long_form_paragraph = Paragraph::create([
      'type' => 'org_section_long_form',
    ]);
    if (isset($content_index)) {
      $result = [];
      foreach ($content_items as $index => $content_item) {
        if ($index == $content_index) {
          foreach ($flexible_link_groups as $flexible_link_group) {
            $result[] = $flexible_link_group;
          }
        }
        else {
          $result[] = $content_item;
        }
      }
      $new_org_section_long_form_paragraph->set('field_section_long_form_content', $result);
    }
    else {
      $new_org_section_long_form_paragraph->set('field_section_long_form_content', $flexible_link_groups);
    }
    if (!$paragraph->get('field_wwyltd_heading')->isEmpty()) {
      $heading = $paragraph->get('field_wwyltd_heading')->value;
      $new_org_section_long_form_paragraph->set('field_section_long_form_heading', $heading);
    }
    $new_org_section_long_form_paragraph->set('field_section_style', 'enhanced');
    $new_org_section_long_form_paragraph->set('field_hide_heading', 0);

    // Save the new paragraph.
    $new_org_section_long_form_paragraph->save();
    // Create a value array for the new section paragraph.
    $new_org_section_long_form_paragraph_value = [
      'target_id' => $new_org_section_long_form_paragraph->id(),
      'target_revision_id' => $new_org_section_long_form_paragraph->getRevisionId(),
    ];
    if (isset($section_index)) {
      $items[$section_index] = $new_org_section_long_form_paragraph_value;
    }
    else {
      $items[] = $new_org_section_long_form_paragraph_value;
    }

    $node->set('field_organization_sections', $items);
    // Save the node.
    // Save without updating the last modified date. This requires a core patch
    // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
    $node->setSyncing(TRUE);
    $node->revision_log = "Bulk automated change of ‘what would you like to do’ component to ‘flexible link group’";
    $node->setRevisionCreationTime(\Drupal::time()->getCurrentTime());
    $node->save();
    $paragraph->delete();
  }
}

/**
 * Populate data for the org_page navigation.
 */
function mass_content_deploy_org_z_page_navigation_migration(&$sandbox) {
  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
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

  // Turn off entity_hierarchy writes while processing the item.
  \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();

    try {
      mass_content_org_node_navigation_helper($node);
    }
    catch (\Exception $e) {
      \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    }
    if (!$node->isLatestRevision()) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $query = $storage->getQuery()->accessCheck(FALSE);
      $query->condition('nid', $node->id());
      $query->latestRevision();
      $rids = $query->execute();
      foreach ($rids as $rid) {
        $latest_revision = $storage->loadRevision($rid);
        if (isset($latest_revision)) {
          try {
            mass_content_org_node_navigation_helper($latest_revision);
          }
          catch (\Exception $e) {
            \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
          }
        }
      }
    }

    $sandbox['progress']++;
  }
  $memory_cache->deleteAll();

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    // Turn on entity_hierarchy writes after processing the item.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    return t('New Org navigation data has been populated');
  }
}

/**
 * Helper function to populate org navigation.
 */
function mass_content_org_node_navigation_helper($node) {
  $changed = FALSE;
  if ($sections = $node->get('field_organization_sections')->referencedEntities()) {
    $has_contact = FALSE;
    $has_news = FALSE;
    $has_events = FALSE;
    $has_locations = FALSE;
    $has_wws = FALSE;
    $has_topics = FALSE;
    $has_wwylt = FALSE;
    $has_featured = FALSE;
    $has_about = FALSE;
    $has_most_requested = FALSE;
    $has_resources = FALSE;
    $has_our = FALSE;
    $has_links = FALSE;
    $has_meeting = FALSE;
    $has_webinar = FALSE;
    $has_services = FALSE;
    $has_mission = FALSE;
    $has_information = FALSE;
    $has_learn_more = FALSE;
    $has_visit = FALSE;
    $has_advisories = FALSE;
    $has_hours = FALSE;
    $has_priorities = FALSE;
    $has_data = FALSE;
    $has_initiatives = FALSE;
    foreach ($sections as $section) {
      if (!empty($section->field_section_long_form_heading->value) && $section->field_hide_heading->value != 1) {
        $heading = strtolower($section->field_section_long_form_heading->value);
        $heading_upper = ucfirst($heading);
        if (strlen($heading) <= 12) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', $heading_upper);
          $changed = TRUE;
          $section->save();
        }
        if (str_contains($heading, 'contact') && !$has_contact) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Contact us');
          $has_contact = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'news') && !$has_news) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'News');
          $has_news = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'events') && !$has_events) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Events');
          $has_events = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'location') && !$has_locations) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Locations');
          $has_locations = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'who we serve') && !$has_wws) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Who we serve');
          $has_wws = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'topics') && !$has_topics) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Topics');
          $has_topics = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'what would you like to') && !$has_wwylt) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'I want to…');
          $has_wwylt = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'feature') && !$has_featured) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Featured');
          $has_featured = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if ((str_contains($heading, 'about the') || str_contains($heading, 'about us')) && !$has_about) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'About');
          $has_about = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'most requested') && !$has_most_requested) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Most requested');
          $has_most_requested = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'our ') && !$has_our) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', $heading_upper);
          $has_our = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if ((str_contains($heading, 'quick links') || str_contains($heading, 'important links') || str_contains($heading, 'top links') || str_contains($heading, 'helpful links') || str_contains($heading, 'popular links'))&& !$has_links) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', $heading_upper);
          $has_links = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'upcoming public meetings/hearings') && !$has_meeting) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Meetings / Hearings');
          $has_meeting = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'upcoming webinars and meetings') && !$has_webinar) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Webinars / Meetings');
          $has_webinar = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'services') && !$has_services) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Services');
          $has_services = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'hours') && !$has_hours) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Hours');
          $has_hours = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'mission') && !$has_mission) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Mission');
          $has_mission = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'resources') && !$has_resources) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Resources');
          $has_resources = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'information') && !$has_information) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Information');
          $has_information = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'learn more') && !$has_learn_more) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Learn more');
          $has_learn_more = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'visit') && !$has_visit) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Visit');
          $has_visit = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'advisories') && !$has_advisories) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Advisories');
          $has_advisories = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'by the numbers') && !$has_data) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Data');
          $has_data = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'initiatives') && !$has_initiatives) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Initiatives');
          $has_initiatives = TRUE;
          $changed = TRUE;
          $section->save();
          continue;
        }
        if (str_contains($heading, 'priorities') && !$has_priorities) {
          $section->set('field_hide_in_org_navigation', 0);
          $section->set('field_org_navigation_jump_link_t', 'Priorities');
          $has_priorities = TRUE;
          $changed = TRUE;
          $section->save();
        }
      }
    }
  }
  if ($changed) {
    // Save the node.
    // Save without updating the last modified date. This requires a core patch
    // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
    $node->setSyncing(TRUE);
    $node->save();
  }
}
