<?php

namespace Drupal\mass_hierarchy\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy\Form\HierarchyChildrenForm as EntityHierachyHierarchyChildrenForm;
use Drupal\entity_hierarchy\Storage\RecordCollectionCallable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for re-ordering children.
 */
class HierarchyChildrenForm extends EntityHierachyHierarchyChildrenForm {

  /**
   * NearestMicrositeLookup service.
   *
   * @var \Drupal\mass_microsites\NearestMicrositeLookup
   */
  protected $micrositeLookup;

  /**
   * Query builder factory service.
   *
   * @var \Drupal\entity_hierarchy\Storage\QueryBuilderFactory
   */
  protected $queryBuilderFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var self $instance */
    $instance = parent::create($container);
    $instance->micrositeLookup = $container->get('mass_microsites.nearest_microsite_lookup');
    $instance->queryBuilderFactory = $container->get('entity_hierarchy.query_builder_factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $cache = (new CacheableMetadata())->addCacheableDependency($this->entity);

    $form['#attached']['library'][] = 'entity_hierarchy/entity_hierarchy.nodetypeform';

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $this->parentCandidate->getCandidateFields($this->entity);
    if (!$fields) {
      throw new NotFoundHttpException();
    }
    $fieldName = $form_state->getValue('fieldname') ?: reset($fields);
    if (count($fields) === 1) {
      $form['fieldname'] = [
        '#type' => 'value',
        '#value' => $fieldName,
      ];
    }
    else {
      $form['select_field'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline'],
        ],
      ];
      $form['select_field']['fieldname'] = [
        '#type' => 'select',
        '#title' => $this->t('Field'),
        '#description' => $this->t('Field to reorder children in.'),
        '#options' => array_map(function ($field_name) {
          return $this->entity->getFieldDefinitions()[$field_name]->getLabel();
        }, $fields),
        '#default_value' => $fieldName,
      ];
      $form['select_field']['update'] = [
        '#type' => 'submit',
        '#value' => $this->t('Update'),
        '#submit' => ['::updateField'],
      ];
    }
    /** @var \Drupal\entity_hierarchy\Storage\QueryBuilder $queryBuilder */
    $queryBuilder = $this->queryBuilderFactory->get($fieldName, $this->entity->getEntityTypeId());

    // Get descendants with depth 2 (direct children and grandchildren).
    $children = $queryBuilder->findDescendants($this->entity, 2);

    // Filter for entities user has access to view.
    $children = $children->filter(RecordCollectionCallable::viewLabelAccessFilter(...));

    // Get base depth for indentation calculations.
    $baseDepth = $queryBuilder->findDepth($this->entity);

    $form['#attached']['library'][] = 'mass_hierarchy/hierarchy';
    $form['#attached']['library'][] = 'entity_hierarchy/entity_hierarchy.nodetypeform';
    $form['#attached']['drupalSettings']['mass_hierarchy_parent_bundle_info'] = mass_hierarchy_get_parent_bundle_info();

    $currentUser = \Drupal::currentUser();

    // Using a class we remove pointer events to disallow dragging
    // for any item in the hierarchy.
    if (!$currentUser->hasPermission('mass_hierarchy - move items in the hierarchy')) {
      $form['#attributes']['class'][] = 'mass_hierarchy_cant_drag';
    }

    // Using a class we remove pointer events to disallow dragging
    // topic pages if the user doesn't have the permission to change its parent.
    if (!$currentUser->hasPermission('mass_hierarchy - change topic page parent')) {
      $form['#attributes']['class'][] = 'mass_hierarchy_cant_drag_topic_page';
    }

    $form['children'] = [
      '#type' => 'table',
      '#header' => [
        t('Child'),
        t('Type'),
        t('Weight'),
        ['data' => t('Pageviews'), 'colspan' => 1],
        ['data' => t('Operations'), 'colspan' => 2],
      ],
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'child-parent',
          'subgroup' => 'child-parent',
          'source' => 'child-id',
          'hidden' => TRUE,
          'limit' => 100,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'child-weight',
        ],
      ],
      '#empty' => $this->t('There are no children to reorder'),
    ];

    $bundles = FALSE;

    // Collect IDs for pageview data.
    $ids = [];
    foreach ($children as $record) {
      $childEntity = $record->getEntity();
      if (!$childEntity || !$childEntity->isDefaultRevision()) {
        // Doesn't exist, is access hidden, or is not default revision.
        continue;
      }
      $ids[] = $record->getId();
    }

    if (!empty($ids)) {
      $pageviews = \Drupal::service('bigquery.storage')->getRecords($ids);
    }

    foreach ($children as $weight => $record) {
      /** @var \Drupal\node\Entity\Node $childEntity */
      $childEntity = $record->getEntity();

      if (!$childEntity || !$childEntity->isDefaultRevision()) {
        // Doesn't exist, is access hidden, or is not default revision.
        continue;
      }

      if (!$childEntity->isPublished()) {
        continue;
      }

      $child = $record->getId();

      $level = $record->getDepth();

      if ($level > 1) {
        continue;
      }

      // Convert RecordCollection to array for indexed access.
      $childrenArray = iterator_to_array($children);
      $nextElem = $childrenArray[$weight + 1] ?? FALSE;
      $inc = 1;
      while ($nextElem && !$nextElem->getEntity()) {
        $nextElem = $childrenArray[$weight + $inc++] ?? FALSE;
      }

      !$nextElem || ($nextElem->getDepth() <= $record->getDepth())
        ?: $form['children'][$child]['#attributes']['class'][] = 'hierarchy-row--parent';

      $form['children'][$child]['#attributes']['class'][] = 'hierarchy-row';
      $form['children'][$child]['#attributes']['class'][] = 'hierarchy-row--' . $childEntity->bundle();

      $form['children'][$child]['#attributes']['class'][] = 'draggable';
      $form['children'][$child]['#weight'] = $weight;

      $form['children'][$child]['title'] = [
        [
          '#theme' => 'indentation',
          '#size' => $record->getDepth() - $baseDepth - 1,
        ],
        $childEntity->toLink()->toRenderable(),
        [
          '#markup' => '
            <div class="hierarchy-row-controls">
              <div class="hierarchy-row-controls--expand"></div>
              <div class="hierarchy-row-controls--collapse"></div>
            </div>',
        ],
      ];

      if (!$bundles) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($childEntity->getEntityTypeId());
      }

      // Adding bundle machine name information.
      $form['children'][$child]['type']['#type'] = 'html_tag';
      $form['children'][$child]['type']['#tag'] = 'div';
      $form['children'][$child]['type']['#attributes']['data-bundle'] = $childEntity->bundle();
      $form['children'][$child]['type']['#value'] = $bundles[$childEntity->bundle()]['label'];

      $form['children'][$child]['weight'] = [
        '#type' => 'weight',
        '#delta' => 50,
        '#title' => t('Weight for @title', ['@title' => $childEntity->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $childEntity->{$fieldName}->weight,
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['child-weight']],
      ];

      $form['children'][$child]['id'] = [
        '#type' => 'hidden',
        '#value' => $record->getId(),
        '#attributes' => ['class' => ['child-id']],
      ];

      if (isset($pageviews[$child])) {
        $form['children'][$child]['pageviews']['#markup'] = \intval($pageviews[$child]['totalPageViews']);
      }
      else {
        $form['children'][$child]['pageviews']['#markup'] = 'n/a';
      }

      // Find parent entity ID.
      $parentEntity = $queryBuilder->findParent($childEntity);
      $parentId = $parentEntity ? $parentEntity->id() : NULL;

      $form['children'][$child]['parent'] = [
        '#type' => 'hidden',
        '#default_value' => $parentId,
        '#attributes' => ['class' => ['child-parent']],
      ];

      // Operations column.
      $form['children'][$child]['operations'] = [
        '#type' => 'operations',
        '#links' => [],
      ];

      if ($childEntity->access('update') && $childEntity->hasLinkTemplate('edit-form')) {
        $form['children'][$child]['operations']['#links']['edit'] = [
          'title' => t('Edit'),
          'url' => $childEntity->toUrl('edit-form'),
        ];
      }
      if ($childEntity->access('delete') && $childEntity->hasLinkTemplate('delete-form')) {
        $form['children'][$child]['operations']['#links']['delete'] = [
          'title' => t('Delete'),
          'url' => $childEntity->toUrl('delete-form'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {

    $currentUser = \Drupal::currentUser();

    // Using a class we remove pointer events to disallow dragging
    // for any item in the hierarchy.
    if (!$currentUser->hasPermission('mass_hierarchy - move items in the hierarchy')) {
      return [];
    }

    $actions = parent::actions($form, $form_state);
    unset($actions['add_child']);
    $actions['submit']['#value'] = $this->t('Update children');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $children = $form_state->getValue('children');
    $fieldName = $form_state->getValue('fieldname');
    $batch = [
      'title' => new TranslatableMarkup('Rebuilding tree ...'),
      'operations' => [],
      'finished' => [static::class, 'finished'],
    ];
    $microsite = $this->micrositeLookup->getNearestMicrosite($this->entity);
    foreach ($children as $child) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($this->entity->getEntityTypeId())
        ->load($child['id']);
      $batch['operations'][] = [
        [static::class, 'rebuildTree'],
        [$fieldName, $entity, $child['parent'], $child['weight'], $microsite],
      ];
    }
    batch_set($batch);
  }

  /**
   * Batch callback to rebuild the tree.
   */
  public static function rebuildTree($fieldName, ContentEntityInterface $entity, $parent, $weight, $microsite = NULL) {
    $is_same_weight = $entity->{$fieldName}->weight == $weight;
    $is_same_parent = $entity->{$fieldName}->target_id == $parent;

    if ($microsite) {
      if ($is_same_weight && $is_same_parent) {
        return;
      }
    }
    else {
      if ($is_same_parent) {
        return;
      }
    }

    /** @var \Drupal\Node\NodeStorage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $vid = $node_storage->getLatestRevisionId($entity->id());

    /** @var \Drupal\node\Entity\Node $entity */
    $entity->setNewRevision(TRUE);
    $entity->setRevisionUserId(\Drupal::currentUser()->id());
    $entity->setRevisionLogMessage('Revision created with "Hierarchy" feature.');
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $create_draft = $vid != $entity->getRevisionId();

    $entity->{$fieldName}->target_id = $parent;
    $entity->{$fieldName}->weight = $weight;
    $entity->save();

    // Is the current version different from the latest version?
    if ($create_draft) {
      /** @var \Drupal\node\Entity\Node */
      $node_latest = $node_storage->loadRevision($vid);
      $node_latest->setNewRevision(TRUE);
      $node_latest->setRevisionUserId(\Drupal::currentUser()->id());
      $node_latest->setRevisionLogMessage('Revision created with "Hierarchy" feature.');
      $node_latest->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node_latest->{$fieldName}->target_id = $parent;
      $node_latest->{$fieldName}->weight = $weight;
      $node_latest->save();
    }
  }

  /**
   * Batch finished callback.
   */
  public static function finished() {
    \Drupal::messenger()->addMessage(new TranslatableMarkup('Updated parent - child relationships.'));
  }

}
