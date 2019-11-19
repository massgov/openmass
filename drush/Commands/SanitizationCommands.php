<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use MassGov\Sanitation\ContentModerationSanitizer;
use MassGov\Sanitation\MediaSanitizer;
use MassGov\Sanitation\SqlEntitySanitizer;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Extra sanitization commands for drush sql:sanitize.
 *
 * @property EntityFieldManagerInterface $entityFieldManager
 * @property Connection $database
 * @property EntityTypeManagerInterface $entityTypeManager
 */
class SanitizationCommands extends DrushCommands {

  /**
   * Dummy magic method to work around site commands not being DI-capable.
   *
   * This will go away when this work is moved into Drush core - for now it's
   * just an easy way to pretend we've had these things injected. Replace with
   * a proper constructor later.
   */
  public function __get($name) {
    switch ($name) {
      case 'database':
        return \Drupal::database();

      case 'entityTypeManager':
        return \Drupal::entityTypeManager();

      case 'entityFieldManager':
        return \Drupal::service('entity_field.manager');
    }
  }

  /**
   * Sanitize unpublished entities and old revisions of entities.
   *
   * @hook post-command sql-sanitize
   */
  public function sanitizeEntities($result, CommandData $commandData) {
    if (!$commandData->input()->getOption('sanitize-entities')) {
      return;
    }
    $types = array_keys($this->entityTypeManager->getDefinitions());
    // This is a bit of a hack, but content moderation needs to go dead last
    // so it can clean up after all the other stuff has been deleted.
    $idx = array_search('content_moderation_state', $types);
    if ($idx !== -1) {
      unset($types[$idx]);
      $types[] = 'content_moderation_state';
    }

    foreach ($types as $type) {
      if ($sanitizer = $this->getEntitySanitizer($type)) {
        $sanitizer->sanitize();;
      }
    }
  }

  /**
   * Sanitize key_value and key_value_expire.
   *
   * These two tables contain sensitive information, such as form data, and
   * Acquia subscription data. They can also be used as arbitrary data storage,
   * so we're not sure what's gonna end up in there.  As a result, we truncate
   * key_value_expire (since it's all ephemeral by nature), and use a
   * "whitelist" approach to dealing with the key_value data (we only retain
   * specific collections).
   *
   * This will probably need to be adjusted in the future, for example if a
   * contrib module begins storing something critical in key_value.
   *
   * @hook post-command sql-sanitize
   */
  public function sanitizeKeyValue($result, CommandData $commandData) {
    if (!$commandData->input()->getOption('sanitize-keyvalue')) {
      return;
    }

    $this->database->truncate('key_value_expire')
      ->execute();

    $this->database->delete('key_value')
      ->condition('collection', 'config.entity.key_store.%', 'NOT LIKE')
      ->condition('collection', 'entity.definitions.%', 'NOT LIKE')
      ->condition('collection', 'pathauto_state.%', 'NOT LIKE')
      ->condition('collection', 'entity.storage_schema.sql', '!=')
      ->condition('collection', 'system.schema', '!=')
      ->condition('collection', 'post_update', '!=')
      // State will be handled separately.
      ->condition('collection', 'state', '!=')
      ->execute();

    $this->database->delete('key_value')
      ->condition('collection', 'state')
      ->condition('name', 'mass_admin_pages.%', 'NOT LIKE')
      ->condition('name', 'mass_feedback_form.%', 'NOT LIKE')
      ->execute();
  }

  /**
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input) {
    if ($input->getOption('sanitize-keyvalue')) {
      $messages[] = 'Sanitize key_value table.';
    }
    if ($input->getOption('sanitize-entities')) {
      $messages[] = 'Sanitize unpublished and old revisions of entities.';
    }
    if ($input->getOption('sanitize-name')) {
      $message[] = 'Sanitize user names.';
    }
  }

  /**
   * @hook option sql-sanitize
   * @option sanitize-keyvalue Boolean flag for sanitization of key_value.
   */
  public function options($options = ['sanitize-keyvalue' => FALSE, 'sanitize-entities' => FALSE, 'sanitize-name' => FALSE]) {
  }

  /**
   * Retrieve an entity sanitization "plugin" for a given entity type.
   */
  private function getEntitySanitizer($type) {
    $storage = $this->getEntityStorage($type);
    if ($storage instanceof SqlEntityStorageInterface) {
      switch ($type) {
        case 'content_moderation_state':
          $sanitizer = new ContentModerationSanitizer($this->database, $storage, $this->entityFieldManager->getFieldStorageDefinitions($type), $this->logger());
          $sanitizer->setEntityTypeManager($this->entityTypeManager);
          return $sanitizer;
        case 'media':
          return new MediaSanitizer($this->database, $storage, $this->entityFieldManager->getFieldStorageDefinitions($type), $this->logger());
        default:
          return new SqlEntitySanitizer($this->database, $storage, $this->entityFieldManager->getFieldStorageDefinitions($type), $this->logger());
      }
    }
  }

  /**
   * Check the number of dangling ERR references we've got.
   *
   * A dangling reference is defined as any ERR field that references an entity
   * that is not actually the current revision. In our case, we only care about
   * these dangling references as they pertain to the active revision of the
   * host entity. ie: we want every published node to only reference published
   * paragraphs.
   *
   * @bootstrap full
   *
   * @command ma:checkmissing
   */
  public function checkMissing() {
    /** @var \Drupal\field\FieldStorageConfigInterface[] $fieldStorages */
    $fieldStorages = $this->getEntityStorage('field_storage_config')
      ->loadByProperties([
        'type' => 'entity_reference_revisions',
      ]);

    foreach ($fieldStorages as $fieldStorage) {
      /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $sourceStorage */
      $sourceStorage = $this->getEntityStorage($fieldStorage->getTargetEntityTypeId());
      if (!$sourceStorage instanceof SqlEntityStorageInterface) {
        continue;
      }
      $sourceMapping = $sourceStorage->getTableMapping();
      $targetEntityType = $fieldStorage->getSetting('target_type');

      /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $targetStorage */
      $targetStorage = $this->getEntityStorage($targetEntityType);
      $targetType = $targetStorage->getEntityType();

      $revisionFieldName = $sourceMapping->getFieldColumnName($fieldStorage, 'target_revision_id');

      $select = $this->database->select($sourceMapping->getDedicatedDataTableName($fieldStorage), 's')
        ->fields('s', [$revisionFieldName]);

      $select->leftJoin($targetStorage->getDataTable(), 'b', "s.{$revisionFieldName} = b.{$targetType->getKey('revision')}");
      $select->condition("b.{$targetType->getKey('revision')}", NULL, '=');
      $count = $select->countQuery()->execute()->fetchField();
      $this->logger()->notice("Detected {$count} mismatched ERR references in {$fieldStorage->id()}");

    }
  }

  /**
   * Retrieves the entity storage handler for an entity type.
   */
  private function getEntityStorage(string $type) {
    return $this->entityTypeManager->getStorage($type);
  }

  /**
   * Sanitize the database table for the user roles.
   *
   * @hook post-command sql-sanitize
   */
  public function userRole($result, CommandData $commandData) {
    if (!$commandData->input()->getOption('sanitize-roles')) {
      return;
    }
    // Remove all authors roles from the database.
    $role = Database::getConnection()->truncate('user_roles')->execute();
    if ($role) {
      $this->logger()->error(dt('The user roles have not been truncated.'));
    } else {
      $this->logger()->success(dt('The user roles have been truncated.'));
    }
  }

  /**
   * Sanitize the database table for the username.
   *
   * @hook post-command sql-sanitize
   */
  public function userName($result, CommandData $commandData) {
    // User data table updated.
    $options = $commandData->options();
    $query = $this->database->update('users_field_data')->condition('uid', 0, '>');
    $messages = [];

    if ($this->isEnabled($options['sanitize-name'])) {
      if (strpos($options['sanitize-name'], '%') !== false) {
        $name_map = ['%name' => "', replace(name, ' ', '_'), '"];
        $new_name = "concat('" . str_replace(array_keys($name_map), array_values($name_map), $options['sanitize-name']) . "')";
      }
      $query->expression('name', $new_name);

      $messages[] = dt('User names are sanitized.');

      if ($messages) {
        $query->execute();
        $this->entityTypeManager->getStorage('user')->resetCache();
        foreach ($messages as $message) {
          $this->logger()->success($message);
        }
      }
    }
  }
}
