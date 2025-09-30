<?php

namespace Drupal\emoji_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NoEmojiConstraint constraint.
 */
class NoEmojiConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!isset($value)) {
      return;
    }

    $text = $this->extractTextFromValue($value);

    if (empty($text)) {
      return;
    }

    if ($this->containsEmoji($text)) {
      $this->context->addViolation($constraint->message);
    }
  }

  /**
   * Extract text content from various value types.
   *
   * @param mixed $value
   *   The value to extract text from.
   *
   * @return string
   *   The extracted text content.
   */
  private function extractTextFromValue($value) {
    // Handle FieldItemList objects
    if ($value instanceof \Drupal\Core\Field\FieldItemListInterface) {
      $text = '';
      foreach ($value as $item) {
        if ($item instanceof \Drupal\Core\Field\FieldItemInterface) {
          $text .= $item->getValue()['value'] ?? '';
        }
      }
      return $text;
    }

    // Handle FieldItem objects
    if ($value instanceof \Drupal\Core\Field\FieldItemInterface) {
      $item_value = $value->getValue();
      return $item_value['value'] ?? '';
    }

    // Handle arrays (field values)
    if (is_array($value) && isset($value['value'])) {
      return $value['value'];
    }

    // Handle strings directly
    if (is_string($value)) {
      return $value;
    }

    // Fallback to string conversion
    return (string) $value;
  }

  /**
   * Check if text contains emoji characters.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE if emojis are found, FALSE otherwise.
   */
  private function containsEmoji($text) {
    $legitimate_symbols = [
      '©', '®', '™', '✈', '★', '☆', '➔', '➤', '➥', '➦', '➧', '➨', '➩', '➪', '➫', '➬', '➭', '➮', '➯',
      '→', '←', '↑', '↓', '₽', '₹', '€', '£', '¥', '¢',
    ];

    foreach ($legitimate_symbols as $symbol) {
      if (strpos($text, $symbol) !== FALSE) {
        if (trim($text) === $symbol || preg_match('/^[\s\w' . preg_quote($symbol, '/') . ']+$/u', $text)) {
          continue;
        }
      }
    }

    $emoji_pattern = '/
      [\x{1F600}-\x{1F64F}]
      |
      [\x{1F300}-\x{1F5FF}]
      |
      [\x{1F680}-\x{1F6FF}]
      |
      [\x{2600}-\x{2604}\x{260E}-\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26FA}\x{26FD}\x{2702}\x{2705}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}]
      |
      [\x{2700}-\x{2701}\x{2703}-\x{2704}\x{2706}-\x{2707}\x{2709}-\x{270B}\x{270E}-\x{2711}\x{2713}\x{2715}\x{2717}-\x{271C}\x{271E}-\x{2720}\x{2722}-\x{2727}\x{2729}-\x{2732}\x{2735}-\x{2743}\x{2745}-\x{2746}\x{2748}-\x{274B}\x{274D}\x{274F}-\x{2752}\x{2756}\x{2758}-\x{2762}\x{2765}-\x{2767}\x{2768}-\x{2775}\x{2780}-\x{2793}\x{2795}-\x{2797}\x{2799}-\x{27AF}\x{27B1}-\x{27BE}\x{27C0}-\x{27C4}\x{27C7}-\x{27E5}\x{27F0}-\x{27FF}\x{2900}-\x{2982}\x{2999}-\x{29D7}\x{29DA}-\x{29DB}\x{29DC}-\x{29FB}\x{29FE}-\x{2A00}\x{2AFF}-\x{2B00}\x{2B04}\x{2B08}-\x{2B1A}\x{2B1D}-\x{2B4F}\x{2B56}-\x{2B59}\x{2B5B}-\x{2B73}\x{2B76}-\x{2B95}\x{2B97}-\x{2BFF}]
      |
      [\x{1F100}-\x{1F10A}\x{1F110}-\x{1F12E}\x{1F130}-\x{1F16B}\x{1F170}-\x{1F1AC}\x{1F1E6}-\x{1F1FF}]
      |
      [\x{1F900}-\x{1F9FF}]
      |
      [\x{1FA70}-\x{1FAFF}]
      |
      [\x{1F3FB}-\x{1F3FF}]
      |
      [\x{1F1E6}-\x{1F1FF}]
      |
      \x{200D}
    /ux';

    return preg_match($emoji_pattern, $text) === 1;
  }

}
