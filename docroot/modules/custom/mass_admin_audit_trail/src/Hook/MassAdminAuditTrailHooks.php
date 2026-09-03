<?php

declare(strict_types=1);

namespace Drupal\mass_admin_audit_trail\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for the Mass.gov Admin Audit Trail module.
 */
class MassAdminAuditTrailHooks {

  use StringTranslationTrait;

  /**
   * Audit trail event types that are retained permanently.
   */
  private const PERMANENT_TYPES = [
    'user_roles',
    'block_content',
    'config',
    'menu',
    'user',
  ];

  /**
   * Constructs the hook service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Applies the audit trail retention policy during cron.
   */
  #[Hook('cron')]
  #[RemoveHook('cron', ProceduralCall::class, 'admin_audit_trail_cron')]
  public function cron(): void {
    $this->applyRetentionPolicy();
  }

  /**
   * Applies the audit trail retention policy.
   */
  public function applyRetentionPolicy(): void {
    $now = $this->time->getRequestTime();

    // Login operations are retained for three years.
    $query = $this->database->delete('admin_audit_trail');
    $this->excludePermanent($query);
    $query
      ->condition('operation', 'login')
      ->condition('created', strtotime('-3 years', $now), '<')
      ->execute();

    // Non-paragraph inserts and updates are retained for twelve months.
    $query = $this->database->delete('admin_audit_trail');
    $this->excludePermanent($query);
    $query
      ->condition('operation', ['insert', 'update'], 'IN')
      ->condition('type', 'paragraph', '<>')
      ->condition('created', strtotime('-12 months', $now), '<')
      ->execute();

    // Paragraph events, term inserts, and all other events get two months.
    $query = $this->database->delete('admin_audit_trail');
    $this->excludePermanent($query);
    $two_month_operations = $query->orConditionGroup()
      ->condition('operation', ['login', 'insert', 'update'], 'NOT IN')
      ->condition('type', 'paragraph');
    $query
      ->condition($two_month_operations)
      ->condition('created', strtotime('-2 months', $now), '<')
      ->execute();
  }

  /**
   * Locks the overridden row-limit option on the settings form.
   */
  #[Hook('form_admin_audit_trail_settings_form_alter')]
  public function settingsFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['admin_audit_trail_row_limit']['#disabled'] = TRUE;
    $form['admin_audit_trail_row_limit']['#description'] = $this->t('This setting is overridden by the Mass.gov Admin Audit Trail module, which applies the time-based retention periods shown on the audit trail report.');
  }

  /**
   * Displays the retention periods on the audit trail report.
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() !== 'admin_audit_trail') {
      return;
    }

    $view->attachment_before['mass_admin_audit_trail_retention_policy'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'info' => [
          [
            '#theme' => 'item_list',
            '#items' => [
              $this->t('Delete operations: forever'),
              $this->t('User roles, block content, configuration, menu, and user types: forever'),
              $this->t('Login operations: 3 years'),
              $this->t('Insert and update operations, except paragraphs: 12 months'),
              $this->t('Paragraph events: 2 months'),
              $this->t('Term insert operations: 2 months'),
              $this->t('All other events: 2 months'),
            ],
          ],
        ],
      ],
      '#status_headings' => [
        'info' => $this->t('Audit trail retention periods'),
      ],
    ];
  }

  /**
   * Excludes permanently retained records from a delete query.
   */
  private function excludePermanent(Delete $query): void {
    $query
      ->condition('operation', 'delete', '<>')
      ->condition('type', self::PERMANENT_TYPES, 'NOT IN');
  }

}
