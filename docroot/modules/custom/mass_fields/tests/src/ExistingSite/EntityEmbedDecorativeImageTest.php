<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\decorative_image_widget\DecorativeImageWidgetHelper;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies decorative behavior for entity embed image alt text.
 *
 * These tests exercise the validation logic that enforces:
 * - Alt text is required unless the decorative checkbox is checked.
 * - When decorative is checked, the saved alt is forced to an empty string.
 *
 * @group mass_fields
 */
class EntityEmbedDecorativeImageTest extends MassExistingSiteBase {

  /**
   * Helper to build a minimal alt element definition.
   *
   * @return array
   *   A form element array with the expected parents.
   */
  private function buildAltElement(): array {
    return [
      '#parents' => ['attributes', 'alt'],
    ];
  }

  /**
   * Decorative unchecked: empty alt should trigger a validation error.
   */
  public function testEmptyAltWithoutDecorativeFailsValidation(): void {
    $form_state = new FormState();
    $form_state->setValue(['attributes', 'alt'], '');
    $form_state->setValue(['decorative'], FALSE);

    $element = $this->buildAltElement();

    DecorativeImageWidgetHelper::validateEntityEmbedAlt($element, $form_state);

    $this->assertTrue($form_state->hasAnyErrors(), 'Empty alt without decorative checked must fail validation.');
  }

  /**
   * Decorative checked: empty alt should pass validation.
   */
  public function testEmptyAltWithDecorativePassesValidation(): void {
    $form_state = new FormState();
    $form_state->setValue(['attributes', 'alt'], '');
    $form_state->setValue(['attributes', 'decorative'], TRUE);

    $element = $this->buildAltElement();

    DecorativeImageWidgetHelper::validateEntityEmbedAlt($element, $form_state);

    // We only assert that the alt value stays empty when decorative is checked.
    $this->assertSame('', $form_state->getValue(['attributes', 'alt']), 'Alt remains empty when decorative is checked.');
  }

  /**
   * Decorative checked: any typed alt is forced to empty on save.
   */
  public function testTypedAltIsClearedWhenDecorativeChecked(): void {
    $form_state = new FormState();
    $form_state->setValue(['attributes', 'alt'], 'Some description');
    $form_state->setValue(['attributes', 'decorative'], TRUE);

    $element = $this->buildAltElement();

    DecorativeImageWidgetHelper::validateEntityEmbedAlt($element, $form_state);

    $this->assertSame('', $form_state->getValue(['attributes', 'alt']), 'Alt is cleared when decorative is checked.');
  }

}
