<?php

namespace Drupal\mass_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetBase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A form embed widget.
 *
 * @FieldWidget(
 *   id = "form_embed",
 *   label = @Translation("Form Embed widget"),
 *   field_types = {
 *     "form_embed"
 *   }
 * )
 */
class FormEmbedWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = isset($items[$delta]->value) ? $items[$delta]->value : '';
    $type = isset($items[$delta]->type) ? $items[$delta]->type : '';
    $element['value'] = [
      '#title' => 'Embedded form',
      '#type' => 'textarea',
      '#default_value' => $value,
      '#description' => t('Form pages on Mass.gov embed forms created separately in Formstack. In Formstack, click the “publish” link for your form to find the embed code and paste it here. IMPORTANT: In addition, open the form in Build mode, choose the Theme tab from the lower left, and confirm your form uses the ‘Mayflower’ theme.'),
      '#rows' => 5,
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Form Embed Type'),
      '#default_value' => $type,
      '#options' => [
        'formstack' => 'Formstack with no file upload (success message on same page)',
        'formstack_reload' => 'Formstack with file upload (success message on different page)',
      ],
      '#required' => TRUE,
      '#description' => 'Forms that include file uploads require special handling. For any form that includes a file upload, first, change the embed type here to "Formstack with file upload." Next, publish a information detail page that contains the message users should see if the form is submitted successfully. Finally, in your Formstack form, change your submission message to redirect to an external URL and enter the URL of the service detail page you published.',
    ];
    $element['#element_validate'] = [[get_called_class(), 'validate']];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $new_values = [];
    foreach ($values as $delta => $value) {
      $new_values[$delta]['value'] = $value['value']['value'];
      $new_values[$delta]['type'] = $value['value']['type'];
    }
    return $new_values;
  }

  /**
   * Validate embed text.
   */
  public static function validate(&$element, FormStateInterface $form_state) {
    $value = $element['value']['#value'];
    $type = $element['type']['#value'];

    // Validate empty field.
    if (strlen($value) == 0) {
      $form_state->setValueForElement($element, '');
      return;
    }

    switch ($type) {
      case 'formstack':
      case 'formstack_reload':
        try {
          $doc = new Crawler($value);
          $url = $doc->filterXPath('//noscript/a[@href]')->attr('href');
          if (!$url) {
            $form_state->setError($element['value'], t("Malformed embed code. FormStack embed must contain a formstack URL."));
          }
        }
        catch (\Exception $e) {
          // Also throw an error if the form is not parseable.
          $form_state->setError($element['value'], t("Malformed embed code. FormStack embed must contain a formstack URL."));
        }
        break;
    }
  }

}
