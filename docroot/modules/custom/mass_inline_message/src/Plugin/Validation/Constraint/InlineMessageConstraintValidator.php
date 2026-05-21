<?php

namespace Drupal\mass_inline_message\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Html;
use Drupal\mass_inline_message\Form\MassInlineMessageDialog;
use Drupal\mass_inline_message\Plugin\Filter\FilterMassInlineMessage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates mass-inline-message markup in rich text.
 */
class InlineMessageConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value === NULL || $value === '') {
      return;
    }

    if (stripos($value, '<mass-inline-message') === FALSE) {
      return;
    }

    assert($constraint instanceof InlineMessageConstraint);

    $document = Html::load($value);
    $xpath = new \DOMXPath($document);
    $nodes = $xpath->query('//mass-inline-message');

    if (!$nodes || $nodes->length === 0) {
      return;
    }

    $allowed_tags = implode(', ', FilterMassInlineMessage::ALLOWED_BODY_TAGS);

    /** @var \DOMElement $node */
    foreach ($nodes as $node) {
      $title = trim($node->getAttribute('data-title'));
      if ($title === '') {
        $this->context->addViolation($constraint->missingTitleMessage);
        continue;
      }
      if (mb_strlen($title) > MassInlineMessageDialog::TITLE_MAX_LENGTH) {
        $this->context->addViolation($constraint->titleTooLongMessage, [
          '@count' => MassInlineMessageDialog::TITLE_MAX_LENGTH,
        ]);
      }

      $type = $node->getAttribute('data-type');
      if (!in_array($type, ['info', 'warning'], TRUE)) {
        $this->context->addViolation($constraint->invalidTypeMessage);
      }

      $raw_body_html = mass_inline_message_extract_raw_body_html($node);
      if ($raw_body_html === '') {
        continue;
      }

      if ($this->bodyContainsDisallowedElements($raw_body_html)) {
        $this->context->addViolation($constraint->disallowedTagMessage, [
          '@tags' => $allowed_tags,
        ]);
        continue;
      }

      $body_html = mass_inline_message_normalize_body_html($raw_body_html);

      $plain_length = mb_strlen(html_entity_decode(strip_tags($body_html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($plain_length > MassInlineMessageDialog::BODY_MAX_LENGTH) {
        $this->context->addViolation($constraint->bodyTooLongMessage, [
          '@count' => MassInlineMessageDialog::BODY_MAX_LENGTH,
        ]);
      }
    }
  }

  /**
   * Checks whether normalized message body HTML contains disallowed elements.
   */
  private function bodyContainsDisallowedElements(string $body_html): bool {
    $document = Html::load('<div id="mass-inline-message-body-root">' . $body_html . '</div>');
    $root = $document->getElementById('mass-inline-message-body-root');
    if (!$root) {
      return TRUE;
    }

    return $this->elementTreeHasDisallowedTags($root, TRUE);
  }

  /**
   * Recursively checks an element tree for tags outside the allowlist.
   */
  private function elementTreeHasDisallowedTags(\DOMElement $element, bool $skip_root = FALSE): bool {
    $allowed = array_flip(FilterMassInlineMessage::ALLOWED_BODY_TAGS);

    if (!$skip_root) {
      $tag = strtolower($element->tagName);
      if (!isset($allowed[$tag])) {
        return TRUE;
      }
    }

    foreach ($element->childNodes as $child) {
      if ($child instanceof \DOMElement && $this->elementTreeHasDisallowedTags($child)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
