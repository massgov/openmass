<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Provides a block for the intro text on the node add page.
 *
 * @Block(
 *   id = "release_notes_block",
 *   admin_label = @Translation("Release notes")
 * )
 */
class ReleaseNotesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Access and render the newest section of the Release notes node and render it.
    $nid = 365966;
    $field = 'field_info_details_sections';
    $node = Node::load($nid);
    $buildInfo = [];

    if ($node instanceof NodeInterface) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $referenceItem */
      $referenceItem = $node->get($field)->first();

      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityReference $entityReference */
      $entityReference = $referenceItem->get('entity');

      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $entityAdapter */
      $entityAdapter = $entityReference->getTarget();

      /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
      $referencedEntity = $entityAdapter->getValue();

      $renderedEntity = \Drupal::entityTypeManager()->getViewBuilder('paragraph')->view($referencedEntity);

      $buildInfo['details_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Latest release notes'),
        '#open' => TRUE,
      ];

      $buildInfo['details_section']['inside'] = [
        'field_item' => [
          '#type' => 'item',
          'content' => $renderedEntity,
        ],
        'link_item' => [
          '#title' => $this->t('See all release notes'),
          '#type' => 'link',
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $nid]),
          '#attributes' => [
            'class' => [
              'more-link',
            ],
          ],
        ],

      ];
    }

    return $buildInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'node:365966';
    return $cache_tags;
  }

}
