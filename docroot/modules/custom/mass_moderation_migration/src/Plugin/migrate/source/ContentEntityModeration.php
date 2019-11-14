<?php

namespace Drupal\mass_moderation_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Loads certain fields from all content entities of a specific type.
 *
 * @MigrateSource(
 *   id = "content_entity_moderation",
 *   deriver = "\Drupal\mass_moderation_migration\Plugin\migrate\source\ContentEntityDeriver",
 * )
 */
class ContentEntityModeration extends SqlBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $select = $this->select('node_revision', 'nr');
    $select->addField('nr', 'nid');
    $select->addField('nr', 'vid');
    $select->addField('nr', 'revision_timestamp');
    $select->join('node_field_revision', 'nfr', 'nr.vid=nfr.vid');
    $select->addField('nfr', 'moderation_state');
    $select->join('node', 'n', 'nr.nid=n.nid');
    $select->condition('n.type', 'legacy_redirects', '!=');
    $select->orderBy('nr.revision_timestamp', 'ASC');
    return $select;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('Node ID'),
      'revision' => $this->t('Entity Revision'),
      'moderation_state' => $this->t('Moderation State'),
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
        'alias' => 'nr',
      ],
      'vid' => [
        'type' => 'integer',
        'alias' => 'nr',
      ],
    ];
  }

}
