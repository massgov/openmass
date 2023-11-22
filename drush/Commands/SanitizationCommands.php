<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drush\Attributes as CLI;
use Drush\Drupal\Commands\sql\SanitizeCommands;
use MassGov\Sanitation\ContentModerationSanitizer;
use MassGov\Sanitation\MediaSanitizer;
use MassGov\Sanitation\SqlEntitySanitizer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra sanitization commands for drush sql:sanitize.
 */
class SanitizationCommands extends DrushCommands {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected Connection $database
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self
  {
    $commandHandler = new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('database')
    );

    return $commandHandler;
  }

  /**
   * Sanitize unpublished entities and old revisions of entities.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function sanitizeEntities($result, CommandData $commandData): void {
    if (!$commandData->input()->getOption('sanitize-entities')) {
      return;
    }
    $types = array_keys($this->entityTypeManager->getDefinitions());

    // Deleting menu link content sometimes results in rendering issues with
    // super-sanitized databases, so excluding it from sanitization.
    $idx = array_search('menu_link_content', $types);
    if ($idx !== -1) {
      unset($types[$idx]);
    }

    // This is a bit of a hack, but content moderation needs to go dead last
    // so it can clean up after all the other stuff has been deleted.
    $idx = array_search('content_moderation_state', $types);
    if ($idx !== -1) {
      unset($types[$idx]);
      $types[] = 'content_moderation_state';
    }

    foreach ($types as $type) {
      if ($sanitizer = $this->getEntitySanitizer($type)) {
        $sanitizer->sanitize();
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
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
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
      ->condition('collection', 'deploy_hook', '!=')
      // State will be handled separately.
      ->condition('collection', 'state', '!=')
      ->execute();

    $this->database->delete('key_value')
      ->condition('collection', 'state')
      ->condition('name', 'mass_admin_pages.%', 'NOT LIKE')
      ->condition('name', 'mass_feedback_form.%', 'NOT LIKE')
      ->execute();
  }

  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(&$messages, InputInterface $input) {
    if ($input->getOption('sanitize-keyvalue')) {
      $messages[] = 'Sanitize key_value table.';
    }
    if ($input->getOption('sanitize-entities')) {
      $messages[] = 'Sanitize unpublished and old revisions of entities.';
    }
    if ($input->getOption('sanitize-names')) {
      $messages[] = 'Sanitize user names.';
    }
    if ($input->getOption('sanitize-roles')) {
      $messages[] = 'Remove user-role assignments.';
    }
    if ($input->getOption('sanitize-unpublish-reminders')) {
      $messages[] = 'Remove unpublish reminders.';
    }
  }

  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: SanitizeCommands::SANITIZE)]
  #[CLI\Option(name: 'sanitize-keyvalue', description: 'Sanitize key_value table.')]
  #[CLI\Option(name: 'sanitize-entities', description: 'Remove unpublished entities and old revisions of entities.')]
  #[CLI\Option(name: 'sanitize-roles', description: 'Remove user role assignments.')]
  #[CLI\Option(name: 'sanitize-unpublish-reminders', description: 'Remove unpublish reminders.')]
  #[CLI\Option(name: 'sanitize-names', description: 'Replace usernames with user+1, user+2, etc.')]
  public function options($options = ['sanitize-keyvalue' => FALSE, 'sanitize-entities' => FALSE, 'sanitize-names' => FALSE, 'sanitize-roles' => FALSE, 'sanitize-unpublish-reminders' => FALSE]): void {
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
   */
  #[CLI\Command(name: 'ma:checkmissing')]
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
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function userRole($result, CommandData $commandData) {
    if (!$commandData->input()->getOption('sanitize-roles')) {
      return;
    }
    // Remove all authors roles from the database.
    $role = Database::getConnection()->truncate('user__roles')->execute();
    if ($role) {
      $this->logger()->error(dt('The user roles have not been truncated.'));
    } else {
      $this->logger()->success(dt('The user roles have been truncated.'));
    }
  }

  /**
   * Sanitize the database table for the unpublish reminders.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function unpublishReminders($result, CommandData $commandData) {
    if (!$commandData->input()->getOption('sanitize-unpublish-reminders')) {
      return;
    }
    // Remove all from the mass_unpublish_reminders table.
    $reminders = Database::getConnection()->truncate('mass_unpublish_reminders')->execute();
    if ($reminders) {
      $this->logger()->error(dt('The unpublish reminders have not been truncated.'));
    } else {
      $this->logger()->success(dt('The unpublish reminders have been truncated.'));
    }
  }

  /**
   * Sanitize usernames.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function userName($result, CommandData $commandData) {
    if (!$commandData->input()->getOption('sanitize-names')) {
      return;
    }

    $this->database->update('users_field_data')
      ->condition('uid', 0, '>')
      ->expression('name', "concat('user+', uid)")
      ->execute();
    $this->logger()->success(dt('User names are sanitized.'));
    $this->entityTypeManager->getStorage('user')->resetCache();
  }

  /**
   * Test an option value to see if it is disabled.
   */
  protected function isEnabled($value): bool {
    return $value != 'no' && $value != '0';
  }

}
