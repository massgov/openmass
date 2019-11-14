<?php

namespace Drupal\mass_utility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FileSystemReportsController.
 *
 * Provides reports on status of file system.
 *
 * @package Drupal\mass_utility\Controller
 */
class FileSystemReportsController extends ControllerBase {

  protected $connection;

  protected $cacheTable = 'cache_ma_file_system';

  protected $pageLimit = 100;

  /**
   * FileSystemReportsController constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Creates static Controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Drupal container.
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\mass_utility\Controller\FileSystemReportsController
   *   The controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * The Extra Files report.
   *
   * @return array|bool
   *   Renderable array.
   */
  public function extraFiles() {
    $missing = $this->missingTable();
    if ($missing) {
      return $missing;
    }

    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
    $query = $this->connection->select($this->cacheTable, 'cf')->extend('Drupal\\Core\\Database\\Query\\PagerSelectExtender');
    $query->leftJoin('file_managed', 'fm', 'fm.uri = cf.uri');
    $query->isNull('fm.uri');
    $query->fields('cf');
    $query->orderBy('cf.uri');
    $query->limit($this->pageLimit);

    $total = $query->getCountQuery()->execute()->fetchField();
    $heading = [
      '#markup' => $this->t('@total extra files', [
        '@total' => $total,
      ]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $result = $query->execute();

    $table_header = [
      'Drupal URL',
      ['data' => $this->t('Filesize (KB)'), 'class' => 'text-align-right'],
      ['data' => $this->t('Last Modified'), 'class' => 'text-align-right'],
    ];

    $rows = [];
    foreach ($result as $record) {
      $rows[] = [
        $record->uri,
        [
          'data' => number_format($record->filesize / 1024, 1),
          'class' => 'text-align-right',
        ],
        [
          'data' => date('Y-m-d', $record->mtime),
          'class' => 'text-align-right',
        ],
      ];
    }

    $table = [
      '#type' => 'table',
      '#header' => $table_header,
      '#rows' => $rows,
      '#sticky' => TRUE,
    ];

    return [
      'heading' => $heading,
      'help' => $this->drushHelp(),
      'table' => $table,
      'pager' => ['#type' => 'pager'],
    ];
  }

  /**
   * The Missing Files report.
   *
   * @return array|bool
   *   Renderable array.
   */
  public function missingFiles() {
    $missing = $this->missingTable();
    if ($missing) {
      return $missing;
    }

    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
    $base_query = $this->connection->select('file_managed', 'fm')->extend('Drupal\\Core\\Database\\Query\\PagerSelectExtender');
    $base_query->leftJoin($this->cacheTable, 'cf', 'cf.uri = fm.uri');
    $base_query->addField('fm', 'fid');
    $base_query->isNull('cf.uri');
    $base_query->orderBy('fm.changed', 'DESC');
    $base_query->limit($this->pageLimit);

    $total = $base_query->getCountQuery()->execute()->fetchField();
    $heading = [
      '#markup' => $this->t('@total files listed in the file_managed table that were not found on the file system.  This may, or may not, represent a real problem for a given record.', [
        '@total' => $total,
      ]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $fids = $base_query->execute()->fetchCol();

    // Much faster to run the slow file info query only on the 100 fids found.
    $query = $this->connection->select('file_managed', 'fm');
    $query->leftJoin('users_field_data', 'u', 'u.uid = fm.uid');
    $query->leftJoin('file_usage', 'fu', 'fu.fid = fm.fid');
    $query->addField('fm', 'fid');
    $query->addField('fm', 'uri');
    $query->addExpression('MAX(fm.changed)', 'changed');
    $query->addExpression('MAX(fm.status)', 'status');
    $query->addExpression('GROUP_CONCAT(fu.type)', 'entity_types');
    $query->addExpression('GROUP_CONCAT(fu.id)', 'entity_ids');
    $query->addExpression('GROUP_CONCAT(u.name)', 'names');
    $query->condition('fm.fid', $fids, 'IN');
    $query->groupBy('fm.fid');
    $query->groupBy('fm.uri');
    $query->orderBy('fm.uri');

    $result = $query->execute();

    $table_header = [
      'File ID',
      'Drupal URL',
      $this->t('Reportedly Used In'),
      $this->t('Uploaded by'),
      $this->t('Updated'),
      $this->t('Status'),
    ];
    $rows = [];

    foreach ($result as $record) {
      if ($record->entity_types) {
        $tmp = [];
        $entity_types = explode(',', $record->entity_types);
        $entity_ids = explode(',', $record->entity_ids);
        if (count($entity_ids) > 10) {
          $entity_types = array_slice($entity_types, 0, 10);
          $entity_ids = array_slice($entity_ids, 0, 10);
          $tmp[] = $this->t('Often used. Showing first 10:');
        }

        // Default message.
        $entity_link = $this->t('Unable to determine usage');

        if (count($entity_types) == count($entity_ids)) {
          // Assumes GROUP_CONCAT will put records in same order for each field.
          $entity_list = array_combine($entity_ids, $entity_types);
          foreach ($entity_list as $entity_id => $entity_type) {
            if (in_array($entity_type, ['node', 'media', 'user'])) {
              $tmp[] = Link::fromTextAndUrl(ucwords($entity_type) . ' ' . $entity_id, URL::fromUri('entity:' . $entity_type . '/' . $entity_id));
            }
            else {
              $tmp[] = $this->t('Used in a @type', ['@type' => $entity_type]);
            }
          }
        }
        if (!empty($tmp)) {
          $entity_link = [
            '#theme' => 'item_list',
            '#items' => $tmp,
            '#list_type' => 'ul',
          ];
        }
      }
      else {
        $entity_link = $this->t('No usage found');
      }
      $names = array_unique(explode(',', $record->names));
      if (count($names) <= 1) {
        $author = $record->names;
      }
      else {
        $author = [
          '#theme' => 'item_list',
          '#items' => $names,
          '#list_type' => 'ul',
        ];
      }
      $rows[] = [
        $record->fid,
        $record->uri,
        ['data' => $entity_link],
        ['data' => $author],
        date('Y-m-d', $record->changed),
        $record->status ? 'Active' : 'To be deleted',
      ];
    }

    $table = [
      '#type' => 'table',
      '#header' => $table_header,
      '#rows' => $rows,
      '#sticky' => TRUE,
    ];

    return [
      'heading' => $heading,
      'help' => $this->drushHelp(),
      'table' => $table,
      'pager' => ['#type' => 'pager'],
    ];
  }

  /**
   * Checks for existence of the table that lists file system contents.
   *
   * @return array|bool
   *   Returns FALSE if table is present.
   *   Otherwise returns renderable help text.
   */
  protected function missingTable() {
    if (!$this->connection->schema()->tableExists($this->cacheTable)) {
      $text = 'Please run drush @cmd to scan the filesystem so the report can be generated.';
      return [
        '#markup' => $this->t($text, ['@cmd' => 'ma:update-file-list']),
        '#prefix' => '<div class="help"><p>',
        '#suffix' => '</p></div>',
      ];
    }
    return FALSE;
  }

  /**
   * Standard text regarding updates.
   *
   * @return array
   *   Renderable block of help text.
   */
  protected function drushHelp() {
    $text = 'This report is based on a static (i.e. possibly out-of-date) list of files that must be generated by a drush command.';
    $text .= ' Please run drush @cmd to update the list, or get a developer to do it for you.';
    return [
      '#markup' => $this->t($text, ['@cmd' => 'ma:update-file-list']),
      '#prefix' => '<div class="help"><p>',
      '#suffix' => '</p></div>',
    ];
  }

}
