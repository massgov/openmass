<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Content.
 */

use Drupal\paragraphs\Entity\Paragraph;

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

  $node_storage = \Drupal::entityManager()->getStorage('node');
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
function mass_content_deploy_header_media_images_followup(&$sandbox, $status = 1) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')
    ->condition('status', $status)
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
 * Migrate unpublished Header Media image wrapping fields.
 */
function mass_content_deploy_header_media_images_unpublished(&$sandbox) {
  mass_content_deploy_header_media_images_followup($sandbox, 0);
}
