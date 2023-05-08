<?php

namespace Drupal\mass_fields\Plugin\Field\FieldFormatter;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormat;
use CommerceGuys\Addressing\Locale;
use Drupal\address\AddressInterface;
use Drupal\address\Plugin\Field\FieldFormatter\AddressPlainFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'address_plain' formatter.
 *
 * @FieldFormatter(
 *   id = "address_full_link",
 *   label = @Translation("Full Address and Link Format"),
 *   field_types = {
 *     "address",
 *   },
 * )
 */
class AddressFullLinkFormatter extends AddressPlainFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link_text' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $link_text = $this->getSetting('link_text');
    return [$this->t('Link text: @text', ['@text' => $link_text])];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $link_text = $this->getSetting('link_text');
    $elements['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link Text'),
      '#default_value' => $link_text,
      '#description' => $this->t('This will appear as the link text. Leave it empty to use the url.'),
    ];
    return $elements;
  }

  /**
   * Builds a renderable array.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array.
   */
  protected function viewElement(AddressInterface $address, $langcode) {
    $country_code = $address->getCountryCode();
    $address_format = $this->addressFormatRepository->get($country_code);
    $values = $this->getValues($address, $address_format);
    foreach (['oneline', 'linebreaks'] as $type) {
      $line_ending = ($type == 'oneline') ? ', ' : PHP_EOL;
      $addresses[$type] = !empty($values['addressLine1']) ? $values['addressLine1'] . $line_ending : '';
      $addresses[$type] .= !empty($values['addressLine2']) ? $values['addressLine2'] . $line_ending : '';
      $addresses[$type] .= !empty($values['locality']) ? $values['locality'] : '';
      $addresses[$type] .= !empty($values['administrativeArea']['code']) ? ', ' . $values['administrativeArea']['code'] : '';
      $addresses[$type] .= !empty($values['postalCode']) ? ' ' . $values['postalCode'] : '';
    }
    $url = 'https://maps.google.com/?q=' . urlencode($addresses['oneline']);
    $link_text = $this->getSetting('link_text');
    $element = [
      'oneline' => [
        '#markup' => $addresses['oneline'],
      ],
      'linebreaks' => [
        '#markup' => $addresses['linebreaks'],
      ],
      'link' => [
        '#type' => 'link',
        '#title' => !empty($link_text) ? $link_text : $url,
        '#url' => $url,
      ],
    ];

    return $element;
  }

  /**
   * Gets the address values used for rendering.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   * @param \CommerceGuys\Addressing\AddressFormat\AddressFormat $address_format
   *   The address format.
   *
   * @return array
   *   The values, keyed by address field.
   */
  protected function getValues(AddressInterface $address, AddressFormat $address_format) {
    $values = [];
    foreach (AddressField::getAll() as $field) {
      $getter = 'get' . ucfirst($field);
      $values[$field] = $address->$getter();
    }

    $original_values = [];
    $subdivision_fields = $address_format->getUsedSubdivisionFields();
    $parents = [];
    foreach ($subdivision_fields as $index => $field) {
      $value = $values[$field];
      // The template needs access to both the subdivision code and name.
      $values[$field] = [
        'code' => $value,
        'name' => '',
      ];

      if (empty($value)) {
        // This level is empty, so there can be no sublevels.
        break;
      }
      $parents[] = $index ? $original_values[$subdivision_fields[$index - 1]] : $address->getCountryCode();
      $subdivision = $this->subdivisionRepository->get($value, $parents);
      if (!$subdivision) {
        break;
      }

      // Remember the original value so that it can be used for $parents.
      $original_values[$field] = $values[$field];
      // Replace the value with the expected code.
      $locale = $subdivision->getLocale();
      if ($locale && Locale::match($address->getLocale(), $locale)) {
        $values[$field] = [
          'code' => $subdivision->getLocalCode(),
          'name' => $subdivision->getLocalName(),
        ];
      }
      else {
        $values[$field] = [
          'code' => $subdivision->getCode(),
          'name' => $subdivision->getName(),
        ];
      }

      if (!$subdivision->hasChildren()) {
        // The current subdivision has no children, stop.
        break;
      }
    }

    return $values;
  }

}
