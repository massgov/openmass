<?php

declare(strict_types=1);

namespace Drupal\mass_schema_web_page;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Generates field tokens for the Decision content type.
 */
final class DecisionTokenRenderer {

  private EntityTypeManagerInterface $entityTypeManager;

  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Construct a new DecisionTokenRenderer.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Return if a node can be processed by this class.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if this node can be processed into tokens, FALSE otherwise.
   */
  public function isRenderable(NodeInterface $node): bool {
    return $node->bundle() === 'decision';
  }

  /**
   * Return an array of generated token values for use in hook_tokens().
   *
   * @param array $tokens
   *   The array of tokens as per hook_tokens().
   * @param \Drupal\node\NodeInterface $node
   *   The decision node to render tokens from.
   *
   * @throws \LogicException
   *   Thrown if the node is not the right content type.
   *
   * @return array
   *   The array of rendered tokens.
   */
  public function getTokens(array $tokens, NodeInterface $node): array {
    $replacements = [];

    if (!$this->isRenderable($node)) {
      throw new \LogicException('isRenderable() must be called before calling getTokens()');
    }

    // Iterate through all the tokens.
    foreach ($tokens as $name => $original) {
      // Check for the [field_reference:entity:field_name] token.
      if (strpos($name, ':') !== FALSE) {
        [$name] = explode(':', $name);
      }

      // If the node entity doesn't have the field, continue.
      if ($node->hasField($name)) {
        $field = $node->get($name);
        // Logic for handling entity reference fields.
        if ($field instanceof EntityReferenceFieldItemListInterface && $this->fieldReferencesDocument($field)) {
          $this->getEntityReferenceReplacements($field, $replacements, $original);
        }
      }

    }

    return $replacements;
  }

  /**
   * Add file paths to the replacements array if the target is a document.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field
   *   The field referencing documents.
   * @param array $replacements
   *   The current array of token replacements.
   * @param string $original
   *   The original token name.
   */
  private function getEntityReferenceReplacements(EntityReferenceFieldItemListInterface $field, array &$replacements, string $original): void {
    $filepaths = $this->getFilepaths($field);
    $replacements[$original] = json_encode($filepaths, JSON_THROW_ON_ERROR);
  }

  /**
   * Returns if the entity reference field references Document media items.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field
   *
   * @return bool
   *   TRUE if the field references a Document, FALSE otherwise.
   */
  private function fieldReferencesDocument(EntityReferenceFieldItemListInterface $field): bool {
    $field_definition = $field->getFieldDefinition();
    $target_type = $field_definition->getSetting('target_type');
    $handler_settings = $field_definition->getSetting('handler_settings');

    return !empty($handler_settings['target_bundles']) && $target_type === 'media' && array_key_exists('document', $handler_settings['target_bundles']);
  }

  /**
   * Return file paths for referenced media.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field
   *   The file field to load file paths from.
   *
   * @return array
   *   An array of file paths.
   */
  private function getFilepaths(EntityReferenceFieldItemListInterface $field): array {
    $filepaths = [];

    foreach ($field->getValue() as $value) {
      if (!empty($value['target_id'])) {
        // Load the media entity by the target id.
        /** @var \Drupal\media\MediaInterface $media_entity */
        $media_entity = $this->entityTypeManager->getStorage('media')
          ->load($value['target_id']);

        // Skip media entities that have been deleted from underneath the parent
        // decision node.
        if ($media_entity) {
          $this->getUploadedFileValues($media_entity, $filepaths);
        }
      }
    }

    return $filepaths;
  }

  /**
   * Add absolute URLs to all referenced files to the filepaths array.
   */
  private function getUploadedFileValues(MediaInterface $media_entity, array &$filepaths): void {
    foreach ($media_entity->get('field_upload_file')->getValue() as $file) {
      if (!empty($file['target_id'])) {
        $file_id = $file['target_id'];
        /** @var \Drupal\file\FileInterface $file_entity */
        $file_entity = $this->entityTypeManager->getStorage('file')
          ->load($file_id);

        // Skip file entities that have been deleted underneath their media
        // entity.
        if ($file_entity) {
          // Get the file uri and the url path to the file.
          $uri = $file_entity->getFileUri();
          $filepaths[] = $this->fileUrlGenerator->generateAbsoluteString($uri);
        }
      }
    }
  }

}
