<?php

namespace Drupal\mass_yaml_content;

use Drupal\node\Entity\Node;

/**
 * Imports the entities.
 */
class ImportContent {

  /**
   * Class constructor.
   */
  public function __construct($config_id, $type, $config_key, $options, &$context) {
    $this->id = $config_id;
    $this->type = $type;
    $this->config_key = $config_key;
    $this->options = $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function importContent($config_id, $type, $config_key, $options, &$context) {
    $message = 'Importing Content...';
    $results = [];

    $import = \Drupal::service('content_export_yaml.manager');

    // Import the yaml file.
    $result = $import->import((int) $config_id, $type, $config_key, $options);

    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * {@inheritdoc}
   */
  public static function importFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = t('Example content created.');
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
