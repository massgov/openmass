<?php

namespace Drupal\mass_moderation_migration;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Runs a single migration batch.
 */
class MigrateBatch {

  /**
   * Maximum number of previous messages to display.
   */
  const MESSAGE_LENGTH = 1;

  /**
   * The processed items for one batch of a given migration.
   *
   * @var int
   */
  protected static $numProcessed = 0;

  /**
   * Ensure we only add the listeners once per request.
   *
   * @var bool
   */
  protected static $listenersAdded = FALSE;

  protected static $startTime;

  protected static $messageCount;

  /**
   * The maximum length in seconds to allow processing in a request.
   *
   * @var int
   *
   * @see self::run()
   */
  protected static $maxExecTime;

  /**
   * MigrateMessage instance to capture messages during the migration process.
   *
   * @var \Drupal\migrate_drupal_ui\Batch\MigrateMessageCapture
   */
  protected static $messages;

  /**
   * The follow-up migrations.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface[]
   */
  protected static $followUpMigrations;

  /**
   * Runs a single migrate batch import.
   *
   * @param int[] $initial_ids
   *   The full set of migration IDs to import.
   * @param array $config
   *   An array of additional configuration.
   * @param mixed $context
   *   The batch context.
   */
  public static function run(array $initial_ids, array $config, &$context) {
    static::$startTime = microtime(TRUE);

    if (!static::$listenersAdded) {
      $event_dispatcher = \Drupal::service('event_dispatcher');
      $event_dispatcher->addListener(MigrateEvents::POST_ROW_SAVE, [static::class, 'onPostRowSave']);
      $event_dispatcher->addListener(MigrateEvents::MAP_SAVE, [static::class, 'onMapSave']);

      static::$maxExecTime = ini_get('max_execution_time');
      if (static::$maxExecTime <= 0) {
        static::$maxExecTime = 60;
      }
      // Set an arbitrary threshold of 3 seconds (e.g., if max_execution_time is
      // 45 seconds, we will quit at 42 seconds so a slow item or cleanup
      // overhead don't put us over 45).
      static::$maxExecTime -= 3;
      static::$listenersAdded = TRUE;
    }
    if (!isset($context['sandbox']['migration_ids'])) {
      $context['sandbox']['max'] = count($initial_ids);
      $context['sandbox']['current'] = 1;
      // Total number processed for this migration.
      $context['sandbox']['num_processed'] = 0;
      // migration_ids will be the list of IDs remaining to run.
      $context['sandbox']['migration_ids'] = $initial_ids;
      $context['sandbox']['messages'] = [];
      $context['results']['failures'] = 0;
      $context['results']['successes'] = 0;
    }

    // Number processed in this batch.
    static::$numProcessed = 0;

    $migration_id = reset($context['sandbox']['migration_ids']);
    $configuration = [];

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id, $configuration);

    if ($migration) {
      static::$messages = new MigrateMessage();
      $executable = new MigrateExecutable($migration, static::$messages, ['feedback' => 100]);

      $migration_name = $migration_id;

      try {
        $migration_status = $executable->import();
      }
      catch (\Exception $e) {
        \Drupal::logger('mass_moderation_migration')->error($e->getMessage());
        $migration_status = MigrationInterface::RESULT_FAILED;
      }

      switch ($migration_status) {
        case MigrationInterface::RESULT_COMPLETED:
          // Store the number processed in the sandbox.
          $context['sandbox']['num_processed'] += static::$numProcessed;
          $message = new PluralTranslatableMarkup(
            $context['sandbox']['num_processed'], 'Upgraded @migration (processed 1 item total)', 'Upgraded @migration (processed @count items total)',
            ['@migration' => $migration_name]);
          $context['sandbox']['messages'][] = (string) $message;
          \Drupal::logger('mass_moderation_migration')->notice($message);
          $context['sandbox']['num_processed'] = 0;
          $context['results']['successes']++;

        case MigrationInterface::RESULT_INCOMPLETE:
          $context['sandbox']['messages'][] = (string) new PluralTranslatableMarkup(
            static::$numProcessed, 'Continuing with @migration (processed 1 item)', 'Continuing with @migration (processed @count items)',
            ['@migration' => $migration_name]);
          $context['sandbox']['num_processed'] += static::$numProcessed;
          break;

        case MigrationInterface::RESULT_STOPPED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation stopped by request');
          break;

        case MigrationInterface::RESULT_FAILED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation on @migration failed', ['@migration' => $migration_name]);
          $context['results']['failures']++;
          \Drupal::logger('mass_moderation_migration')->error('Operation on @migration failed', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_SKIPPED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation on @migration skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          \Drupal::logger('mass_moderation_migration')->error('Operation on @migration skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_DISABLED:
          // Skip silently if disabled.
          break;
      }

      // Unless we're continuing on with this migration, take it off the list.
      if ($migration_status != MigrationInterface::RESULT_INCOMPLETE) {
        array_shift($context['sandbox']['migration_ids']);
        $context['sandbox']['current']++;
      }

      // Only display the last MESSAGE_LENGTH messages, in reverse order.
      $message_count = count($context['sandbox']['messages']);
      $context['message'] = '';
      for ($index = max(0, $message_count - self::MESSAGE_LENGTH); $index < $message_count; $index++) {
        $context['message'] = $context['sandbox']['messages'][$index] . "<br />\n" . $context['message'];
      }
    }
    else {
      array_shift($context['sandbox']['migration_ids']);
      $context['sandbox']['current']++;
    }

    $context['finished'] = 1 - count($context['sandbox']['migration_ids']) / $context['sandbox']['max'];
  }

  /**
   * Callback executed when the Migrate Upgrade Import batch process completes.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of methods run in the batch.
   * @param string $elapsed
   *   The time to run the batch.
   */
  public static function finished($success, array $results, array $operations, $elapsed) {
    $successes = $results['successes'];
    $failures = $results['failures'];

    // If we had any successes log that for the user.
    if ($successes > 0) {
      \Drupal::messenger()->addStatus(\Drupal::translation()
        ->formatPlural($successes, 'Completed 1 batch successfully', 'Completed @count batches successfully'));
    }
    // If we had failures, log them and show the migration failed.
    if ($failures > 0) {
      \Drupal::messenger()->addStatus(\Drupal::translation()
        ->formatPlural($failures, '1 batch failed', '@count batches failed'));
      \Drupal::messenger()->addError(t('Upgrade process not completed'));
    }
    else {
      \Drupal::messenger()->addStatus(t('All moderation states have been restored.'));
    }
  }

  /**
   * Reacts to item import.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The post-save event.
   */
  public static function onPostRowSave(MigratePostRowSaveEvent $event) {
    // We want to interrupt this batch and start a fresh one.
    if ((time() - static::$startTime) > static::$maxExecTime) {
      $event->getMigration()->interruptMigration(MigrationInterface::RESULT_INCOMPLETE);
    }
  }

  /**
   * Counts up any map save events.
   *
   * @param \Drupal\migrate\Event\MigrateMapSaveEvent $event
   *   The map event.
   */
  public static function onMapSave(MigrateMapSaveEvent $event) {
    static::$numProcessed++;
  }

}
