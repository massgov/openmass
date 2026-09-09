<?php

namespace Drupal\Tests\mass_fields\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\mass_fields\Plugin\Field\FieldWidget\FormEmbedWidget;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Formstack embed validation.
 *
 * @coversDefaultClass \Drupal\mass_fields\Plugin\Field\FieldWidget\FormEmbedWidget
 * @group mass_fields
 */
class FormEmbedWidgetTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * A Formstack link inside noscript is accepted.
   */
  public function testValidFormstackEmbed(): void {
    $html = '<script src="https://www.formstack.com/forms/js.php/example"></script>' .
      '<noscript><a href="https://www.formstack.com/forms/example">View form</a></noscript>';

    $this->assertSame([], $this->validateEmbed($html)->getErrors());
  }

  /**
   * An unrelated Formstack link cannot stand in for the noscript link.
   */
  public function testValidationScopesFallbackLinkToNoscript(): void {
    $html = '<a href="https://www.formstack.com/forms/unrelated">Unrelated link</a>' .
      '<script src="https://www.formstack.com/forms/js.php/example"></script>' .
      '<noscript><a href="https://example.com/forms/example">View form</a></noscript>';

    $this->assertNotEmpty($this->validateEmbed($html)->getErrors());
  }

  /**
   * Runs widget validation against an embed fragment.
   */
  private function validateEmbed(string $html): FormState {
    $element = [
      'value' => [
        '#value' => $html,
        '#parents' => ['form_embed', 'value'],
      ],
      'type' => [
        '#value' => 'formstack',
      ],
    ];
    $form_state = new FormState();

    FormEmbedWidget::validate($element, $form_state);

    return $form_state;
  }

}
