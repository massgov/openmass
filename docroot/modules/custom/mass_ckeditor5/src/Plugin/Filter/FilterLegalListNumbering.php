<?php

namespace Drupal\mass_ckeditor5\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Provides a filter to add hierarchical numbering to nested ordered lists.
 */
#[Filter(
  id: "legal_list_numbering",
  title: new TranslatableMarkup("Legal-Style List Numbering"),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  description: new TranslatableMarkup("Adds hierarchical numbering (1.1, 1.2, 1.2.1) to nested ordered lists with class 'list-style-legal'."),
)]
class FilterLegalListNumbering extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (empty($text)) {
      return new FilterProcessResult($text);
    }

    $document = Html::load($text);
    $xpath = new \DOMXPath($document);

    // Find all ol elements with class 'list-style-legal'.
    $legalLists = $xpath->query('//ol[contains(@class, "list-style-legal")]');

    // Only process and attach library if we found legal-style lists.
    if ($legalLists->length === 0) {
      return new FilterProcessResult($text);
    }

    foreach ($legalLists as $list) {
      // Add inline style to root list.
      $this->addListStyle($list);
      $this->processListItems($list, [], TRUE);
    }

    $modifiedText = Html::serialize($document);

    // Create result and attach library since we have legal-style lists.
    $result = new FilterProcessResult($modifiedText);
    $result->addAttachments([
      'library' => [
        'mass_ckeditor5/legal-list',
      ],
    ]);

    return $result;
  }

  /**
   * Recursively process list items and add hierarchical numbering.
   *
   * @param \DOMElement $element
   *   The current element being processed.
   * @param array $counters
   *   The current counter array for tracking hierarchy.
   * @param bool $isRoot
   *   Whether this is the root list.
   */
  private function processListItems(\DOMElement $element, array $counters, bool $isRoot = FALSE): void {
    $depth = count($counters);
    $itemCounter = 0;

    foreach ($element->childNodes as $child) {
      // Only process li elements.
      if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'li') {
        $itemCounter++;

        // Update counters for current depth.
        $currentCounters = $counters;
        $currentCounters[$depth] = $itemCounter;

        // Generate the number string (e.g., "1.2.3").
        $numberText = implode('.', $currentCounters);

        // Remove any existing numbering spans.
        $this->removeNumberingSpans($child);

        // Create and insert the numbering span at the beginning.
        $span = $child->ownerDocument->createElement('span');
        $span->setAttribute('class', 'multi-level-list__marker');
        $span->nodeValue = $numberText . '. ';

        // Insert span as first child.
        if ($child->firstChild) {
          $child->insertBefore($span, $child->firstChild);
        }
        else {
          $child->appendChild($span);
        }

        // Process ALL nested ordered lists (not just those with class).
        foreach ($child->childNodes as $nestedChild) {
          if ($nestedChild->nodeType === XML_ELEMENT_NODE && $nestedChild->nodeName === 'ol') {
            // Add classes and style to nested list.
            $this->addNestedListClasses($nestedChild);
            $this->addListStyle($nestedChild);
            // Recursively process nested list.
            $this->processListItems($nestedChild, $currentCounters, FALSE);
          }
        }
      }
    }
  }

  /**
   * Add inline style to list element.
   *
   * @param \DOMElement $list
   *   The list element.
   */
  private function addListStyle(\DOMElement $list): void {
    $list->setAttribute('style', 'list-style-type:none;');
  }

  /**
   * Add classes to nested list elements.
   *
   * @param \DOMElement $list
   *   The nested list element.
   */
  private function addNestedListClasses(\DOMElement $list): void {
    $existingClasses = $list->hasAttribute('class') ? $list->getAttribute('class') : '';
    $classes = array_filter(explode(' ', $existingClasses));

    // Add multi-level-list and legal-list if not present.
    if (!in_array('multi-level-list', $classes)) {
      $classes[] = 'multi-level-list';
    }
    if (!in_array('legal-list', $classes)) {
      $classes[] = 'legal-list';
    }

    $list->setAttribute('class', implode(' ', $classes));
  }

  /**
   * Remove existing numbering spans from a list item.
   *
   * @param \DOMElement $li
   *   The list item element.
   */
  private function removeNumberingSpans(\DOMElement $li): void {
    $xpath = new \DOMXPath($li->ownerDocument);
    $spans = $xpath->query('.//span[@class="multi-level-list__marker" or @class="legal-list-number"]', $li);

    foreach ($spans as $span) {
      $span->parentNode->removeChild($span);
    }
  }

}
