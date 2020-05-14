<?php

namespace Drupal\mass_schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org items that can be Url or WebContent should extend this class.
 */
class SchemaUrlWebContentBase extends SchemaWebContentBase {

  /**
   * {@inheritdoc}
   */
  public function output() {
    $value = SchemaMetatagManager::unserialize($this->value());

    // If this is a complex array of value, process the array.
    if (is_array($value)) {

      // Clean out empty values.
      $value = SchemaMetatagManager::arrayTrim($value);
    }

    if (empty($value)) {
      return '';
    }

    // If url return simple value, else return WebContent.
    if ($value['@type'] === 'Url') {
      return $this->processItem($value['url']);
    }

    return parent::output();
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);

    $state = [':input[name="' . $this->visibilitySelector() . '[@type]"]' => ['value' => 'Url']];
    $form['@type']['#options']['Url'] = $this->t('Url');
    foreach (SchemaWebContentTrait::creativeWorkProperties('All') as $name => $property) {
      if ($name === 'url') {
        continue;
      }
      $form[$name]['#states']['invisible'][] = $state;
    }
    if ($this->multiple()) {
      $form['pivot']['#states']['invisible'][] = $state;
    }
    if (empty($form['@type']['#default_value'])) {
      $form['@type']['#default_value'] = 'Url';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = self::creativeWorkFormKeys('WebContent');
    foreach ($keys as $key) {
      switch ($key) {

        case '@type':
          $items[$key] = 'WebContent';
          break;

        case 'author':
          $items[$key] = SchemaPersonOrgBase::testValue();
          break;

        case 'potentialAction':
          $items[$key] = SchemaActionBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

}
