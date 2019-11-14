<?php

namespace Drupal\mass_utility\Commands;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\mass_utility\RedirectReplacement\FieldSearcher;
use Drupal\mass_utility\RedirectReplacement\FindReplacer;
use Drupal\mass_utility\RedirectReplacement\Indexer;
use Drupal\mass_utility\RedirectReplacement\OneToOneProvider;
use Drupal\mass_utility\RedirectReplacement\StringSearcher;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Drush commands for legacy redirect replacement.
 *
 * Note: Replacement makes heavy use of PHP generators to keep the memory usage
 * to a reasonable level. Generators are functions that "yield" a single result
 * at a time, allowing for huge datasets to be iterated through without running
 * out of memory.
 *
 * @see http://php.net/manual/en/language.generators.overview.php
 */
class RedirectMigrationCommands extends DrushCommands implements LoggerAwareInterface {
  use LoggerAwareTrait;

  private $manager;
  private $database;
  private $dateFormatter;

  private $index;
  private $fieldSearcher;

  /**
   * Constructor.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $manager, DateFormatterInterface $dateFormatter) {
    $this->database = $connection;
    $this->manager = $manager;
    $this->dateFormatter = $dateFormatter;

    $this->fieldSearcher = new FieldSearcher($this->database, $this->manager, $this->logger);
    $this->index = new Indexer($this->database, $this->logger);
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger) {
    $this->fieldSearcher->setLogger($logger);
    $this->index->setLogger($logger);
  }

  /**
   * Searches for URL strings in the content.
   *
   * @throws \Exception
   *
   * @usage drush ma:batch-legacy-redirect-replacements
   *   Reads 404 data from supplied google analytics csv file, and adds root cause beside each url.
   *
   * @command ma:search-legacy-redirect-replacements
   */
  public function search(string $str) {
    $finder = new StringSearcher([$str]);
    $items = $this->fieldSearcher->search($finder);
    $m = t('Discovered @count urls matching', [
      '@count' => iterator_count($items)
    ]);
    $this->output->writeln((string) $m);
    $this->writeln((string) t('Memory usage: @mem', [
      '@mem' => format_size(memory_get_peak_usage(TRUE))
    ]));
  }

  /**
   * Replace 1-1 redirects in field content.
   *
   * @command ma:replace-redirects
   */
  public function replaceRedirects() {
    // Build a searcher for discovering links in the content.
    $stringSearcher = $this->getStringSearcher();
    $this->fieldSearcher->journal()->reset();

    // Step 1: Build up an index of all the strings we WANT to replace.  This
    // includes legacy redirects, document redirects, Drupal redirects, media,
    // nodes, and files.
    $this->timed('Building 1-1 index', function () {
      $this->index->buildOneToOneIndex($this->getOneToOneRedirects());
    });

    // Step 2: Build up an index of all the occurrences of links matching the
    // searcher in the database, and check whether replacing each one is
    // "safe".
    $this->timed('Building content index', function () use ($stringSearcher) {
      $discovered = $this->fieldSearcher->search($stringSearcher);
      $this->index->buildContentIndex($discovered);
    });

    // Step 3: Take the intersection of the things we WANT to replace with the
    // things that are discovered in the content and are SAFE to replace, and
    // run replacements on those strings.
    $this->timed('Replacing', function () use ($stringSearcher) {
      // Finally, do the replacement.
      $stringSearcher->setReplacements($this->index->getSafeReplacements());
      $this->fieldSearcher->replace($stringSearcher);
    });

    // Finally, rebuild the content index one last time so we can use it to tell
    // what wasn't replaced.
    $this->timed('Building final content index', function () use ($stringSearcher) {
      $discovered = $this->fieldSearcher->search($stringSearcher);
      $this->index->buildContentIndex($discovered);
    });

    $filename = sprintf('/tmp/journal-%s.json', time());
    $this->fieldSearcher->journal()->flush($filename);

    $this->writeln((string) t('Finished.  Journal file saved to @file. Memory usage: @mem', [
      '@file' => $filename,
      '@mem' => format_size(memory_get_peak_usage(TRUE))
    ]));
  }

  /**
   * Execute an operation, timing it and printing start/finish messages.
   */
  private function timed($name, callable $op) {
    $this->writeln((string) t('[Start] - @op', [
      '@op' => $name,
    ]));
    Timer::start('timedoperation');
    $op();
    $this->writeln((string) t('[Finish] @op in @time', [
      '@op' => $name,
      '@time' => $this->dateFormatter->formatInterval(Timer::read('timedoperation') / 1000)
    ]));
  }

  /**
   * Get a string searcher instance for use in the current command.
   */
  private function getStringSearcher() {
    // Build a searcher for discovering links in the content.
    return new StringSearcher([
      'http://www.mass.gov',
      'https://www.mass.gov',
      'http://pilot.mass.gov',
      'https://pilot.mass.gov',
      'http://edit.mass.gov',
      'https://edit.mass.gov',
      'http://mass.gov',
      'https://mass.gov',
    ]);
  }

  /**
   * Returns an iterable containing every 1-1 redirect mapping we know about.
   *
   * @return \iterable
   *   All 1-1 redirects.
   */
  private function getOneToOneRedirects() {
    $one_to_one = new OneToOneProvider($this->database);

    return $one_to_one::combine([
      $one_to_one->getLegacyRedirects(),
      $one_to_one->getDocumentRedirects(),
    ]);
  }

  /**
   * Runs a check to ensure that all nodes on the site pass validation.
   *
   * Will dump nodes with validation errors to a CSV for analysis.
   *
   * @command ma:validate-all
   */
  public function validateAll() {
    $account = User::load(1);
    \Drupal::currentUser()->setAccount($account);
    \Drupal::service('session')->migrate();
    \Drupal::service('session')->set('uid', $account->id());
    \Drupal::moduleHandler()->invokeAll('user_login', [$account]);

    $builder = \Drupal::formBuilder();
    /** @var \Drupal\Core\Form\FormValidatorInterface $validator */
    $validator = \Drupal::service('form_validator');

    $index = 0;
    $size = 50;
    $handle = fopen('/tmp/validation.csv', 'w');
    $formObject = $this->manager->getFormObject('node', 'edit');

    while ($chunk = $this->getValidationChunk($index, $size)) {
      $index += $size;
      self::timed('chunk', function () use ($chunk, $builder, $validator, $formObject, $handle) {
        foreach ($chunk as $node) {
          $formObject->setEntity($node);
          $formState = new FormState();
          $form = $builder->buildForm($formObject, $formState);
          // Pretend the submit button was pressed.
          $formState->setTriggeringElement($form['actions']['submit']);
          $formState->setUserInput($formState->getValues());
          // Do not do CSRF token validation.
          $form['#token'] = NULL;
          // Pretend we're hitting the "Save as draft" button.
          if ($node->moderation_state->target_id === 'unpublished') {
            $formState->setValue('moderation_state', ['target_id' => 'draft']);
          }
          // Pretend we're hitting the "Save and restore from trash" button.
          elseif ($node->moderation_state->target_id === 'trash') {
            $formState->setValue('moderation_state', ['target_id' => 'unpublished']);
          }

          $validator->validateForm($formObject->getFormId(), $form, $formState);
          if ($errors = $formState->getErrors()) {
            fputcsv($handle, [
              $node->id(),
              'https://edit.mass.gov/node/' . $node->id() . '/edit',
              implode("\n", $errors)
            ]);
            $this->writeln("Found validation errors: " . implode(", ", $errors));
          }
          $this->writeln("Finished validating {$node->id()}");
        }
      });

    }
    fclose($handle);
  }

  /**
   * Retrieves a chunk of nodes to be checked for redirects.
   */
  private function getValidationChunk($start, $length = 50) {
    $storage = $this->manager->getStorage('node');
    $ids = $storage->getQuery()
      ->range($start, $length)
      ->execute();
    return $storage->loadMultiple($ids);
  }

}
