<?php

namespace Drupal\mass_entityreference\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;

/**
 * Plugin implementation of the 'entity_reference_select_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_select_autocomplete",
 *   label = @Translation("Autocomplete Select Filter"),
 *   description = @Translation("An autocomplete text field with a select list filter."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceSelectAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Get all possible type options.
    $parent = parent::form($items, $form, $form_state, $get_delta);
    $node_labels = node_type_get_names();
    $options = $this->getFieldSetting('handler_settings')['target_bundles'];
    foreach ($options as $node_name) {
      $options[$node_name] = $node_labels[$node_name];
    }
    // Add a select list to filter autocomplete results.
    $parent['widget']['filter_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by Type'),
      '#multiple' => TRUE,
      '#options' => $options,
      '#ajax' => [
        'callback' => [get_class($this), 'setFilterSelect'],
      ],
    ];

    // Unset any previously saved filters when the form is constructed.
    user_cookie_save(['autocomplete_select_filter' => NULL]);

    return $parent;
  }

  /**
   * Ajax callback for the "Filter by Type" select list.
   *
   * This updates a cookie that will be used by the Entity Selection Plugin
   * to filter autocomplete results.
   */
  public static function setFilterSelect(array $form, FormStateInterface $form_state) {
    $select = $form_state->getTriggeringElement();
    user_cookie_save(['autocomplete_select_filter' => serialize($select['#value'])]);
    return new AjaxResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $referenced_entities = $items->referencedEntities();

    // Append the match operation to the selection settings.
    $selection_settings = $this->getFieldSetting('handler_settings') + ['match_operator' => $this->getSetting('match_operator')];

    // Use the custom form element so autocomplete results are not cached.
    $element += [
      '#type' => 'entity_autocomplete_filter',
      '#target_type' => $this->getFieldSetting('target_type'),
      '#selection_handler' => $this->getFieldSetting('handler'),
      '#selection_settings' => $selection_settings,
      // Entity reference field items are handling validation themselves via
      // the 'ValidReference' constraint.
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#default_value' => isset($referenced_entities[$delta]) ? $referenced_entities[$delta] : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
    ];

    if ($this->getSelectionHandlerSetting('auto_create') && ($bundle = $this->getAutocreateBundle())) {
      $element['#autocreate'] = [
        'bundle' => $bundle,
        'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : \Drupal::currentUser()->id(),
      ];
    }

    return ['target_id' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $path = array_merge($form['#parents'], [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    if ($key_exists) {
      // Account for drag-and-drop reordering if needed.
      if (!$this->handlesMultipleValues()) {
        // Remove the 'value' of the 'add more' button.
        unset($values['add_more']);
        // Remove the 'value' of the 'filter' select list.
        unset($values['filter_select']);

        // The original delta, before drag-and-drop reordering, is needed to
        // route errors to the correct form element.
        foreach ($values as $delta => &$value) {
          $value['_original_delta'] = $delta;
        }

        usort($values, function ($a, $b) {
          return SortArray::sortByKeyInt($a, $b, '_weight');
        });
      }

      // Let the widget massage the submitted values.
      $values = $this->massageFormValues($values, $form, $form_state);

      // Assign the values and remove the empty ones.
      $items->setValue($values);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = isset($item->_original_delta) ? $item->_original_delta : $delta;
        unset($item->_original_delta, $item->_weight);
      }
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

}
