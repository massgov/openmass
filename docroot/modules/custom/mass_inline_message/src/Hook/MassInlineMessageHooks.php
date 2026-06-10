<?php

namespace Drupal\mass_inline_message\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\field\Entity\FieldConfig;

/**
 * Hook implementations for the Mass Inline Message module.
 */
class MassInlineMessageHooks {

  /**
   * Rich text field types that may contain message box markup.
   */
  private const RICH_TEXT_FIELD_TYPES = [
    'text_long',
    'text_with_summary',
    'string_long',
  ];

  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(&$fields, EntityTypeInterface $entity_type, $bundle): void {
    foreach ($fields as &$field) {
      if ($field instanceof FieldConfig && in_array($field->getType(), self::RICH_TEXT_FIELD_TYPES, TRUE)) {
        $field->addPropertyConstraints('value', [
          'InlineMessageConstraint' => [],
        ]);
      }
    }
  }

  #[Hook('theme')]
  public function theme(): array {
    return [
      'mass_inline_message' => [
        'variables' => [
          'type' => 'info',
          'heading' => '',
          'body' => NULL,
        ],
      ],
    ];
  }

  #[Hook('form_layout_paragraphs_component_form_alter')]
  public function formLayoutParagraphsComponentFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attached']['library'][] = 'mass_inline_message/dialog';
  }

  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!self::formHasLayoutParagraphsWidget($form)) {
      return;
    }
    $form['#attached']['library'][] = 'mass_inline_message/dialog';
  }

  /**
   * Whether a form tree contains a Layout Paragraphs field widget.
   */
  private static function formHasLayoutParagraphsWidget(array $form): bool {
    foreach ($form as $key => $element) {
      if (!is_array($element)) {
        continue;
      }
      if (($element['#type'] ?? '') === 'layout_paragraphs_builder') {
        return TRUE;
      }
      if (self::formHasLayoutParagraphsWidget($element)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
