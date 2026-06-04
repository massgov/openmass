<?php

namespace Drupal\mass_inline_message\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Html;
use Drupal\mass_inline_message\Form\MassInlineMessageDialog;
use Drupal\mass_inline_message\MessageBoxBody;
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

      $raw_body_html = MessageBoxBody::extractRawFromElement($node);
      if (!MessageBoxBody::hasRenderableContent($raw_body_html)) {
        continue;
      }

      $body_html = MessageBoxBody::normalize($raw_body_html);

      $plain_length = mb_strlen(MessageBoxBody::plainText($body_html));
      if ($plain_length > MassInlineMessageDialog::BODY_MAX_LENGTH) {
        $this->context->addViolation($constraint->bodyTooLongMessage, [
          '@count' => MassInlineMessageDialog::BODY_MAX_LENGTH,
        ]);
      }
    }
  }

}
