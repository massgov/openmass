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
    return t('All Image caption fields migrated.');
  }
}

