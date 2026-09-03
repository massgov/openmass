<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_admin_audit_trail\Unit\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Form\FormState;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\mass_admin_audit_trail\Hook\MassAdminAuditTrailHooks;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Admin Audit Trail hook implementations.
 */
#[Group('mass_admin_audit_trail')]
final class MassAdminAuditTrailHooksTest extends UnitTestCase {

  /**
   * Conditions recorded from each mocked delete query.
   */
  private array $conditions = [];

  /**
   * Conditions recorded from the two-month OR group.
   */
  private array $twoMonthConditions = [];

  /**
   * Tests that cron builds the three retention cleanup queries.
   */
  public function testCronCleanupQueries(): void {
    $now = strtotime('2027-01-15 12:00:00 UTC');

    // Record the conditions added to each of the three cleanup queries.
    $login_query = $this->deleteQuery('login');
    $insert_update_query = $this->deleteQuery('insert_update');
    $two_month_query = $this->deleteQuery('two_month');
    $two_month_group = $this->twoMonthConditionGroup();
    $two_month_query->method('orConditionGroup')->willReturn($two_month_group);

    $database = $this->databaseReturningQueries(
      $login_query,
      $insert_update_query,
      $two_month_query,
    );

    // Run cron against a fixed time so each cutoff is deterministic.
    $this->hooks($database, $this->timeAt($now))->cron();

    // Every query must preserve deletes and the protected event types.
    $permanent_conditions = [
      ['operation', 'delete', '<>'],
      ['type', ['user_roles', 'block_content', 'config', 'menu', 'user'], 'NOT IN'],
    ];

    // Login records expire after three years.
    $this->assertSame([
      ...$permanent_conditions,
      ['operation', 'login', '='],
      ['created', strtotime('-3 years', $now), '<'],
    ], $this->conditions['login']);

    // Non-paragraph inserts and updates expire after twelve months.
    $this->assertSame([
      ...$permanent_conditions,
      ['operation', ['insert', 'update'], 'IN'],
      ['type', 'paragraph', '<>'],
      ['created', strtotime('-12 months', $now), '<'],
    ], $this->conditions['insert_update']);

    // Paragraphs and all remaining operations expire after two months.
    $this->assertSame([
      ...$permanent_conditions,
      [$two_month_group, NULL, '='],
      ['created', strtotime('-2 months', $now), '<'],
    ], $this->conditions['two_month']);

    // Exclude operations with longer retention unless they are paragraphs.
    $this->assertSame([
      ['operation', ['login', 'insert', 'update'], 'NOT IN'],
      ['type', 'paragraph', '='],
    ], $this->twoMonthConditions);
  }

  /**
   * Tests that cron declares its hook and removes the contributed cleanup.
   */
  public function testCronAttributes(): void {
    $method = new \ReflectionMethod(MassAdminAuditTrailHooks::class, 'cron');
    $hook = $method->getAttributes(Hook::class)[0]->newInstance();
    $remove = $method->getAttributes(RemoveHook::class)[0]->newInstance();

    $this->assertSame('cron', $hook->hook);
    $this->assertSame('cron', $remove->hook);
    $this->assertSame(ProceduralCall::class, $remove->class);
    $this->assertSame('admin_audit_trail_cron', $remove->method);
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
   * Creates the hook implementation with mocked infrastructure dependencies.
   */
  private function hooks(?Connection $database = NULL, ?TimeInterface $time = NULL): MassAdminAuditTrailHooks {
    return new MassAdminAuditTrailHooks(
      $database ?? $this->createMock(Connection::class),
      $time ?? $this->createMock(TimeInterface::class),
      $this->getStringTranslationStub(),
    );
  }

  /**
   * Creates a delete query that records its conditions by policy name.
   */
  private function deleteQuery(string $policy): Delete {
    $query = $this->createMock(Delete::class);
    $query->method('condition')->willReturnCallback(
      function ($field, $value = NULL, $operator = '=') use ($policy, $query): Delete {
        $this->conditions[$policy][] = [$field, $value, $operator];
        return $query;
      },
    );
    $query->expects($this->once())->method('execute');
    return $query;
  }

  /**
   * Creates the OR group used to identify two-month records.
   */
  private function twoMonthConditionGroup(): ConditionInterface {
    $group = $this->createMock(ConditionInterface::class);
    $group->method('condition')->willReturnCallback(
      function ($field, $value = NULL, $operator = '=') use ($group): ConditionInterface {
        $this->twoMonthConditions[] = [$field, $value, $operator];
        return $group;
      },
    );
    return $group;
  }

  /**
   * Creates a database that returns the supplied cleanup queries in order.
   */
  private function databaseReturningQueries(Delete ...$queries): Connection {
    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(count($queries)))
      ->method('delete')
      ->with('admin_audit_trail')
      ->willReturnOnConsecutiveCalls(...$queries);
    return $database;
  }

  /**
   * Creates a clock fixed at the supplied timestamp.
   */
  private function timeAt(int $timestamp): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($timestamp);
    return $time;
  }

}
