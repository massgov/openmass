<?php

namespace Drupal\mass_entityreference\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Provides a filterable entity autocomplete form element.
 *
 * The #default_value accepted by this element is either an entity object or an
 * array of entity objects.
 *
 * @FormElement("entity_autocomplete_filter")
 */
class EntityAutocompleteFilter extends EntityAutocomplete {

  /**
   * {@inheritdoc}
   */
  public static function processAutocomplete(&$element, FormStateInterface $form_state, &$complete_form) {
    $url = NULL;
    $access = FALSE;

    if (!empty($element['#autocomplete_route_name'])) {
      $parameters = isset($element['#autocomplete_route_parameters']) ? $element['#autocomplete_route_parameters'] : [];
      $url = Url::fromRoute($element['#autocomplete_route_name'], $parameters)->toString(TRUE);
      /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
      $access_manager = \Drupal::service('access_manager');
      $access = $access_manager->checkNamedRoute($element['#autocomplete_route_name'], $parameters, \Drupal::currentUser(), TRUE);
    }

    if ($access) {
      $metadata = BubbleableMetadata::createFromRenderArray($element);
      if ($access->isAllowed()) {
        $element['#attributes']['class'][] = 'form-autocomplete';
        // Use Autocomplete Javascript that does not cache results.
        $metadata->addAttachments(['library' => ['mass_entityreference/mass_entityreference.autocompletefilter']]);
        // Provide a data attribute for the JavaScript behavior to bind to.
        $element['#attributes']['data-autocomplete-path'] = $url->getGeneratedUrl();
        $metadata = $metadata->merge($url);
      }
      $metadata
        ->merge(BubbleableMetadata::createFromObject($access))
        ->applyTo($element);
    }

    return $element;
  }

}
