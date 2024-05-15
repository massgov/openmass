<?php

namespace Drupal\mass_utility\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;

/**
 * Class FileSystemCommands.
 *
 * Drush commands for dealing with the file system.
 *
 * @package Drupal\mass_utility\Commands
 */
class FileSystemCommands extends DrushCommands {

  use AutowireTrait;

  private $batch = [];

  private $tableName = 'cache_ma_file_system';

  private $schema = [
    'fields' => [
      'cfid' => [
        'type' => 'serial',
        'unsigned' => TRUE,
        'not null' => TRUE,
      ],
      'uri' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'length' => 255,
        'binary' => TRUE,
      ],
      'filesize' => [
        'type' => 'int',
        'size' => 'big',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ],
      'mtime' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ],
    ],
    'primary key' => ['cfid'],
    // Same as uri index on file_managed table.
    'indexes' => ['uri' => [['uri', 191]]],
    'description' => 'Snapshot index of filesystem created by drush ma:update-file-list',
  ];

  public function __construct(protected Connection $database, protected FileSystemInterface $file_system) {
    parent::__construct();
  }

  /**
   * Drush command to update the cache_ma_file_system table.
   *
   * @command ma:update-file-list
   */
  public function fileList() {
    $this->ensureTable();
    foreach ($this->getFiles() as $file) {
      $this->batchWrite($file);
    }
    $this->doBatch();
  }

  /**
   * Writes records in batches of 200.
   *
   * @param array $record
   *   The file information to be saved.
   */
  private function batchWrite(array $record) {
    $this->batch[] = $record;
    if (count($this->batch) > 200) {
      $this->doBatch();
    }
  }

  /**
   * Runs insert query for all records in $this->batch.
   */
  private function doBatch() {
    try {
      $query = $this->database->insert($this->tableName);
      $query->fields(['uri', 'filesize', 'mtime']);
      while ($record = array_pop($this->batch)) {
        $query->values($record);
      }
      $query->execute();
    }
    catch (\Exception $e) {
      /* @noinspection PhpUndefinedMethodInspection */
      $this->output()->writeln("Error saving file records: " . $e->getMessage());
    }
  }

  /**
   * Provides a file iterator.
   *
   * @return \EmptyIterator|\Generator
   *   The iterator.
   */
  private function getFiles() {

    // Get the base locations for public and private files.
    $directories = [
      'public://' => $this->fileSystem->realpath('public://'),
      'private://' => $this->fileSystem->realpath('private://'),
    ];

    // For each base location, set up crawler, then yield record for each file.
    foreach ($directories as $scheme => $directory) {
      if (empty($directory)) {
        /* @noinspection PhpUndefinedMethodInspection */
        $this->output()->writeln("Skipping $scheme, no directory defined");
        continue;
      }
      /* @noinspection PhpUndefinedMethodInspection */
      $this->output()->writeln("Scanning $scheme -- $directory");
      // @see https://symfony.com/doc/2.8/components/finder.html
      $finder = new Finder();
      /*
       * Excludes directories containing generated files
       * not recorded in file_managed.
       * This list may need to be updated from time to time.
       */
      $finder->files()->in($directory)->exclude([
        'config_*',
        'css',
        'js',
        'migration',
        'php',
        'styles',
        'topic-featured-images',
        'xmlsitemap',
      ]);
      /** @var \Symfony\Component\Finder\SplFileInfo $file */
      foreach ($finder as $file) {
        yield [
          'uri' => $scheme . $file->getRelativePathname(),
          'filesize' => $file->getSize(),
          'mtime' => $file->getMTime(),
        ];
      }
    }
    return new \EmptyIterator();
  }

  /**
   * Reset the cache_ma_file_system table.
   */
  private function ensureTable() {
    $schema = $this->database->schema();
    if ($schema->tableExists($this->tableName)) {
      $schema->dropTable($this->tableName);
    }
    $schema->createTable($this->tableName, $this->schema);
  }

}
