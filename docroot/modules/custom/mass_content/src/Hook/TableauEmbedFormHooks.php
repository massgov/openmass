<?php

namespace Drupal\mass_content\Hook;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form states for the Tableau embed paragraph settings (DP-47145).
 *
 * The Toolbar, Data details and Share options fields only apply to the
 * "Connected Apps" embed type, and the latter two are toolbar buttons, so
 * they are only shown while the toolbar itself is displayed.
 */
class TableauEmbedFormHooks {

  /**
   * Paragraph reference fields whose widgets may host a tableau_embed.
   *
   * These are the only paragraph reference fields edited through the classic
   * paragraphs widgets that allow tableau_embed in their target bundles.
   * Fields managed by the layout paragraphs builder (field_service_sections,
   * field_info_details_sections) are handled by the
   * form_layout_paragraphs_component_form_alter hook instead.
   */
  protected const PARAGRAPH_REFERENCE_FIELDS = [
    'field_section_long_form_content',
    'field_service_section_content',
  ];

  #[Hook('field_widget_single_element_paragraphs_form_alter')]
  public function paragraphsWidgetFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $this->applyWidgetStates($element, $form_state, $context);
  }

  #[Hook('field_widget_single_element_entity_reference_paragraphs_form_alter')]
  public function entityReferenceParagraphsWidgetFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $this->applyWidgetStates($element, $form_state, $context);
  }

  /**
   * Implements hook_form_layout_paragraphs_component_form_alter().
   */
  #[Hook('form_layout_paragraphs_component_form_alter')]
  public function layoutParagraphsComponentFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form['#paragraph']->bundle() !== 'tableau_embed') {
      return;
    }
    $this->applyStates(
      $form,
      ':input[name="field_tableau_embed_type"]',
      ':input[name="field_tableau_toolbar"]'
    );
  }

  /**
   * Adds the states to a tableau_embed subform inside a paragraphs widget.
   */
  protected function applyWidgetStates(array &$element, FormStateInterface $form_state, array $context): void {
    $field_name = $context['items']->getFieldDefinition()->getName();
    if (!in_array($field_name, self::PARAGRAPH_REFERENCE_FIELDS)) {
      return;
    }
    $widget_state = WidgetBase::getWidgetState($element['#field_parents'], $field_name, $form_state);
    $paragraph = $widget_state['paragraphs'][$element['#delta']]['entity'] ?? NULL;
    if (!$paragraph || $paragraph->bundle() !== 'tableau_embed') {
      return;
    }

    $selector_template = sprintf('select[name="%s[%d][subform][%s][%d][subform][%%s]"]', $element['#field_parents'][0], $element['#field_parents'][1], $field_name, $element['#delta']);
    $this->applyStates(
      $element['subform'],
      sprintf($selector_template, 'field_tableau_embed_type'),
      sprintf($selector_template, 'field_tableau_toolbar')
    );
  }

  /**
   * Adds the visibility states to the tableau_embed setting fields.
   */
  protected function applyStates(array &$subform, string $embed_type_selector, string $toolbar_selector): void {
    if (isset($subform['field_tableau_toolbar'])) {
      $subform['field_tableau_toolbar']['#states'] = [
        'visible' => [
          $embed_type_selector => ['value' => 'connected_apps'],
        ],
      ];
    }
    // Data details and Share options are toolbar buttons, so they only
    // matter when the toolbar is actually displayed.
    foreach (['field_tableau_data_details', 'field_tableau_share_options'] as $field) {
      if (isset($subform[$field])) {
        $subform[$field]['#states'] = [
          'visible' => [
            $embed_type_selector => ['value' => 'connected_apps'],
            $toolbar_selector => [
              ['value' => 'bottom'],
              ['value' => 'top'],
            ],
          ],
        ];
      }
    }
  }

}
