<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom view field to show media redirect source.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("media_redirect_field")
 */
class MediaRedirectField extends FieldPluginBase {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a MediaRedirectField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get the media ID from the row.
    if ($media_id = $values->mid) {

      // Query the database to get the corresponding redirect URL.
      $query = $this->database->select('redirect', 'r')
        ->fields('r', ['redirect_source__path'])
        ->condition('r.redirect_redirect__uri', 'internal:/media/' . $media_id . '%', 'LIKE')
        ->execute()
        ->fetchField();

      // If a redirect is found, return it.
      if ($query) {
        return $query;
      }
    }
    return $this->t('No redirect');
  }

}
