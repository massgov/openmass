<?php

namespace Drupal\mass_utility;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Groups paragraph render items into components for theming.
 */
class RenderItemsGrouper implements ContainerInjectionInterface {

  /**
   * Paragraph bundles that always wrap themselves.
   *
   * @var string[]
   */
  private array $wrapParagraphs = ['iframe', 'caspio_embed', 'tableau_embed', 'image', 'stat'];

  /**
   * Field names that indicate wrapping.
   *
   * @var string[]
   */
  private array $wrapFields = [
    'field_tabl_wrapping',
    'field_iframe_wrapping',
    'field_image_wrapping',
    'field_stat_wrapping',
  ];

  /**
   * Paragraph bundles that are treated as "contained".
   *
   * @var string[]
   */
  private array $contained = ['rich_text'];

  /**
   * Groups render items.
   *
   * @param array $paragraphs
   *   Array of render items with '#paragraph' => Paragraph.
   *
   * @return array
   *   Grouped components.
   */
  public function groupRenderItems(array $paragraphs): array {
    $components = [];

    for ($i = 0; $i < count($paragraphs); $i++) {
      $component = [];
      $p = $paragraphs[$i]['#paragraph'] ?? NULL;
      if (!$p instanceof Paragraph) {
        continue;
      }

      // 1. Wrapping sequences.
      if ($this->hasAnyWrappingField($p)) {
        $items = [];
        do {
          if (isset($paragraphs[$i]['#paragraph']) && $paragraphs[$i]['#paragraph'] instanceof Paragraph) {
            $items[] = $paragraphs[$i]['#paragraph'];
          }
          $i++;
        } while (isset($paragraphs[$i]['#paragraph']) && $this->hasAnyWrappingField($paragraphs[$i]['#paragraph']));
        if (isset($paragraphs[$i]['#paragraph'])) {
          $items[] = $paragraphs[$i]['#paragraph'];
        }
        $component = ['group' => 'default', 'items' => $items];
      }
      // 2. Contained.
      elseif (in_array($p->bundle(), $this->contained, TRUE)) {
        $component = ['group' => 'contained', 'items' => $p];
      }
      // 3. Self-wrap.
      elseif (in_array($p->bundle(), $this->wrapParagraphs, TRUE)) {
        $component = ['group' => 'self', 'items' => $p];
      }
      // 4. Default.
      else {
        $component[] = $p;
      }

      $components[] = $component;
    }

    return $components;
  }

  /**
   * Checks if a paragraph has any wrapping field populated.
   */
  private function hasAnyWrappingField(Paragraph $paragraph): bool {
    foreach ($this->wrapFields as $field) {
      if ($paragraph->hasField($field) && !$paragraph->get($field)->isEmpty()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

}
