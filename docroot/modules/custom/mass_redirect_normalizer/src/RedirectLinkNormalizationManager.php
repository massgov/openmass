<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\mayflower\Helper;
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
   * Creates the manager.
   */
  public function __construct(
    protected RedirectLinkResolver $resolver,
    protected TimeInterface $time,
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

    $this->prepareRevision($entity, self::REVISION_MESSAGE);
    $entity->save();

    if ($entity->getEntityTypeId() === 'paragraph' && $node = Helper::getParentNode($entity)) {
      $this->prepareRevision($node, self::NESTED_REVISION_MESSAGE);
      $node->save();
    }

    return [
      'changed' => TRUE,
      'skipped' => FALSE,
      'changes' => $result['changes'],
    ];
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
    }

    return ['changed' => $changed, 'changes' => $changes];
  }

  /**
   * Sets revision data if the entity supports revisions.
   */
  private function prepareRevision(ContentEntityInterface $entity, string $message): void {
    if ($entity instanceof RevisionableInterface) {
      $entity->setNewRevision();
    }
    if ($entity instanceof RevisionLogInterface) {
      // Keep automated URL-fix revisions attributable to admin.
      $entity->setRevisionUserId(1);
      $entity->setRevisionLogMessage($message);
      $entity->setRevisionCreationTime($this->time->getRequestTime());
    }
  }

}
