<?php

namespace Drupal\mass_redirects\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Url;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Processes mass_redirects_repoint queue.
 *
 * @QueueWorker(
 *   id = "mass_redirects_repoint",
 *   title = @Translation("Mass redirects repoint"),
 * )
 */
class MassRedirectsRepoint extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Database\Connection $database_service
   *   The Drupal Database service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Connection $database_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    $storage = $this->entityTypeManager->getStorage($data['usage_type']);

    if (!$storage) {
      return;
    }

    $entity = $storage->load($data['usage_id']);

    if (!$entity) {
      return;
    }

    if (!$entity->hasField($data['field'])) {
      return;
    }

    $changed = FALSE;
    $options = ['absolute' => TRUE];

    $field_name = $data['field'];
    $list = $entity->get($field_name);
    $uri_old = 'entity:' . $data['from_type'] . '/' . $data['from_id'];
    $uri_new = 'entity:' . $data['from_type'] . '/' . $data['to_id'];
    foreach ($list as $delta => $item) {
      dump(get_class($item));
      switch (get_class($item)) {
        case DynamicLinkItem::class:

          $values[$delta] = $item->getValue();
          $item_uri = $item->get('uri')->getString();
          $item_uri_path = parse_url($item_uri, PHP_URL_PATH);
          if ($item_uri == $uri_old) {
            $values[$delta]['uri'] = $uri_new;
            $changed = TRUE;
          }
          elseif ($item_uri_path == Url::fromUri($uri_old)->toString()) {
            $values[$delta]['uri'] = Url::fromUri($uri_new, $options)->toString();
            $changed = TRUE;
          }
          break;

        case EntityReferenceItem::class:
          $values[$delta] = $item->getValue();
          if ($item->get('target_id')->getString() == $data['from_id']) {
            $values[$delta]['target_id'] = $data['to_id'];
            $changed = TRUE;
          }
          break;

        case TextLongItem::class:
        case TextWithSummaryItem::class:
          $values[$delta] = $item->getValue();
          // First check for the entity ID
          if (str_contains($item->getString(), $data['from_id'])) {
            $replaced = str_replace($data['from_id'], $data['to_id'], $item->getString());
            $values[$delta]['value'] = $replaced;
            $changed = TRUE;
          }
          // Next check for the link. We want relative links not
          // absolute so domain mismatch isn't an issue.
          if (str_contains($item->getString(), Url::fromUri($uri_old)->toString())) {
            $replaced = str_replace(Url::fromUri($uri_old)->toString(), Url::fromUri($uri_new)->toString(), $item->getString());
            $values[$delta]['value'] = $replaced;
            $changed = TRUE;
          }
          break;
      }

      // Update the field values if any changes were made.
      if ($changed) {
        $entity->set($field_name, $values);
        $entity->save();
      }

    }
  }

}
