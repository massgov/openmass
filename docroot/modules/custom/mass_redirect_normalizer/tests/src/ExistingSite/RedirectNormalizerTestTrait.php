<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_redirect_normalizer\Drush\Commands\MassRedirectNormalizerCommands;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mass_redirect_normalizer\RedirectLinkChangeLog;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drupal\redirect\Entity\Redirect;

/**
 * Shared helpers for redirect normalizer ExistingSite test classes.
 */
trait RedirectNormalizerTestTrait {

  /**
   * Creates a simple two-hop redirect chain to a target node.
   *
   * @return array{0: string, 1: string}
   *   Source start path and source final path (both without leading slash).
   */
  private function createRedirectChain($target): array {
    $sourceStart = 'chain-start-' . $this->randomMachineName();
    $sourceFinal = 'chain-final-' . $this->randomMachineName();

    $secondHop = Redirect::create();
    $secondHop->setRedirect('node/' . $target->id());
    $secondHop->setSource($sourceFinal);
    $secondHop->setLanguage($target->language()->getId());
    $secondHop->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $secondHop->save();
    $this->cleanupEntities[] = $secondHop;

    $firstHop = Redirect::create();
    $firstHop->setRedirect('/' . $sourceFinal);
    $firstHop->setSource($sourceStart);
    $firstHop->setLanguage($target->language()->getId());
    $firstHop->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $firstHop->save();
    $this->cleanupEntities[] = $firstHop;

    return [$sourceStart, $sourceFinal];
  }

  /**
   * Creates one redirect from source path to target local path.
   */
  private function createRedirect(string $sourcePath, string $targetPath): Redirect {
    $redirect = Redirect::create();
    $redirect->setSource(ltrim($sourcePath, '/'));
    $redirect->setRedirect($targetPath);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirect->save();
    $this->cleanupEntities[] = $redirect;
    return $redirect;
  }

  /**
   * Creates a published document media entity for tests.
   */
  private function createDocumentMedia(string $suffix, array $overrides = []) {
    $destination = 'public://redirect-normalizer-' . $suffix . '.txt';
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    \Drupal::service('file_system')->copy($src, $destination, TRUE);

    return $this->createMedia($overrides + [
      'title' => 'Doc ' . $suffix,
      'bundle' => 'document',
      'field_upload_file' => ['target_id' => $file->id()],
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

  /**
   * Builds the Drush command object with module services for testing.
   */
  private function createNormalizerCommand(): MassRedirectNormalizerCommands {
    return new MassRedirectNormalizerCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('mass_redirect_normalizer.enqueuer'),
      \Drupal::lock(),
      \Drupal::database(),
      \Drupal::state(),
    );
  }

  /**
   * Processes all pending redirect-link normalization queue items.
   */
  private function drainNormalizationQueue(): void {
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $worker = \Drupal::service('plugin.manager.queue_worker')
      ->createInstance(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Deletes every row in the redirect-link normalization queue.
   */
  private function purgeNormalizationQueue(): void {
    \Drupal::database()->delete('queue')
      ->condition('name', RedirectLinkQueueEnqueuer::QUEUE_NAME)
      ->execute();
  }

  /**
   * Ensures the change log table exists in ExistingSite tests.
   */
  private function ensureChangeLogTableExists(): void {
    $schema = \Drupal::database()->schema();
    $table = 'mass_redirect_normalizer_change_log';
    if (!$schema->tableExists($table)) {
      $definition = [
        'description' => 'Stores redirect normalization changes written by queue worker.',
        'fields' => [
          'id' => ['type' => 'serial', 'not null' => TRUE],
          'changed_at' => ['type' => 'int', 'not null' => TRUE],
          'source' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
          'entity_type' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE, 'default' => ''],
          'entity_id' => ['type' => 'int', 'not null' => TRUE],
          'bundle' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE, 'default' => ''],
          'field_name' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
          'delta' => ['type' => 'int', 'not null' => TRUE],
          'kind' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
          'before_value' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
          'after_value' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
          'status' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE, 'default' => 'succeeded'],
          'error_message' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'changed_at' => ['changed_at'],
          'entity' => ['entity_type', 'entity_id'],
          'source' => ['source'],
          'status' => ['status'],
        ],
      ];
      $schema->createTable($table, $definition);
      return;
    }

    if (!$schema->fieldExists($table, 'status')) {
      $schema->addField($table, 'status', [
        'type' => 'varchar',
        'length' => 16,
        'not null' => TRUE,
        'default' => 'succeeded',
      ]);
      $schema->addIndex($table, 'status', ['status']);
    }
    if (!$schema->fieldExists($table, 'error_message')) {
      $schema->addField($table, 'error_message', [
        'type' => 'text',
        'size' => 'big',
        'not null' => FALSE,
      ]);
    }
  }

  /**
   * Creates a published how-to with one method paragraph.
   *
   * @return array{0: \Drupal\node\NodeInterface, 1: int}
   *   Host node and method paragraph ID.
   */
  private function createHowToWithMethodParagraph(): array {
    $method = Paragraph::create([
      'type' => 'method',
      'field_method_type' => 'online',
      'field_method_details' => [
        'value' => '<p><a href="/placeholder">Need docs</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $contact = $this->createNode([
      'type' => 'contact_information',
      'title' => $this->randomMachineName(),
      'field_display_title' => 'Test contact',
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->cleanupEntities[] = $contact;

    $org = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    $node = $this->createNode([
      'type' => 'how_to_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_how_to_lede' => [
        'value' => 'Test lede',
        'format' => 'plain_text',
      ],
      'field_how_to_link_1' => [
        'uri' => 'https://www.example.com',
        'title' => 'Example',
      ],
      'field_how_to_methods_5' => [$method],
      'field_how_to_contacts_3' => [$contact],
      'field_organizations' => [$org],
    ]);
    $this->cleanupEntities[] = $node;

    return [$node, (int) $method->id()];
  }

  /**
   * Sets method paragraph body markup in storage (bypasses presave normalization).
   */
  private function setParagraphMethodDetailsMarkup(int $paragraphId, string $markup): void {
    $connection = \Drupal::database();
    foreach (['paragraph__field_method_details', 'paragraph_revision__field_method_details'] as $table) {
      $connection->update($table)
        ->fields(['field_method_details_value' => $markup])
        ->condition('entity_id', $paragraphId)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache([$paragraphId]);
  }

  /**
   * Breaks a node's paragraph reference while leaving nested embeds intact.
   */
  private function corruptNodeParagraphFieldTarget(int $nodeId, string $fieldName, int $fakeTargetId = 999999): void {
    $connection = \Drupal::database();
    foreach (['node__' . $fieldName, 'node_revision__' . $fieldName] as $table) {
      $connection->update($table)
        ->fields([$fieldName . '_target_id' => $fakeTargetId])
        ->condition('entity_id', $nodeId)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nodeId]);
  }

  /**
   * Reads a field value from a Views result row for the change log base table.
   */
  private function viewResultValue(object $row, string $field): mixed {
    $table = RedirectLinkChangeLog::TABLE;
    foreach ([$field, $table . '_' . $field] as $property) {
      if (property_exists($row, $property)) {
        return $row->{$property};
      }
    }
    return NULL;
  }

}
