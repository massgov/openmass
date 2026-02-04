<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for 'schema_government_service_potential_action' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_potential_action",
 *   label = @Translation("Potential Action"),
 *   description = @Translation("The potential action of the item."),
 *   name = "potentialAction",
 *   group = "schema_government_service",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaGovernmentServicePotentialAction extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $value = SchemaMetatagManager::unserialize($this->value());

    $form['#type'] = 'details';
    $form['#title'] = $this->label();
    $form['#description'] = $this->description();
    $form['#tree'] = TRUE;
    $form['#open'] = !empty($value['potentialAction']);
    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Action' => $this->t("Action"),
        'AchieveAction' => $this->t("AchieveAction"),
        'ConsumeAction' => $this->t("ConsumeAction"),
        'ControlAction' => $this->t("ControlAction"),
        'CreateAction' => $this->t("CreateAction"),
        'FindAction' => $this->t("FindAction"),
        'InteractAction' => $this->t("InteractAction"),
        'MoveAction' => $this->t("MoveAction"),
        'OrganizeAction' => $this->t("OrganizeAction"),
        'PlayAction' => $this->t("PlayAction"),
        'SearchAction' => $this->t("SearchAction"),
        'TradeAction' => $this->t("TradeAction"),
        'TransferAction' => $this->t("TransferAction"),
        'UpdateAction' => $this->t("UpdateAction"),
      ],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
    ];

    $form['potentialAction'] = [
      '#type' => 'textfield',
      '#title' => $this->t('potentialAction'),
      '#default_value' => !empty($value['potentialAction']) ? $value['potentialAction'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("The street address. For example, 1600 Amphitheatre Pkwy."),
      '#attributes' => [
        'placeholder' => '[node:title]',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();
    $values = SchemaMetatagManager::unserialize($this->value());
    $link_content = [];

    // NOTE: Our downstream services like search.mass.gov depend on "GovernmentService" schema data where
    // they expect ```"potentialAction": [],``` to still be present as an empty array.
    // So if the element returned above is empty, for potentialAction to still show in the output as empty array,
    // we set $element with the requisite metatag structure still, but with no content.
    if (empty($element) && empty($values)) {
      // The below structure can be set ONLY when there are no values.
      $element = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => $this->name,
          'content' => [],
          'group' => $this->group,
          'schema_metatag' => TRUE,
        ],
      ];
    }
    elseif (!empty($values)) {
      // When there are one or more values, the structure is set as below.
      $element['#attributes']['content'] = [];
      // Since there could be multiple values, explode the string value.
      $actions = explode(', ', $values['potentialAction']);
      foreach ($actions as $action) {
        $decoded_value = json_decode($action, TRUE);
        if (is_array($decoded_value)) {
          foreach ($decoded_value as $item) {
            $link_content[] = $item;
          }
        }
        else {
          $link_content[] = $action;
        }
      }
    }

    // Iterate through each link value to get its name and url for output.
    foreach ($link_content as $link_values) {
      $name = !empty($link_values['name']) ? $link_values['name'] : '';
      $url = !empty($link_values['url']) ? $link_values['url'] : '';
      // Decode the link values.
      if ($name || $url) {
        // For each link item, append the values of the 'name' and 'url' to the
        // 'content' key. This will be the value outputted on the markup.
        $element['#attributes']['content'][] = [
          '@type' => $values['@type'],
          'name' => $name,
          'url' => $url,
        ];
      }
    }

    return $element;
  }

}
