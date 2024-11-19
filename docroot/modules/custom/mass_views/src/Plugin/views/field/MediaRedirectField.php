<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom view field to show media redirect source, including following multiple redirects.
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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager')
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
      // Start by checking for redirects pointing to this media entity.
      $final_redirect = $this->findFinalRedirect('internal:/media/' . $media_id);

      // Prepare the output string.
      $output = '';

      // If a final redirect was found, attempt to load the entity from it.
      if ($final_redirect) {
        // Append the final redirect path.
        $output .= '<strong>Final redirect:</strong> ' . $final_redirect;

        // Try to load the entity.
        $entity = $this->loadEntityFromRedirect($final_redirect);

        // If an entity was loaded, append its label.
        if ($entity) {
          $output .= '<br><strong>Entity found:</strong> ' . $entity->label();

          // Query the entity usage table to see if the media entity is in use.
          $usage_count = $this->getEntityUsageCount($media_id);
          if ($usage_count > 0) {
            $output .= '<br><strong>Usage count:</strong> ' . $usage_count;
          }
          else {
            $output .= '<br><strong>Usage:</strong> Not used';
          }
        }
        else {
          $output .= '<br>No entity found for this redirect.';
        }

        // Use Markup::create to return the raw HTML.
        return Markup::create($output);
      }
    }

    return $this->t('No redirect');
  }

  /**
   * Recursive function to find the final redirect in a chain.
   *
   * @param string $uri
   *   The URI to check for redirects.
   *
   * @return string|null
   *   The final redirect URI or null if no redirect exists.
   */
  protected function findFinalRedirect($uri) {
    // Query the database to get the corresponding redirect source for the given URI.
    $query = $this->database->select('redirect', 'r')
      ->fields('r', ['redirect_source__path'])
      ->condition('r.redirect_redirect__uri', $uri, '=')
      ->execute()
      ->fetchField();

    // If a redirect is found, check if it points to another redirect.
    if ($query) {
      // Recursively call the function to follow the redirect chain.
      $next_redirect = $this->findFinalRedirect('internal:/' . $query);

      // If another redirect was found, return it. Otherwise, return the current one.
      return $next_redirect ? $next_redirect : $query;
    }

    // If no further redirects are found, return null.
    return NULL;
  }

  /**
   * Load a media entity from the final redirect path.
   *
   * @param string $path
   *   The path from the final redirect.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The loaded media entity, or null if no entity could be found.
   */
  protected function loadEntityFromRedirect($path) {
    // Use the path alias manager to get the internal system path.
    $internal_path = $this->aliasManager->getPathByAlias($path);

    // Check if the internal path is in the format 'media/{id}'.
    if (preg_match('/^media\/(\d+)$/', $internal_path, $matches)) {
      $media_id = $matches[1];

      // Attempt to load the media entity.
      $media = $this->entityTypeManager->getStorage('media')->load($media_id);

      if ($media) {
        return $media;
      }
    }

    // No media entity was found or the path did not resolve to a media entity.
    return NULL;
  }

  /**
   * Query the entity usage table to count how many times a media entity is used.
   *
   * @param int $media_id
   *   The media entity ID.
   *
   * @return int
   *   The number of times the media entity is used.
   */
  protected function getEntityUsageCount($media_id) {
    // Query the entity_usage table to count records where target_id = media ID and target_type = 'media'.
    $count = $this->database->select('entity_usage', 'eu')
      ->condition('target_id', $media_id)
      ->condition('target_type', 'media')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count ? (int) $count : 0;
  }

}
