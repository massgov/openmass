<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\mayflower\Helper;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Manager class: entity processing and save flow.
 *
 * This class loops entity fields, calls the resolver, and decides if/when
 * to save revisions. The resolver class only handles link rewrite logic.
 */
class RedirectLinkNormalizationManager {
  private const REVISION_MESSAGE = 'Revision created to normalize redirected internal links.';
  private const NESTED_REVISION_MESSAGE = 'Revision created to normalize redirected internal links in nested content.';
  /**
   * In-process cache: whether an entity type/bundle has normalizable fields.
   *
   * @var array<string, bool>
   */
  private static array $normalizableFieldCache = [];

  public function __construct(
    protected RedirectLinkResolver $resolver,
    protected TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {
  }

  /**
   * Normalizes redirect-based links on one entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Node or paragraph entity.
   * @param bool $save
   *   If TRUE, save changes and create revisions when possible.
   * @param bool $dryRun
   *   If TRUE, only collect changes and do not write values.
   *
   * @return array
   *   Result with keys: changed, skipped, and changes.
   */
  public function normalizeEntity(ContentEntityInterface $entity, bool $save = TRUE, bool $dryRun = FALSE): array {
    if ($entity instanceof Paragraph && Helper::isParagraphOrphan($entity)) {
      return ['changed' => FALSE, 'skipped' => TRUE, 'changes' => []];
    }
    if (!$this->hasNormalizableFields($entity)) {
      return ['changed' => FALSE, 'skipped' => FALSE, 'changes' => []];
    }

    $apply = !$dryRun;
    $result = $this->collectFieldNormalizations($entity, $apply);

    if (!$result['changed']) {
      return ['changed' => FALSE, 'skipped' => FALSE, 'changes' => []];
    }

    if ($dryRun) {
      return [
        'changed' => TRUE,
        'skipped' => FALSE,
        'changes' => $result['changes'],
      ];
    }

    if (!$save) {
      return [
        'changed' => TRUE,
        'skipped' => FALSE,
        'changes' => $result['changes'],
      ];
    }

    if ($entity instanceof Paragraph) {
      $this->saveNormalizedParagraphAndAncestors($entity);
    }
    else {
      $this->prepareRevision($entity, self::REVISION_MESSAGE);
      $entity->save();
    }

    return [
      'changed' => TRUE,
      'skipped' => FALSE,
      'changes' => $result['changes'],
    ];
  }

  /**
   * Fast pre-check to avoid scanning entities with no supported field types.
   */
  private function hasNormalizableFields(ContentEntityInterface $entity): bool {
    $cacheKey = $entity->getEntityTypeId() . ':' . $entity->bundle();
    if (array_key_exists($cacheKey, self::$normalizableFieldCache)) {
      return self::$normalizableFieldCache[$cacheKey];
    }

    foreach ($entity->getFieldDefinitions() as $definition) {
      $fieldType = $definition->getType();
      if (in_array($fieldType, ['text_long', 'text_with_summary', 'string_long', 'link'], TRUE)) {
        self::$normalizableFieldCache[$cacheKey] = TRUE;
        return TRUE;
      }
      if ($fieldType === 'entity_reference') {
        $targetType = (string) $definition->getSetting('target_type');
        if (in_array($targetType, ['node', 'media'], TRUE)) {
          self::$normalizableFieldCache[$cacheKey] = TRUE;
          return TRUE;
        }
      }
    }

    self::$normalizableFieldCache[$cacheKey] = FALSE;
    return FALSE;
  }

  /**
   * Saves a normalized paragraph and ancestor revisions without stale embeds.
   *
   * After the paragraph is saved, parent entities must reference the new
   * paragraph revision. Saving the host node with an in-memory copy of the
   * pre-normalization paragraph would create another paragraph revision that
   * reverts the link fix.
   */
  private function saveNormalizedParagraphAndAncestors(Paragraph $paragraph): void {
    $transaction = $this->database->startTransaction();
    try {
      $this->saveNormalizedParagraphAndAncestorsWithinTransaction($paragraph);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Persists paragraph, ancestor, and host-node revisions as one DB unit.
   */
  private function saveNormalizedParagraphAndAncestorsWithinTransaction(Paragraph $paragraph): void {
    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');

    $this->prepareRevision($paragraph, self::REVISION_MESSAGE);
    $paragraph->save();

    $freshParagraph = $paragraphStorage->loadRevision((int) $paragraph->getRevisionId());
    if (!$freshParagraph instanceof Paragraph) {
      throw new \RuntimeException(sprintf(
        'Failed to load paragraph revision %d after normalization save.',
        (int) $paragraph->getRevisionId()
      ));
    }

    $parent = $freshParagraph->getParentEntity();
    while ($parent instanceof Paragraph) {
      $parentParagraph = $paragraphStorage->load((int) $parent->id());
      if (!$parentParagraph instanceof Paragraph) {
        throw new \RuntimeException(sprintf(
          'Failed to load parent paragraph %d while saving normalized nested content.',
          (int) $parent->id()
        ));
      }
      if (!$this->replaceParagraphReference($parentParagraph, $freshParagraph)) {
        throw new \RuntimeException(sprintf(
          'Failed to update parent paragraph %d to reference normalized paragraph revision %d.',
          (int) $parentParagraph->id(),
          (int) $freshParagraph->getRevisionId()
        ));
      }
      $this->prepareRevision($parentParagraph, self::REVISION_MESSAGE);
      $parentParagraph->save();

      $freshParagraph = $paragraphStorage->loadRevision((int) $parentParagraph->getRevisionId());
      if (!$freshParagraph instanceof Paragraph) {
        throw new \RuntimeException(sprintf(
          'Failed to load paragraph revision %d after parent paragraph save.',
          (int) $parentParagraph->getRevisionId()
        ));
      }
      $parent = $freshParagraph->getParentEntity();
    }

    if (!$parent instanceof NodeInterface) {
      throw new \RuntimeException(sprintf(
        'Failed to resolve host node for normalized paragraph %d.',
        (int) $freshParagraph->id()
      ));
    }

    $node = $this->entityTypeManager->getStorage('node')->load((int) $parent->id());
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf(
        'Failed to load host node %d for normalized paragraph %d.',
        (int) $parent->id(),
        (int) $freshParagraph->id()
      ));
    }

    if (!$this->replaceParagraphReference($node, $freshParagraph)) {
      throw new \RuntimeException(sprintf(
        'Failed to update host node %d to reference normalized paragraph revision %d.',
        (int) $node->id(),
        (int) $freshParagraph->getRevisionId()
      ));
    }
    $this->prepareRevision($node, self::NESTED_REVISION_MESSAGE);
    $node->save();
  }

  /**
   * Points entity reference revision fields at the saved paragraph revision.
   *
   * Walks nested paragraph fields so host entities embed the normalized copy.
   *
   * @return bool
   *   TRUE when a reference to the paragraph was replaced.
   */
  private function replaceParagraphReference(ContentEntityInterface $parent, Paragraph $freshParagraph): bool {
    $paragraphId = (int) $freshParagraph->id();
    $found = FALSE;

    foreach ($parent->getFieldDefinitions() as $fieldName => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($parent->get($fieldName)->isEmpty()) {
        continue;
      }

      foreach ($parent->get($fieldName) as $delta => $item) {
        if ((int) ($item->target_id ?? 0) === $paragraphId) {
          $parent->get($fieldName)->set($delta, [
            'target_id' => $paragraphId,
            'target_revision_id' => (int) $freshParagraph->getRevisionId(),
            'entity' => $freshParagraph,
          ]);
          $found = TRUE;
          continue;
        }

        $embedded = $item->entity;
        if ($embedded instanceof Paragraph && $this->replaceParagraphReference($embedded, $freshParagraph)) {
          $parent->get($fieldName)->set($delta, [
            'target_id' => (int) $embedded->id(),
            'target_revision_id' => (int) $embedded->getRevisionId(),
            'entity' => $embedded,
          ]);
          $found = TRUE;
        }
      }
    }

    return $found;
  }

  /**
   * Scans text and link fields and updates values when needed.
   *
   * @return array
   *   An array with keys:
   *   - changed (bool): TRUE when at least one value changed.
   *   - changes (array): List of changed items (field, delta, kind, before,
   *     after).
   */
  private function collectFieldNormalizations(ContentEntityInterface $entity, bool $apply): array {
    $changed = FALSE;
    $changes = [];

    foreach ($entity->getFields() as $fieldName => $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      if (in_array($fieldType, ['text_long', 'text_with_summary', 'string_long'], TRUE)) {
        foreach ($field as $delta => $item) {
          if (!isset($item->value) || $item->value === NULL || $item->value === '') {
            continue;
          }
          $before = (string) $item->value;
          $processed = $this->resolver->normalizeRedirectLinksInText($before);
          if (!$processed['changed']) {
            continue;
          }
          $changed = TRUE;
          $changes[] = [
            'field' => (string) $fieldName,
            'delta' => (int) $delta,
            'kind' => 'text',
            'before' => $before,
            'after' => $processed['text'],
          ];
          if ($apply) {
            $item->value = $processed['text'];
          }
        }
      }
      elseif ($fieldType === 'link') {
        foreach ($field as $delta => $item) {
          if (empty($item->uri)) {
            continue;
          }
          $before = (string) $item->uri;
          $processed = $this->resolver->normalizeRedirectLinkUri($before);
          if (!$processed['changed']) {
            continue;
          }
          $changed = TRUE;
          $changes[] = [
            'field' => (string) $fieldName,
            'delta' => (int) $delta,
            'kind' => 'link',
            'before' => $before,
            'after' => $processed['uri'],
          ];
          if ($apply) {
            $item->uri = $processed['uri'];
          }
        }
      }
      elseif ($fieldType === 'entity_reference') {
        $targetType = (string) $field->getFieldDefinition()->getSetting('target_type');
        if (!in_array($targetType, ['node', 'media'], TRUE)) {
          continue;
        }
        foreach ($field as $delta => $item) {
          $beforeId = (int) ($item->target_id ?? 0);
          if ($beforeId <= 0) {
            continue;
          }
          $processed = $this->resolver->normalizeEntityReferenceTarget($targetType, $beforeId);
          if (!$processed['changed']) {
            continue;
          }
          $afterId = (int) $processed['target_entity_id'];
          $changed = TRUE;
          $changes[] = [
            'field' => (string) $fieldName,
            'delta' => (int) $delta,
            'kind' => 'entity_reference',
            'before' => $targetType . ':' . $beforeId,
            'after' => $targetType . ':' . $afterId,
          ];
          if ($apply) {
            $item->target_id = $afterId;
          }
        }
      }
    }

    return ['changed' => $changed, 'changes' => $changes];
  }

  /**
   * Sets revision data if the entity supports revisions.
   */
  private function prepareRevision(ContentEntityInterface $entity, string $message): void {
    $changedTime = NULL;
    if ($entity instanceof EntityChangedInterface) {
      $changedTime = $entity->getChangedTime();
    }

    // Automated sweeps should not bump "last updated" or trigger sync side-effects.
    $entity->setSyncing(TRUE);

    if ($entity instanceof RevisionableInterface) {
      $entity->setNewRevision();
    }
    if ($entity instanceof RevisionLogInterface) {
      // These automated fixes should show as admin.
      $entity->setRevisionUserId(1);
      $entity->setRevisionLogMessage($message);
      $entity->setRevisionCreationTime($this->time->getRequestTime());
    }
    if ($changedTime !== NULL) {
      $entity->setChangedTime($changedTime);
    }
  }

}

