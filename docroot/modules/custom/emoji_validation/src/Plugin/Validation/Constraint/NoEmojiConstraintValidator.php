<?php

namespace Drupal\emoji_validation\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NoEmojiConstraint constraint.
 */
class NoEmojiConstraintValidator extends ConstraintValidator {

  /**
   * Emoji validation settings (immutable config).
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructor with DI (and safe fallback).
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   The config factory (optional for safety during cache rebuilds).
   */
  public function __construct(?ConfigFactoryInterface $config_factory = NULL) {
    // Prefer DI, but fall back to the static container if needed.
    $config_factory = $config_factory ?? \Drupal::configFactory();
    $this->settings = $config_factory->get('emoji_validation.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!isset($value)) {
      return;
    }

    // Global toggle from config.
    if (!$this->settings->get('enabled')) {
      return;
    }

    $text = $this->extractTextFromValue($value);
    if ($text === '' || $text === NULL) {
      return;
    }

    // Drop any characters that are explicitly allowed via config before testing.
    $filtered = $this->stripAllowed($text);

    if ($filtered !== '' && self::containsEmoji($filtered)) {
      $this->context->addViolation($constraint->message);
    }
  }

  /**
   * Extract text content from various value types.
   */
  private function extractTextFromValue($value): string {
    if ($value instanceof FieldItemListInterface) {
      $out = '';
      foreach ($value as $item) {
        if ($item instanceof FieldItemInterface) {
          $out .= $item->getValue()['value'] ?? '';
        }
      }
      return (string) $out;
    }
    if ($value instanceof FieldItemInterface) {
      $v = $value->getValue();
      return (string) ($v['value'] ?? '');
    }
    if (is_array($value) && isset($value['value'])) {
      return (string) $value['value'];
    }
    if (is_string($value)) {
      return $value;
    }
    return (string) $value;
  }

  /**
   * Remove all explicitly allowed code points/ranges from the text.
   */
  private function stripAllowed(string $text): string {
    $codes = (array) $this->settings->get('allowed_codepoints') ?: [];
    $ranges = (array) $this->settings->get('allowed_ranges') ?: [];

    $parts = [];

    // Single code points like '00B0'
    foreach ($codes as $hex) {
      $hex = strtoupper(trim((string) $hex));
      if (preg_match('/^[0-9A-F]{2,6}$/', $hex)) {
        $parts[] = '\x{' . $hex . '}';
      }
    }

    // Ranges like '2200-22FF'
    foreach ($ranges as $range) {
      $range = strtoupper(trim((string) $range));
      if (preg_match('/^([0-9A-F]{2,6})-([0-9A-F]{2,6})$/', $range, $m)) {
        $parts[] = '[\x{' . $m[1] . '}-\x{' . $m[2] . '}]';
      }
    }

    if (!$parts) {
      return $text;
    }

    $pattern = '/(?:' . implode('|', $parts) . ')/u';
    return preg_replace($pattern, '', $text) ?? $text;
  }

  /**
   * Detects emoji/pictographic characters (incl. common ZWJ glue).
   */
  public static function containsEmoji(string $text): bool {
    $emoji_pattern = '/
      [\x{1F600}-\x{1F64F}]|
      [\x{1F300}-\x{1F5FF}]|
      [\x{1F680}-\x{1F6FF}]|
      [\x{2190}-\x{21FF}]|
      [\x{2600}-\x{26FF}]|
      [\x{2700}-\x{27FF}]|
      [\x{2B00}-\x{2BFF}]|
      [\x{1F100}-\x{1F1FF}]|
      [\x{1F900}-\x{1F9FF}]|
      [\x{1FA70}-\x{1FAFF}]|
      [\x{1F3FB}-\x{1F3FF}]|
      \x{200D}
    /ux';

    return preg_match($emoji_pattern, $text) === 1;
  }

}
