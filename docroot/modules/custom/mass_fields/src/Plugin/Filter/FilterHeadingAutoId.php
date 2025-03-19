<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a filter to automatically add ID attributes to headings in rich text fields.
 *
 * @Filter(
 *   id = "auto_heading_id",
 *   title = @Translation("Auto Heading ID"),
 *   description = @Translation("Automatically adds ID attributes to all headings in rich text fields."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterHeadingAutoId extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (empty($text)) {
      return new FilterProcessResult($text);
    }

    $document = Html::load($text);
    $xpath = new \DOMXPath($document);
    $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

    $existingIds = [];

    foreach ($headings as $heading) {
      if (!$heading->hasAttribute('id')) {
        $textContent = trim($heading->textContent);
        if (!empty($textContent)) {
          $id = $this->generateId($textContent, $existingIds);
          $heading->setAttribute('id', $id);
          $existingIds[] = $id;
        }
      }
    }

    $modifiedText = Html::serialize($document);
    return new FilterProcessResult($modifiedText);
  }

  /**
   * Generates a unique and sanitized ID based on heading text.
   */
  private function generateId($text, array $existingIds) {
    // Convert text to lowercase and replace spaces & special characters with dashes.
    $id = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
    $id = trim($id, '-');

    // Ensure ID uniqueness.
    $originalId = $id;
    $counter = 1;
    while (in_array($id, $existingIds)) {
      $id = $originalId . '-' . $counter;
      $counter++;
    }

    return $id;
  }

}
