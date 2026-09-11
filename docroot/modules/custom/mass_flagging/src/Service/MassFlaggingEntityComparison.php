<?php

namespace Drupal\mass_flagging\Service;

use Drupal\Component\Utility\Xss;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\diff\DiffBuilderManager;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\DiffEntityParser;
use Drupal\diff\DiffFormatter;

/**
 * Class MassFlaggingEntityComparison.
 *
 * @package Drupal\mass_flagging\Service
 */
class MassFlaggingEntityComparison extends DiffEntityComparison {

  /**
   * Constructs a MassFlaggingEntityComparison object.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    DiffFormatter $diffFormatter,
    FieldTypePluginManagerInterface $plugin_manager,
    DiffEntityParser $entityParser,
    DiffBuilderManager $diffBuilderManager,
    protected ?ModerationInformationInterface $moderationInformation = NULL,
  ) {
    parent::__construct($configFactory, $diffFormatter, $plugin_manager, $entityParser, $diffBuilderManager);
  }

  /**
   * Builds a revision description for watch notifications.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The current revision.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $previous_revision
   *   The previous revision, if any.
   *
   * @return string
   *   The revision log message.
   */
  public function getRevisionDescription(ContentEntityInterface $revision, ?ContentEntityInterface $previous_revision = NULL) {
    // Code is adapted from Diff module prior to patch introduced in this issue:
    // https://www.drupal.org/project/diff/issues/2880936
    $summary_elements = [];
    $revision_summary = '';
    // Check if the revision has a revision log message.
    if ($revision instanceof RevisionLogInterface) {
      $revision_summary = Xss::filter((string) $revision->getRevisionLogMessage());
    }
    // Auto generate the revision log.
    if ($revision_summary == '') {
      // If there is a previous revision, load values of both revisions, loop
      // over the current revision fields.
      if ($previous_revision) {
        $left_values = $this->summary($previous_revision);
        $right_values = $this->summary($revision);
        foreach ($right_values as $key => $value) {
          // Unset left values after comparing. Add right value label to the
          // summary if it is changed or new.
          if (isset($left_values[$key])) {
            if ($value['value'] != $left_values[$key]['value']) {
              $summary_elements[] = $value['label'];
            }
            unset($left_values[$key]);
          }
          else {
            $summary_elements[] = $value['label'];
          }
        }
        // Add the remaining left values if not present in the right entity.
        foreach ($left_values as $key => $value) {
          if (!isset($right_values[$key])) {
            $summary_elements[] = $value['label'];
          }
        }
        if (count($summary_elements) > 0) {
          $revision_summary = 'Changes on: ' . implode(', ', $summary_elements);
        }
        else {
          $revision_summary = 'No changes.';
        }
      }
      else {
        $revision_summary = 'Initial revision.';
      }
    }

    // Add workflow/content moderation state information.
    if ($state = $this->getModerationState($revision)) {
      $revision_summary .= " ($state)";
    }

    return $revision_summary;
  }

  /**
   * Detects whether only image paragraph fields changed between revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $revision
   *   The current revision.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $previous_revision
   *   The previous revision.
   *
   * @return bool
   *   TRUE if the only paragraph changes are image-section fields.
   */
  public function checkRevisionforImageSectionChanges(?ContentEntityInterface $revision, ?ContentEntityInterface $previous_revision = NULL) {
    if (!$revision || !$previous_revision) {
      return FALSE;
    }
    $elements = [];

    $mapping = [
      "field_image_administrative_title",
      "field_image",
      "field_image_alignment",
      "field_image_caption",
      "field_media_display",
      "field_image",
      "field_image_wrapping",
    ];

    if ($previous_revision) {
      $left_values = $this->summary($previous_revision);
      $right_values = $this->summary($revision);
      foreach ($right_values as $key => $value) {
        if (isset($left_values[$key])) {
          if ($value['value'] != $left_values[$key]['value']) {
            $elements[] = $key;
          }
          unset($left_values[$key]);
        }
        else {
          $elements[] = $key;
        }
      }
      // Add the remaining left values if not present in the right entity.
      foreach ($left_values as $key => $value) {
        if (!isset($right_values[$key])) {
          $elements[] = $key;
        }
      }
      if (count($elements) > 0) {
        foreach ($elements as $key => $element) {
          // We don't need the first part of exploded string.
          // It only contains the entity id.
          $el_tmp = explode(":", $element)[1];
          $el = explode(".", $el_tmp);
          if ($el[0] == 'paragraph') {
            if (in_array($el[1], $mapping)) {
              continue;
            }
            else {
              return FALSE;
            }
          }
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Creates a log message based on the changes of entity fields.
   *
   * Restores the Diff 8.x-1.10 field map that watch notifications need.
   * Walks fields directly instead of instantiating Diff field plugins, because
   * AddressFieldBuilder is not compatible with Diff 2.0.1's build(): array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The current revision.
   *
   * @return array
   *   Array of the revision fields with their value and label.
   */
  protected function summary(ContentEntityInterface $revision, array &$seen = []) {
    $seen_key = $revision->getEntityTypeId() . ':' . $revision->id() . ':' . ($revision->getRevisionId() ?? '0');
    if (isset($seen[$seen_key])) {
      return [];
    }
    $seen[$seen_key] = TRUE;

    $result = [];
    $entity_type_id = $revision->getEntityTypeId();
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
    foreach ($revision as $field_items) {
      $definition = $field_items->getFieldDefinition();
      if (!$this->diffBuilderManager->showDiff($definition->getFieldStorageDefinition())) {
        continue;
      }

      // Recurse paragraphs only. Node/media references can cycle through
      // organizations and related content and would recurse forever.
      if ($definition->getType() === 'entity_reference_revisions' && $field_items instanceof EntityReferenceFieldItemListInterface) {
        $show_delta = $definition->getFieldStorageDefinition()->getCardinality() != 1;
        foreach ($field_items->referencedEntities() as $entity_key => $reference_entity) {
          if (!$reference_entity instanceof ContentEntityInterface) {
            continue;
          }
          foreach ($this->summary($reference_entity, $seen) as $key => $build) {
            $result[$key] = $build;
            $delta = $show_delta ? ' ' . ($entity_key + 1) . ' ' : ' - ';
            $result[$key]['label'] = $definition->getLabel() . $delta . $result[$key]['label'];
          }
        }
        continue;
      }

      $key = $revision->id() . ':' . $entity_type_id . '.' . $field_items->getName();
      $result[$key]['value'] = $field_items->getValue();
      $result[$key]['label'] = $definition->getLabel();
    }

    return $result;
  }

  /**
   * Gets the revision's content moderation state, if available.
   *
   * Copied from Diff 8.x-1.10. Diff 2.0.1 moved this off DiffEntityComparison.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity revision.
   *
   * @return string|false
   *   The moderation state label, or FALSE if unavailable.
   */
  protected function getModerationState(ContentEntityInterface $entity) {
    if ($this->moderationInformation && $this->moderationInformation->isModeratedEntity($entity)) {
      if ($state = $entity->moderation_state->value) {
        $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
        return $workflow->getTypePlugin()->getState($state)->label();
      }
    }

    return FALSE;
  }

}
