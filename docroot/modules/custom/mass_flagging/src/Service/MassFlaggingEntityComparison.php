<?php

namespace Drupal\mass_flagging\Service;

use Drupal\Component\Utility\Xss;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\FieldReferenceInterface;

/**
 * Class MassFlaggingEntityComparison.
 *
 * @package Drupal\mass_flagging\Service
 */
class MassFlaggingEntityComparison extends DiffEntityComparison {

  /**
   * Content moderation, when the module is available.
   *
   * This service never receives the setter Diff 1.x used, so this stays NULL
   * and revision summaries omit the moderation-state suffix.
   */
  protected ?ModerationInformationInterface $moderationInformation = NULL;

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
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
   * Builds field values and labels for a revision summary.
   *
   * Diff 2.1 removed DiffEntityComparison::summary(). The revision log and
   * the image-section check still compare this shape: a flat list keyed by
   * "{id}:{entity_type}.{field}" with "value" and "label".
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to summarize.
   *
   * @return array
   *   Field values keyed for comparison.
   */
  protected function summary(ContentEntityInterface $revision) {
    $result = [];
    $entity_type_id = $revision->getEntityTypeId();
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
    foreach ($revision as $field_items) {
      $show_delta = FALSE;
      $plugin = $this->diffBuilderManager->createInstanceForFieldDefinition($field_items->getFieldDefinition());
      if ($plugin && $this->diffBuilderManager->showDiff($field_items->getFieldDefinition()->getFieldStorageDefinition())) {
        if ($plugin instanceof FieldReferenceInterface) {
          foreach ($plugin->getEntitiesToDiff($field_items) as $entity_key => $reference_entity) {
            foreach ($this->summary($reference_entity) as $key => $build) {
              if ($field_items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() != 1) {
                $show_delta = TRUE;
              }
              $result[$key] = $build;
              $delta = $show_delta ? ' ' . ($entity_key + 1) . ' ' : ' - ';
              $result[$key]['label'] = $field_items->getFieldDefinition()->getLabel() . $delta . $result[$key]['label'];
            }
          }
        }
        else {
          $key = $revision->id() . ':' . $entity_type_id . '.' . $field_items->getName();
          $result[$key]['value'] = $field_items->getValue();
          $result[$key]['label'] = $field_items->getFieldDefinition()->getLabel();
        }
      }
    }

    return $result;
  }

  /**
   * Gets the revision's content moderation state, if available.
   *
   * Diff 2.1 moved this off DiffEntityComparison. getRevisionDescription()
   * still calls it after the field summary is built.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity revision.
   *
   * @return string|false
   *   The moderation state label, or FALSE when moderation is unavailable.
   */
  protected function getModerationState(ContentEntityInterface $entity) {
    if ($this->moderationInformation && $this->moderationInformation->isModeratedEntity($entity)) {
      if ($state = $entity->get('moderation_state')->value) {
        $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
        return $workflow->getTypePlugin()->getState($state)->label();
      }
    }

    return FALSE;
  }

}
