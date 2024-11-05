<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\mass_content\Entity\Bundle\node\NodeBundle;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "service_details"
 * )
 */
class ServiceDetails extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('node', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'service_details');
    $query->innerJoin('node_field_data', 'nfd', 'nfd.nid=n.nid AND nfd.vid=n.vid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    /** @var \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node */
    $node = Node::load($row->getSourceProperty('nid'));
    // @todo migrate rabbit hole ?
    $map_destination = [
      'title' => $node->getTitle(),
      'uid' => $node->getOwnerId(),
      'created' => $node->getCreatedTime(),
      'changed' => $node->getChangedTime(),
      'type' => 'info_details',
      'field_hide_table_of_contents' => TRUE,
      'langcode' => $node->language()->getId(),
      'status' => $node->isPublished(),
      'moderation_state' => $node->getModerationState()->getValue(),
      'search' => $node->getSearch()->getValue(),
      'search_nosnippet' => $node->getSearchNoSnippet()->getValue(),
      'field_contact' => $node->get('field_service_detail_contact')->getValue(),
      'field_intended_audience' => $node->get('field_intended_audience')->getValue(),
      'field_migrated_node_id' => $node->id(),
      'field_organizations' => $node->get('field_organizations')->getValue(),
      'field_primary_parent' => $node->get('field_primary_parent')->getValue(),
      'field_data_flag' => $node->get('field_data_flag')->getValue(),
      'field_data_format' => $node->get('field_data_format')->getValue(),
      'field_details_data_type' => $node->get('field_details_data_type')->getValue(),
      'field_data_topic' => $node->get('field_data_topic')->getValue(),
      'field_data_search_content' => $node->get('field_data_search_content')->getValue(),
      'field_data_resource_type' => $node->get('field_data_resource_type')->getValue(),
      'field_reusable_label' => $node->get('field_reusable_label')->getValue(),
      'field_short_desc' => $node->get('field_service_detail_lede')->getValue(),
      'field_info_details_related' => $node->get('field_service_detail_links_5')->getValue(),
      'field_info_detail_overview' => $node->get('field_service_detail_overview')->getValue(),
      'revision_timestamp' => $node->getRevisionCreationTime(),
      'revision_uid' => $node->getRevisionUserId(),
      'revision_log' => $this->t("Migrated from service details"),
    ];
    foreach ($map_destination as $key => $value) {
      $row->setDestinationProperty($key, $value);
    }

    $this->prepareRowSections($row, $node);

    // Map for use in process plugins.
    $map_source = [
      // @todo Possibly use 2 migrations for this.
      'field_english_version' => $node->get('field_english_version')->getString() === '0' ? NULL : $node->get('field_english_version')->getString(),
      'alias' => \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id()),
    ];
    foreach ($map_source as $key => $value) {
      $row->setSourceProperty($key, $value);
    }
    return parent::prepareRow($row);
  }

  private function prepareRowSections(Row $row, NodeBundle $node) {
    /** @var Paragraph[] $paragraphs */
    $paragraphs = $node->get('field_service_detail_sections')->referencedEntities();
    if (empty($paragraphs)) {
      return [];
    }

    $values['type'] = 'section_long_form';
    $list = [];
    foreach ($paragraphs as $delta => $paragraph) {
      $info_details_section = Paragraph::create($values);
      switch ($paragraph->getType()) {
        case 'section':
          $info_details_section->set('field_section_long_form_heading', mb_substr($paragraph->get('field_section_title')->getString(), 0, 96, 'UTF-8'));

          $info_details_section->set('field_hide_heading', FALSE);
          $body = $paragraph->get('field_section_body');
          if (!$body->isEmpty()) {
            $rich = Paragraph::create(['type' => 'rich_text']);
            $item = $body->first();
            $rich->field_body->value = $item->value;
            $rich->field_body->format = $item->format;
            $rich->save();
            $info_details_section->set('field_section_long_form_content', $rich);
            if (!$paragraph->get('field_section_downloads')->isEmpty() || !$paragraph->get('field_section_links')->isEmpty()) {
              $links_and_downloads = Paragraph::create(['type' => 'links_downloads']);
              $links_and_downloads->set('field_links_downloads_down', $paragraph->get('field_section_downloads')->getValue());
              $links_and_downloads->set('field_links_downloads_link', $paragraph->get('field_section_links')->getValue());
              $links_and_downloads->save();
              $info_details_section->set('field_section_long_form_addition', $links_and_downloads);
            }
          }
          break;

        case 'iframe':
          $info_details_section->set('field_section_long_form_heading', 'Iframe');
          $info_details_section->set('field_hide_heading', TRUE);
          // Re-use paragraph.
          $info_details_section->set('field_section_long_form_content', $paragraph);
          break;

        case 'video':
          $info_details_section->set('field_section_long_form_heading', 'Video');
          $info_details_section->set('field_hide_heading', TRUE);
          // Re-use paragraph.
          $info_details_section->set('field_section_long_form_content', $paragraph);
          break;

        default:
          throw new MigrateSkipRowException('Unknown para type: ' . $paragraph->getType(), TRUE);
      }
      $info_details_section->save();
      $list[] = $info_details_section;
    }
    $row->setDestinationProperty('field_info_details_sections', $list);
  }

}
