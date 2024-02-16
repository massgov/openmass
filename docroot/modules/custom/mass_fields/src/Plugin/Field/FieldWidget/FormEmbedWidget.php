<?php

namespace Drupal\mass_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
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
      '#title' => $this->t('Form success message'),
      '#default_value' => $type,
      '#options' => [
        'formstack_reload' => 'Formstack with success message on separate page',
        'formstack' => 'Formstack with success message on same page',
      ],
      '#required' => TRUE,
      '#description' => '<strong>You MUST use a separate success page</strong> to avoid errors. The success page can be an Information Details page that contains the message users should see if the form is submitted successfully. On that page, in the right column of that page under "Search Status," check the option to exclude the success page from search. Finally, in your Formstack form, change your submission message to redirect to an external URL and enter the public URL of the success page you published (starting with http://www.mass.gov).',
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
      if ($value['value']) {
        $new_values[$delta]['value'] = $value['value']['value'];
        $new_values[$delta]['type'] = $value['value']['type'];
      }
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
          $script_url = $doc->filterXPath('//script[@src]')->attr('src');
          $noscript_url = $doc->filterXPath('//noscript/a[@href]')->attr('href');

          if (!$noscript_url || !$script_url) {
            $form_state->setError($element['value'], t("Malformed embed code. FormStack embed must contain a formstack URL."));
          }
          else {
            $validate_noscript_url = parse_url($noscript_url, PHP_URL_HOST);
            $validate_script_url = parse_url($script_url, PHP_URL_HOST);
            if (!str_ends_with($validate_noscript_url, "formstack.com") || !str_ends_with($validate_script_url, "formstack.com")) {
              $form_state->setError($element['value'], t("Malformed embed code. FormStack embed must contain a formstack URL."));
            }
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
