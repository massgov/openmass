<?php

namespace Drupal\mass_redirect_normalizer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\mass_redirect_normalizer\RedirectLinkChangeLog;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Report pages for redirect link normalization change log.
 */
final class RedirectLinkChangeLogController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly RedirectLinkChangeLog $changeLog,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('mass_redirect_normalizer.change_log'),
    );
  }

  /**
   * Builds the report page.
   */
  public function report(): array {
    $header = [
      $this->t('Changed'),
      $this->t('Source'),
      $this->t('Entity'),
      $this->t('Bundle'),
      $this->t('Field'),
      $this->t('Delta'),
      $this->t('Kind'),
      $this->t('Before'),
      $this->t('After'),
    ];

    $query = $this->database->select(RedirectLinkChangeLog::TABLE, 'l')
      ->fields('l', [
        'changed_at',
        'source',
        'entity_type',
        'entity_id',
        'bundle',
        'field_name',
        'delta',
        'kind',
        'before_value',
        'after_value',
      ])
      ->orderBy('id', 'DESC')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50);

    $rows = [];
    foreach ($query->execute() as $record) {
      $rows[] = [
        date('Y-m-d H:i:s', (int) $record->changed_at),
        $record->source,
        $record->entity_type . ':' . $record->entity_id,
        $record->bundle,
        $record->field_name,
        $record->delta,
        $record->kind,
        $record->before_value,
        $record->after_value,
      ];
    }

    $actions = [];
    $actions[] = Link::fromTextAndUrl($this->t('Export CSV'), Url::fromRoute('mass_redirect_normalizer.change_log_export'))->toRenderable();
    $actions[] = Link::fromTextAndUrl($this->t('Clear all records'), Url::fromRoute('mass_redirect_normalizer.change_log_clear'))->toRenderable();

    return [
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $actions,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No changes logged yet.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

  /**
   * Exports report and redirects back with status message.
   */
  public function export() {
    $uri = $this->changeLog->exportCsv();
    $this->messenger()->addStatus($this->t('Exported CSV to @uri', ['@uri' => $uri]));
    return $this->redirect('mass_redirect_normalizer.change_log');
  }

}
