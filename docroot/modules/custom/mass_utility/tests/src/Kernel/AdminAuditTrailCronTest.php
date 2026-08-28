<?php

namespace Drupal\Tests\mass_utility\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Admin Audit Trail cron override.
 *
 * @group mass_utility
 */
class AdminAuditTrailCronTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_audit_trail',
    'mass_utility',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('admin_audit_trail', 'admin_audit_trail');
  }

  /**
   * Tests that node and media deletes are excluded from count and cleanup.
   */
  public function testNodeAndMediaDeleteOperationsAreRetained(): void {
    $this->config('admin_audit_trail.settings')
      ->set('admin_audit_trail_row_limit', 2)
      ->save();

    $logs = [
      'old_update' => ['node', 'update'],
      'old_node_delete' => ['node', 'delete'],
      'old_other_delete' => ['taxonomy', 'delete'],
      'old_media_delete' => ['media', 'delete'],
      'new_update' => ['node', 'update'],
      'new_other_delete' => ['taxonomy', 'delete'],
      'new_node_delete' => ['node', 'delete'],
      'new_insert' => ['node', 'insert'],
    ];
    foreach ($logs as $reference => [$type, $operation]) {
      $this->insertLog($reference, $type, $operation);
    }

    mass_utility_admin_audit_trail_cron();

    $remaining = $this->container->get('database')
      ->select('admin_audit_trail', 'aat')
      ->fields('aat', ['ref_char'])
      ->orderBy('lid')
      ->execute()
      ->fetchCol();

    $this->assertSame([
      'old_node_delete',
      'old_media_delete',
      'new_other_delete',
      'new_node_delete',
      'new_insert',
    ], $remaining);
  }

  /**
   * Inserts an audit trail record.
   *
   * @param string $reference
   *   The unique reference for the test record.
   * @param string $type
   *   The logged entity type.
   * @param string $operation
   *   The logged operation.
   */
  private function insertLog(string $reference, string $type, string $operation): void {
    $this->container->get('database')
      ->insert('admin_audit_trail')
      ->fields([
        'type' => $type,
        'operation' => $operation,
        'path' => '',
        'ref_char' => $reference,
        'description' => $reference,
        'uid' => 0,
        'created' => 0,
      ])
      ->execute();
  }

}
