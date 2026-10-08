<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\taxonomy\TermInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the format of SME / content owner names.
 *
 * Applied in two places: to the owner field on content, where it checks the
 * owners an author is creating on the fly, and to the term name, where it
 * checks owners added or renamed in the vocabulary. Owners that already exist
 * are not re-checked from content, so editing a page never fails because of
 * an older entry someone else created.
 */
class SmeOwnerNameConstraintValidator extends ConstraintValidator {

  /**
   * Something non-empty in parentheses, at the very end.
   */
  private const PATTERN = '/\(\s*[^()\s][^()]*\)\s*$/u';

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $entity = $value->getEntity();

    // Term name in the owner vocabulary.
    if ($entity instanceof TermInterface) {
      if ($entity->bundle() === 'sme_owner') {
        $this->check((string) $entity->getName(), $constraint, 'value');
      }
      return;
    }

    // Owners created on the fly from the content form.
    foreach ($value as $delta => $item) {
      if ($item->entity && $item->entity->isNew()) {
        $this->check((string) $item->entity->label(), $constraint, $delta . '.target_id');
      }
    }
  }

  /**
   * Adds a violation when the name does not end with a parenthesised part.
   */
  private function check(string $name, Constraint $constraint, string $path): void {
    if (!preg_match(self::PATTERN, $name)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('%name', $name)
        ->atPath($path)
        ->addViolation();
    }
  }

}
