<?php

namespace Drupal\mass_entity_usage;

use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\mayflower\Helper;

/**
 * To decide if a entity needs to be tracked by the entity usage.
 */
class UsageTrackingBlocker {

  /**
   * Connection to the database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Creates a new UsageTrackingBlocker.
   *
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Redirects the check (if an entity should tracked) for nodes & paragraphs.
   */
  public function check($entity_type, $vid) {
    if ($entity_type == 'paragraph') {
      return $this->checkParagraph($vid);
    }

    if ($entity_type == 'node') {
      return $this->checkNode($vid);
    }
    return FALSE;
  }

  /**
   * Checks if a paragraphs should be tracked.
   */
  protected function checkParagraph($vid) {
    // Verify the paragraph revision is in the paragraphs_item_field_data table.
    $query = $this->database->select('paragraphs_item_field_data', 'pifd');
    $query->fields('pifd', ['id', 'revision_id']);
    $query->condition('revision_id', $vid);
    $results = $query->execute()->fetchAll();
    if (empty($results)) {
      return FALSE;
    }
    // Load the paragraph revision.
    $paragraph = $this->entityTypeManager->getStorage('paragraph')
      ->loadByProperties(['revision_id' => $vid]);
    // Get the parent node of the paragraph.
    $parent_node = Helper::getParentNode(current($paragraph));
    if (is_null($parent_node)) {
      return FALSE;
    }
    // Check if the parent node should be tracked.
    return $this->check('node', $parent_node->getRevisionId());
  }

  /**
   * Checks if nodes should be tracked.
   */
  protected function checkNode($vid) {
    // Load the node.
    $node = $this->entityTypeManager->getStorage('node')->loadRevision($vid);
    // Get the moderation state.
    $content_moderation_state = ContentModerationState::loadFromModeratedEntity($node);
    if ($content_moderation_state instanceof ContentModerationStateInterface
      && !$content_moderation_state->get('moderation_state')->isEmpty()) {
      $state_name = $content_moderation_state->get('moderation_state')->value;
      // These states should be not be tracked.
      $skipped_states = [
        MassModeration::UNPUBLISHED,
        MassModeration::PREPUBLISHED_DRAFT,
        MassModeration::DRAFT,
        MassModeration::PREPUBLISHED_NEEDS_REVIEW,
        MassModeration::TRASH,
      ];
      // Don't track sources that are in unpublished or trash state.
      if (in_array($state_name, $skipped_states)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
