<?php

namespace Drupal\mass_yaml_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Defines a form that configures forms module settings.
 */
class MassYamlContentConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_yaml_content_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_yaml_content.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // The options to display in our checkboxes.
    $types = [
      'node' => t('Node Examples'),
      'taxonomy_term' => t('Taxonomy Examples'),
    ];

    $form['types'] = [
      '#title' => t('Types of Example Content'),
      '#type' => 'checkboxes',
      '#description' => t('Select the items you want to import.'),
      '#options' => $types,
    ];

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = $form_state->getValue('types');

    // Set the paths.
    $module_path = drupal_get_path('module', 'mass_yaml_content');
    $config_path = $module_path . '/config';
    $config_path_taxonomy = $module_path . '/config/taxonomy_term';
    $options['path'] = $module_path . '/config';

    $import = \Drupal::service('content_export_yaml.manager');
    $configs = [];

    $i = 0;
    // Get our content.
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($config_path)) as $filename) {
      $filename = str_replace($config_path . "/", "", $filename);
      // Only grab the lines that contain the yaml file.
      if (strpos($filename, '.yml') !== FALSE) {
        // Break the path and filename into an array.
        $values = explode("/", $filename);
        // Grab all files other than those in the install directory.
        if ($values[0] !== 'install') {
          $entity = $values[0];
          $type = $values[1];
          // Remove the extension from the filename.
          $id = str_replace(".yml", "", $values[2]);
          // Set the content type and file id into an array.
          $configs[$i] = [
            'entity' => $entity,
            'type' => $type,
            'id' => $id,
          ];
          $i++;
        }
      }
    }

    $batch = [
      'title' => t('Importing Content...'),
      'operations' => [],
      'init_message'     => t('Firing up the mass engines...'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message'    => t('An error occurred during processing'),
      'finished' => '\Drupal\mass_yaml_content\ImportContent::importFinishedCallback',
    ];

    foreach ($types as $type_key => $type_value) {
      // If the checkbox was checked, add items to the batch.
      if ($type_value !== 0) {
        foreach ($configs as $config) {
          // Loop through all types other than our install folder.
          if ($type_key == $config['entity']) {
            $batch['operations'][] = [
              '\Drupal\mass_yaml_content\ImportContent::importContent',
              [
                $config['id'],
                $config['entity'],
                $config['type'],
                $options,
              ],
            ];
          }
        }
      }

    }

    batch_set($batch);

  }

}
