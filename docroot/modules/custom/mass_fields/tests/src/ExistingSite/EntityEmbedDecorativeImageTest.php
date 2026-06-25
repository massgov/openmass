<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\decorative_image_widget\DecorativeImageWidgetHelper;
use Drupal\decorative_image_widget\Plugin\entity_embed\EntityEmbedDisplay\DecorativeImageFieldFormatter;
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
   * Decorative setting parents in the entity embed dialog.
   *
   * @return array
   *   The form value parents.
   */
  private function decorativeSettingParents(): array {
    return [
      'attributes',
      'data-entity-embed-display-settings',
      'decorative',
    ];
  }

  /**
   * Decorative unchecked: empty alt should trigger a validation error.
   */
  public function testEmptyAltWithoutDecorativeFailsValidation(): void {
    $form_state = new FormState();
    $form_state->setValue(['attributes', 'alt'], '');
    $form_state->setValue($this->decorativeSettingParents(), FALSE);

    $element = $this->buildAltElement();
    $complete_form = [];

    DecorativeImageFieldFormatter::validateAltOrDecorative($element, $form_state, $complete_form);

    $this->assertTrue($form_state->hasAnyErrors(), 'Empty alt without decorative checked must fail validation.');
  }

  /**
   * Decorative checked: empty alt should pass validation.
   */
  public function testEmptyAltWithDecorativePassesValidation(): void {
    $form_state = new FormState();
    $form_state->setValue(['attributes', 'alt'], '');
    $form_state->setValue($this->decorativeSettingParents(), TRUE);

    $element = $this->buildAltElement();
    $complete_form = [];

    DecorativeImageFieldFormatter::validateAltOrDecorative($element, $form_state, $complete_form);

    $this->assertSame('', $form_state->getValue(['attributes', 'alt']), 'Alt remains empty when decorative is checked.');
    $this->assertSame(1, $form_state->getValue($this->decorativeSettingParents()), 'Decorative setting is stored in display settings.');
  }

  /**
   * Decorative checked: any typed alt is forced to empty on save.
   */
  public function testTypedAltIsClearedWhenDecorativeChecked(): void {
    $form_state = new FormState();
    $form_state->setValue(['attributes', 'alt'], 'Some description');
    $form_state->setValue($this->decorativeSettingParents(), TRUE);

    $element = $this->buildAltElement();
    $complete_form = [];

    DecorativeImageFieldFormatter::validateAltOrDecorative($element, $form_state, $complete_form);

    $this->assertSame('', $form_state->getValue(['attributes', 'alt']), 'Alt is cleared when decorative is checked.');
  }

  /**
   * Decorative defaults to unchecked for new entity embed images.
   */
  public function testEntityEmbedDecorativeDefaultsUnchecked(): void {
    $display = \Drupal::service('plugin.manager.entity_embed.display')->createInstance('image:image', [
      'image_style' => 'embedded_full_width',
    ]);
    $this->assertInstanceOf(DecorativeImageFieldFormatter::class, $display);

    $display->setAttributes([
      'data-entity-type' => 'file',
      'data-entity-uuid' => '48116afe-d709-4ea2-95e0-2da46d711a4d',
      'data-entity-embed-display' => 'image:image',
    ]);

    $form_state = new FormState();
    $form = $display->buildConfigurationForm([], $form_state);

    $this->assertArrayHasKey('decorative', $form, 'Decorative checkbox is added to entity embed form.');
    $this->assertFalse((bool) $form['decorative']['#default_value'], 'Decorative checkbox defaults to unchecked for new embeds.');
  }

  /**
   * Decorative defaults to checked when stored in display settings.
   */
  public function testEntityEmbedDecorativeDefaultsCheckedFromDisplaySettings(): void {
    $display = \Drupal::service('plugin.manager.entity_embed.display')->createInstance('image:image', [
      'image_style' => 'embedded_full_width',
      'decorative' => TRUE,
    ]);

    $display->setAttributes([
      'data-entity-type' => 'file',
      'data-entity-uuid' => '48116afe-d709-4ea2-95e0-2da46d711a4d',
      'data-entity-embed-display' => 'image:image',
      'data-entity-embed-display-settings' => [
        'image_style' => 'embedded_full_width',
        'decorative' => 1,
      ],
    ]);

    $form_state = new FormState();
    $form = $display->buildConfigurationForm([], $form_state);

    $this->assertTrue((bool) $form['decorative']['#default_value'], 'Decorative checkbox defaults to checked when stored in display settings.');
  }

  /**
   * Image widget validation clears alt when decorative is checked.
   */
  public function testImageWidgetAltClearedWhenDecorativeChecked(): void {
    $form_state = new FormState();
    $form_state->setValue(['field_image', 0, 'alt'], 'Some description');
    $form_state->setValue(['field_image', 0, 'decorative'], TRUE);
    $form_state->setValue(['field_image', 0, 'fids'], '123');

    $element = [
      '#parents' => ['field_image', 0, 'alt'],
    ];

    DecorativeImageWidgetHelper::validateAltText($element, $form_state);

    $this->assertSame('', $form_state->getValue(['field_image', 0, 'alt']), 'Alt is cleared when decorative is checked on image widgets.');
  }

  /**
   * Image widget validation fails when alt is empty and decorative is unchecked.
   */
  public function testImageWidgetEmptyAltWithoutDecorativeFailsValidation(): void {
    $form_state = new FormState();
    $form_state->setValue(['field_image', 0, 'alt'], '');
    $form_state->setValue(['field_image', 0, 'decorative'], FALSE);
    $form_state->setValue(['field_image', 0, 'fids'], '123');

    $element = [
      '#parents' => ['field_image', 0, 'alt'],
    ];

    DecorativeImageWidgetHelper::validateAltText($element, $form_state);

    $this->assertTrue($form_state->hasAnyErrors(), 'Empty alt without decorative checked must fail validation on image widgets.');
  }

  /**
   * Entity embed decorative checkbox does not use image widget alignment wrapper.
   */
  public function testEntityEmbedDecorativeOmitsImageWidgetWrapperClass(): void {
    $display = \Drupal::service('plugin.manager.entity_embed.display')->createInstance('image:image', [
      'image_style' => 'embedded_full_width',
    ]);

    $form = $display->buildConfigurationForm([], new FormState());

    $this->assertArrayHasKey('decorative', $form);
    $wrapper_classes = $form['decorative']['#wrapper_attributes']['class'] ?? [];
    $this->assertNotContains('decorative-image-widget__decorative', $wrapper_classes, 'Entity embed must not use image widget alignment wrapper class.');
  }

  /**
   * Image widget theme templates are registered when decorative is enabled.
   */
  public function testImageWidgetDecorativeThemeIsRegistered(): void {
    $registry = \Drupal::service('theme.registry')->get();

    $this->assertArrayHasKey('image_widget__decorative', $registry, 'Decorative image widget theme is registered.');
    $this->assertArrayHasKey('file_managed_file__decorative', $registry, 'Decorative file managed file theme is registered.');
  }

  /**
   * Image widget adds a decorative theme suggestion when the field is present.
   */
  public function testImageWidgetDecorativeThemeSuggestion(): void {
    $suggestions = [];
    $variables = [
      'element' => [
        'decorative' => ['#type' => 'checkbox'],
      ],
    ];

    decorative_image_widget_theme_suggestions_image_widget_alter($suggestions, $variables);

    $this->assertContains('image_widget__decorative', $suggestions);
  }

  /**
   * Location banner image does not use the decorative checkbox.
   */
  public function testLocationBannerImageDecorativeCheckboxDisabled(): void {
    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $form_display */
    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.location.default');

    $this->assertNotNull($form_display);
    $component = $form_display->getComponent('field_bg_narrow');
    $this->assertSame('image_focal_point', $component['type']);
    $this->assertEmpty(
      $component['third_party_settings']['decorative_image_widget']['use_decorative_checkbox'] ?? NULL,
      'Location banner image must not enable the decorative checkbox.'
    );
  }

  /**
   * Image fields with decorative checkbox enabled are configured as expected.
   */
  public function testDecorativeCheckboxEnabledFormDisplays(): void {
    $enabled_fields = [
      ['node.news.default', 'field_news_image'],
      ['node.event.default', 'field_event_image'],
      ['node.event.default', 'field_event_logo'],
      ['paragraph.image.default', 'field_image'],
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');

    foreach ($enabled_fields as [$display_id, $field_name]) {
      /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $form_display */
      $form_display = $storage->load($display_id);
      $this->assertNotNull($form_display, "Form display $display_id exists.");
      $component = $form_display->getComponent($field_name);
      $this->assertTrue(
        !empty($component['third_party_settings']['decorative_image_widget']['use_decorative_checkbox']),
        "$display_id:$field_name must enable the decorative checkbox."
      );
    }
  }

  /**
   * Alt help text mentions the decorative checkbox only when it is enabled.
   */
  public function testAltHelpTextMatchesDecorativeCheckboxPresence(): void {
    $form_state = new FormState();

    $without_checkbox = [
      'alt' => ['#required' => FALSE],
    ];
    $without_checkbox = _mass_fields_location_image_widget_alt_help_text_process($without_checkbox, $form_state, []);
    $this->assertStringNotContainsString(
      'checkbox',
      (string) $without_checkbox['alt']['#description'],
      'Location banner alt help must not mention a decorative checkbox.'
    );

    $with_checkbox = [
      'alt' => ['#required' => FALSE],
      '#decorative_image_widget' => ['use_decorative_checkbox' => TRUE],
    ];
    $with_checkbox = _mass_fields_event_image_widget_alt_help_text_process($with_checkbox, $form_state, []);
    $this->assertStringContainsString(
      'decorative" checkbox below',
      (string) $with_checkbox['alt']['#description'],
      'Event image alt help must mention the decorative checkbox when enabled.'
    );
  }

}
