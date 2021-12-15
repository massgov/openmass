<?php

namespace Drupal\mass_views\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Allows to change Collections field value.
 *
 * @see https://www.drupal.org/docs/contributed-modules/views-bulk-operations-vbo/creating-a-new-action#s-2-action-class
 *
 * @Action(
 *   id = "mass_views_change_collections",
 *   label = @Translation("Change Collections"),
 *   type = ""
 * )
 */
class ChangeCollections extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $config = $this->getConfiguration();
    $new_collection_id = $config['new_collection'];

    /** @var \Drupal\Node\NodeStorage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $vid = $node_storage->getLatestRevisionId($entity->id());
    $create_draft = $vid != $entity->getRevisionId();

    /** @var \Drupal\node\Entity\Node $entity */
    $entity->field_primary_parent = $new_collection_id;
    $entity->setNewRevision(TRUE);
    $entity->setRevisionUserId(\Drupal::currentUser()->id());
    $entity->setRevisionLogMessage('Revision created with "Move Children" feature.');
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->save();

    // Was the current version different from the latest version?
    if ($create_draft) {
      /** @var \Drupal\node\Entity\Node */
      $node_latest = $node_storage->loadRevision($vid);
      $node_latest->setNewRevision(TRUE);
      $node_latest->setRevisionUserId(\Drupal::currentUser()->id());
      $node_latest->setRevisionLogMessage('Revision created with "Move Children" feature.');
      $node_latest->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node_latest->field_primary_parent = $new_collection_id;
      $node_latest->save();
    }

    return $this->t('Updated parent for') . ' ' . $entity->label() . ' - ' . $entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

  /**
   * Returns the entity bundles allowed for collections.
   */
  private function intersectTargetBundles() {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $target_bundles = NULL;

    /** @var int[] */
    $list = $this->context['list'];
    foreach ($list as $item_id) {
      $node = $node_storage->load($item_id[0]);
      /** @var \Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchyFieldItemList */
      $collections = $node->field_collections ?? FALSE;
      $definition = $collections ? $collections->getFieldDefinition() : FALSE;
      $settings = $definition ? $definition->getSettings() : FALSE;
      $handler_settings = $settings ? $settings['handler_settings'] ?? [] : [];
      $target_bundles =
        is_array($target_bundles) ?
          \array_intersect($target_bundles, ($handler_settings['target_bundles'] ?? [])) :
          $handler_settings['target_bundles'];
    }

    return $target_bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $target_bundles = $this->intersectTargetBundles();
    $vocabularies = Vocabulary::loadMultiple($target_bundles);

    $form['#list'] = $this->context['list'];

    $form['actions']['submit']['#value'] = $this->t('Change collections');

    $form['new_collection'] = [
      '#type' => 'checkbox_tree',
      '#vocabularies' => $vocabularies,
      '#max_choices' => -1,
      '#leaves_only' => FALSE,
      '#select_parents' => TRUE,
      '#cascading_selection' => 0,
      '#value_key' => 'target_id',
      '#max_depth' => 0,
      '#start_minimized' => TRUE,
      '#title' => $this->t('New Collection'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['field--widget-term-reference-tree']]
    ];
    return $form;
  }

}
