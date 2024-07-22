<?php

namespace Drupal\mass_entity_usage\Controller;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\mass_entity_usage\MassEntityUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for our pages.
 */
class ListUsageController extends ControllerBase {

  /**
   * Number of items per page to use when nothing was configured.
   */
  const ITEMS_PER_PAGE_DEFAULT = 25;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The EntityUsage service.
   *
   * @var \Drupal\mass_entity_usage\MassEntityUsageInterface
   */
  protected $entityUsage;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * All source rows for this target entity.
   *
   * @var array
   */
  protected $allRows;

  /**
   * The Entity Usage settings config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $entityUsageConfig;

  /**
   * The number of records per page this controller should output.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * LocalTaskUsageController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\mass_entity_usage\MassEntityUsageInterface $entity_usage
   *   The EntityUsage service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, MassEntityUsageInterface $entity_usage, ConfigFactoryInterface $config_factory, PagerManagerInterface $pager_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityUsage = $entity_usage;
    $this->entityUsageConfig = $config_factory->get('entity_usage.settings');
    $this->itemsPerPage = $this->entityUsageConfig->get('usage_controller_items_per_page') ?: self::ITEMS_PER_PAGE_DEFAULT;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('mass_entity_usage.usage'),
      $container->get('config.factory'),
      $container->get('pager.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function listUsagePageSubQuery($entity_type, $entity_id) {
    $build = [];
    // Link needed for the caption.
    $help_url = Url::fromUri('https://massgovdigital.gitbook.io/knowledge-base/content-improvement-tools/pages-linking-here');
    $help_text = Link::fromTextAndUrl('Learn how to use Linking Pages.', $help_url)->toString();
    // Table headers.
    $header = [
      $this->t('Entity'),
      $this->t('Type'),
      $this->t('Language'),
      $this->t('Field name'),
      $this->t('Status'),
      $this->t('Used in'),
    ];

    // Result table.
    $build['results'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#caption' => $this->t('The list below shows pages that include a link to this page in structured and rich text fields. @help_text', ['@help_text' => $help_text]),
      '#empty' => $this->t('No pages link here.'),
    ];

    $all_rows = $this->getRows($entity_type, $entity_id);
    $total = count($all_rows);
    if (!$total) {
      return $build;
    }
    $pager = $this->pagerManager->createPager($total, $this->itemsPerPage);
    $page = $pager->getCurrentPage();
    $offset = $page * $this->itemsPerPage;
    $page_rows = array_slice($all_rows, $offset, $this->itemsPerPage);

    $build['results']['#prefix'] = $this->t($total . ' total records.');
    $build['results']['#rows'] = $page_rows;

    $build['pager'] = [
      '#type' => 'pager',
      '#route_name' => '<current>',
    ];

    return $build;
  }

  /**
   * Retrieve all usage rows for this target entity.
   *
   * @param string $entity_type
   *   The type of the target entity.
   * @param int|string $entity_id
   *   The ID of the target entity.
   *
   * @return array
   *   An indexed array of rows that should be displayed as sources for this
   *   target entity.
   */
  protected function getRows($entity_type, $entity_id) {
    if (!empty($this->allRows)) {
      return $this->allRows;
      // @todo Cache this based on the target entity, invalidating the cached
      // results every time records are added/removed to the same target entity.
    }
    $rows = [];
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      return $rows;
    }
    $entity_types = $this->entityTypeManager->getDefinitions();
    $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    $all_usages = $this->entityUsage->listSources($entity);
    foreach ($all_usages as $source_type => $ids) {
      $type_storage = $this->entityTypeManager->getStorage($source_type);
      foreach ($ids as $source_id => $records) {
        // We will show a single row per source entity. If the target is not
        // referenced on its default revision on the default language, we will
        // just show indicate that in a specific column.
        $source_entity = $type_storage->load($source_id);
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($source_type, $source_entity->bundle());
        if ($source_entity instanceof RevisionableInterface) {
          $default_revision_id = $source_entity->getRevisionId();
          $default_langcode = $source_entity->language()->getId();
          $used_in_default = FALSE;
          $default_key = 0;
          foreach ($records as $key => $record) {
            if ($record['source_vid'] == $default_revision_id && $record['source_langcode'] == $default_langcode) {
              $default_key = $key;
              $used_in_default = TRUE;
              break;
            }
          }
          $used_in_text = $used_in_default ? $this->t('Default revision') : $this->t('Translations or previous revisions');
        }
        $link = $this->getSourceEntityLink($source_entity);
        // If the label is empty it means this usage shouldn't be shown
        // on the UI, just skip this row.
        if (empty($link)) {
          continue;
        }

        $published = $this->getSourceEntityStatus($source_entity);
        $field_label = isset($field_definitions[$records[$default_key]['field_name']]) ? $field_definitions[$records[$default_key]['field_name']]->getLabel() : $this->t('Unknown');
        $rows[] = [
          $link,
          $entity_types[$source_type]->getLabel(),
          $languages[$default_langcode]->getName(),
          $field_label,
          $published,
          $used_in_text,
        ];
      }
    }

    $this->allRows = $rows;
    return $this->allRows;
  }

  /**
   * Retrieve the source entity's status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return string
   *   The entity's status.
   */
  protected function getSourceEntityStatus(EntityInterface $source_entity) {
    // Treat paragraph entities in a special manner. Paragraph entities
    // should get their host (parent) entity's status.
    if ($source_entity->getEntityTypeId() == 'paragraph') {
      /** @var \Drupal\paragraphs\ParagraphInterface $source_entity */
      $parent = $source_entity->getParentEntity();
      if (!empty($parent)) {
        return $this->getSourceEntityStatus($parent);
      }
    }

    if (isset($source_entity->status)) {
      $published = !empty($source_entity->status->value) ? $this->t('Published') : $this->t('Unpublished');
    }
    else {
      $published = '';
    }

    return $published;
  }

  /**
   * Retrieve a link to the source entity.
   *
   * Note that some entities are special-cased, since they don't have canonical
   * template and aren't expected to be re-usable. For example, if the entity
   * passed in is a paragraph or a block content, the link we produce will point
   * to this entity's parent (host) entity instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   * @param string|null $text
   *   (optional) The link text for the anchor tag as a translated string.
   *   If NULL, it will use the entity's label. Defaults to NULL.
   *
   * @return \Drupal\Core\Link|string|false
   *   A link to the entity, or its non-linked label, in case it was impossible
   *   to correctly build a link. Will return FALSE if this item should not be
   *   shown on the UI (for example when dealing with an orphan paragraph).
   */
  protected function getSourceEntityLink(EntityInterface $source_entity, $text = NULL) {
    // Note that $paragraph_entity->label() will return a string of type:
    // "{parent label} > {parent field}", which is actually OK for us.
    $entity_label = $source_entity->access('view label') ? $source_entity->label() : $this->t('- Restricted access -');

    $rel = NULL;
    if ($source_entity->hasLinkTemplate('revision')) {
      $rel = 'revision';
    }
    elseif ($source_entity->hasLinkTemplate('canonical')) {
      $rel = 'canonical';
    }

    // Block content likely used in Layout Builder inline blocks.
    if ($source_entity instanceof BlockContentInterface && !$source_entity->isReusable()) {
      $rel = NULL;
    }

    $link_text = $text ?: $entity_label;
    if ($rel) {
      // Prevent 404s by exposing the text unlinked if the user has no access
      // to view the entity.
      return $source_entity->access('view') ? $source_entity->toLink($link_text, $rel) : $link_text;
    }

    // Treat paragraph entities in a special manner. Normal paragraph entities
    // only exist in the context of their host (parent) entity. For this reason
    // we will use the link to the parent's entity label instead.
    /** @var \Drupal\paragraphs\ParagraphInterface $source_entity */
    if ($source_entity->getEntityTypeId() == 'paragraph') {
      $parent = $source_entity->getParentEntity();
      if ($parent) {
        return $this->getSourceEntityLink($parent, $link_text);
      }
    }
    // Treat block_content entities in a special manner. Block content
    // relationships are stored as serialized data on the host entity. This
    // makes it difficult to query parent data. Instead we look up relationship
    // data which may exist in entity_usage tables. This requires site builders
    // to set up entity usage on host-entity-type -> block_content manually.
    // @todo this could be made more generic to support other entity types with
    // difficult to handle parent -> child relationships.
    elseif ($source_entity->getEntityTypeId() === 'block_content') {
      $sources = $this->entityUsage->listSources($source_entity, FALSE);
      $source = reset($sources);
      if ($source !== FALSE) {
        $parent = $this->entityTypeManager()->getStorage($source['source_type'])->load($source['source_id']);
        if ($parent) {
          return $this->getSourceEntityLink($parent);
        }
      }
    }

    // As a fallback just return a non-linked label.
    return $link_text;
  }

  /**
   * Checks access based on whether the user can view the current entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess($entity_type, $entity_id) {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity || !$entity->access('view')) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
