<?php

namespace Drupal\mass_views\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows to add Collections field value.
 *
 * @see https://www.drupal.org/docs/contributed-modules/views-bulk-operations-vbo/creating-a-new-action#s-2-action-class
 *
 * @Action(
 *   id = "mass_views_change_collections",
 *   label = @Translation("Add Collections"),
 *   type = "node"
 * )
 */
class ChangeCollections extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  use StringTranslationTrait;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Object constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $currentUser, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $currentUser;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    $config = $this->getConfiguration();
    $new_collection_id = $config['new_collection'];

    /** @var \Drupal\Node\NodeStorage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $vid = $node_storage->getLatestRevisionId($entity->id());
    $create_draft = $vid != $entity->getRevisionId();

    /** @var \Drupal\node\Entity\Node $entity */
    if (is_array($new_collection_id)) {
      if (!empty($entity->field_collections->getValue())) {
        foreach ($new_collection_id as $id) {
          $entity->field_collections->appendItem($id);
        }
      }
      else {
        $entity->field_collections = $new_collection_id;
      }
    }

    $entity->setNewRevision();
    $entity->setRevisionUserId($this->currentUser->id());
    $entity->setRevisionLogMessage('Revision created with "Add Collections" feature.');
    $entity->setRevisionCreationTime($this->time->getRequestTime());
    $entity->save();

    // Was the current version different from the latest version?
    if ($create_draft) {
      /** @var \Drupal\node\Entity\Node */
      $node_latest = $node_storage->loadRevision($vid);
      $node_latest->setNewRevision(TRUE);
      $node_latest->setRevisionUserId($this->currentUser->id());
      $node_latest->setRevisionLogMessage('Revision created with "Add Collections" feature.');
      $node_latest->setRevisionCreationTime($this->time->getRequestTime());
      if (is_array($new_collection_id)) {
        if (!empty($node_latest->field_collections->getValue())) {
          foreach ($new_collection_id as $id) {
            $node_latest->field_collections->appendItem($id);
          }
        }
        else {
          $node_latest->field_collections = $new_collection_id;
        }
      }
      $node_latest->save();
    }

    return $this->t('Updated collections for') . ' ' . $entity->label() . ' - ' . $entity->id();
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
    return AccessResult::allowed();
  }

  /**
   * Returns the entity bundles allowed for collections.
   */
  private function intersectTargetBundles() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $target_bundles = NULL;

    /** @var int[] */
    $list = $this->context['list'];
    foreach ($list as $item_id) {
      $node = $node_storage->load(array_reverse($item_id)[0]);
      if (!empty($node)) {
        /** @var \Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchyFieldItemList */
        $collections = $node->hasField('field_collections') ?? FALSE;
        $definition = $collections ? $node->field_collections->getFieldDefinition() : FALSE;
        $settings = $definition ? $definition->getSettings() : FALSE;
        $handler_settings = $settings ? $settings['handler_settings'] ?? [] : [];
        $target_bundles =
          is_array($target_bundles) ?
            \array_intersect($target_bundles, ($handler_settings['target_bundles'] ?? [])) :
            $handler_settings['target_bundles'];
      }
    }

    return $target_bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $target_bundles = $this->intersectTargetBundles();
    if (!empty($target_bundles)) {

      $vocabularies = Vocabulary::loadMultiple($target_bundles);

      $form['#list'] = $this->context['list'];

      $form['actions']['submit']['#value'] = $this->t('Add collections');

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
        '#attributes' => ['class' => ['field--widget-term-reference-tree']],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  private function getValueFromElement($element, FormStateInterface $form_state) {
    $items = _term_reference_tree_flatten($element, $form_state);
    $value = [];
    if ($element['#max_choices'] != 1) {
      foreach ($items as $child) {
        if (!empty($child['#value'])) {
          // If the element is leaves only and select parents is on,
          // then automatically add all the parents of each selected value.
          if (!empty($element['#select_parents']) && !empty($element['#leaves_only'])) {
            foreach ($child['#parent_values'] as $parent_tid) {
              if (!in_array([$element['#value_key'] => $parent_tid], $value)) {
                array_push($value, [$element['#value_key'] => $parent_tid]);
              }
            }
          }
          array_push($value, [$element['#value_key'] => $child['#value']]);
        }
      }
    }
    else {
      // If it's a tree of radio buttons, they all have the same value,
      // so we can just grab the value of the first one.
      if (count($items) > 0) {
        $child = reset($items);
        if (!empty($child['#value'])) {
          array_push($value, [$element['#value_key'] => $child['#value']]);
        }
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $element = $form['new_collection'];
    $value = $this->getValueFromElement($element, $form_state);
    if (!$form_state->isValidationComplete() && $element['#required'] && empty($value)) {
      $form_state->setError($element, t('%name field is required.', ['%name' => $element['#title']]));
    }
  }

  /**
   * Set form_state values based on the selected from the widget.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $element = $form['new_collection'];
    $value = $this->getValueFromElement($element, $form_state);
    $form_state->setValueForElement($element, $value);
    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $value) {
      $this->configuration[$key] = $value;
    }
  }

}
