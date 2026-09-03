<?php

namespace Drupal\Tests\mass_admin_audit_trail\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mass_admin_audit_trail\Hook\MassAdminAuditTrailHooks;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the custom Admin Audit Trail retention policy.
 *
 * @group mass_admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
class RetentionPolicyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_audit_trail',
    'mass_admin_audit_trail',
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
   * Tests that the attributed cron hook replaces the contributed cleanup.
   */
  public function testAttributedCronHookReplacesContribCron(): void {
    $implementations = [];
    $this->container->get('module_handler')->invokeAllWith(
      'cron',
      static function (callable $hook, string $module) use (&$implementations): void {
        $implementations[] = $module;
      },
    );

    $this->assertContains('mass_admin_audit_trail', $implementations);
    $this->assertNotContains('admin_audit_trail', $implementations);
  }

  /**
   * Tests the time-based audit trail retention rules and their precedence.
   */
  public function testRetentionRules(): void {
    $now = $this->container->get('datetime.time')->getRequestTime();
    $three_year_cutoff = strtotime('-3 years', $now);
    $twelve_month_cutoff = strtotime('-12 months', $now);
    $two_month_cutoff = strtotime('-2 months', $now);

    $logs = [
      // Delete operations and protected types are permanent.
      'old_delete' => ['node', 'delete', strtotime('-10 years', $now)],
      'old_user_roles' => ['user_roles', 'role_added', strtotime('-10 years', $now)],
      'old_block_content' => ['block_content', 'update', strtotime('-10 years', $now)],
      'old_config' => ['config', 'update', strtotime('-10 years', $now)],
      'old_menu' => ['menu', 'link update', strtotime('-10 years', $now)],
      'old_user' => ['user', 'update', strtotime('-10 years', $now)],
      // Login operations are retained for three years.
      'current_login' => ['authentication', 'login', $three_year_cutoff],
      'expired_login' => ['authentication', 'login', $three_year_cutoff - 1],
      // Non-paragraph inserts and updates are retained for twelve months.
      'current_insert' => ['node', 'insert', $twelve_month_cutoff],
      'expired_insert' => ['node', 'insert', $twelve_month_cutoff - 1],
      'current_update' => ['media', 'update', $twelve_month_cutoff],
      'expired_update' => ['media', 'update', $twelve_month_cutoff - 1],
      // Paragraphs, term inserts, and any other event get two months.
      'current_paragraph' => ['paragraph', 'update', $two_month_cutoff],
      'expired_paragraph' => ['paragraph', 'update', $two_month_cutoff - 1],
      'expired_paragraph_other' => ['paragraph', 'custom', $two_month_cutoff - 1],
      'current_term_insert' => ['taxonomy', 'term insert', $two_month_cutoff],
      'expired_term_insert' => ['taxonomy', 'term insert', $two_month_cutoff - 1],
      'current_other' => ['authentication', 'logout', $two_month_cutoff],
      'expired_other' => ['authentication', 'logout', $two_month_cutoff - 1],
    ];
    foreach ($logs as $reference => [$type, $operation, $created]) {
      $this->insertLog($reference, $type, $operation, $created);
    }

    $this->hooks()->applyRetentionPolicy();

    $remaining = $this->container->get('database')
      ->select('admin_audit_trail', 'aat')
      ->fields('aat', ['ref_char'])
      ->orderBy('lid')
      ->execute()
      ->fetchCol();

    $this->assertSame([
      'old_delete',
      'old_user_roles',
      'old_block_content',
      'old_config',
      'old_menu',
      'old_user',
      'current_login',
      'current_insert',
      'current_update',
      'current_paragraph',
      'current_term_insert',
      'current_other',
    ], $remaining);
  }

  /**
   * Tests that the report explains its retention periods.
   */
  public function testRetentionPeriodsAppearOnReport(): void {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn('admin_audit_trail');

    $this->hooks()->viewsPreRender($view);

    $policy = $view->attachment_before['mass_admin_audit_trail_retention_policy'];
    $this->assertSame('status_messages', $policy['#theme']);
    $this->assertSame('Audit trail retention periods', (string) $policy['#status_headings']['info']);
    $items = $policy['#message_list']['info'][0]['#items'];
    $this->assertSame([
      'Delete operations: forever',
      'User roles, block content, configuration, menu, and user types: forever',
      'Login operations: 3 years',
      'Insert and update operations, except paragraphs: 12 months',
      'Paragraph events: 2 months',
      'Term insert operations: 2 months',
      'All other events: 2 months',
    ], array_map('strval', $items));
  }

  /**
   * Tests that the overridden row-limit setting is visible but locked.
   */
  public function testRowLimitSettingIsLocked(): void {
    $form = [
      'admin_audit_trail_row_limit' => [
        '#type' => 'select',
        '#title' => 'Audit Trail log messages to keep',
        '#options' => [0 => 'All', 100 => 100],
      ],
    ];

    $this->hooks()->settingsFormAlter(
      $form,
      new FormState(),
      'admin_audit_trail_settings_form',
    );

    $element = $form['admin_audit_trail_row_limit'];
    $this->assertSame('select', $element['#type']);
    $this->assertSame([0 => 'All', 100 => 100], $element['#options']);
    $this->assertTrue($element['#disabled']);
    $this->assertSame(
      'This setting is overridden by the Mass.gov Admin Audit Trail module, which applies the time-based retention periods shown on the audit trail report.',
      (string) $element['#description'],
    );
  }

  /**
   * Gets the attributed hook service.
   */
  private function hooks(): MassAdminAuditTrailHooks {
    return $this->container->get(MassAdminAuditTrailHooks::class);
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
   * @param int $created
   *   The time the event was logged.
   */
  private function insertLog(string $reference, string $type, string $operation, int $created): void {
    $this->container->get('database')
      ->insert('admin_audit_trail')
      ->fields([
        'type' => $type,
        'operation' => $operation,
        'path' => '',
        'ref_char' => $reference,
        'description' => $reference,
        'uid' => 0,
        'created' => $created,
      ])
      ->execute();
  }

}
