<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\mass_org_access\OrgMappingImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports org_page → Permission Group mappings from a "nodeid,termid" CSV.
 *
 * Saves the upload, parses it, and runs a batch that sets each org_page's
 * field_content_organization from the mapped terms (plus ancestors). Lives on
 * its own tab because it acts on content, not configuration.
 */
class OrgMappingImportForm extends FormBase {

  /**
   * The entity type manager.
   *
   * Not readonly: the managed_file element caches the form, so it is
   * serialized and rebuilt on submit, and DependencySerializationTrait must be
   * able to re-inject services on __wakeup() (readonly properties break that).
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The org → Permission Group mapping importer.
   */
  protected OrgMappingImporter $importer;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, OrgMappingImporter $importer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('mass_org_access.mapping_importer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mass_org_access_mapping_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#markup' => '<p>' . $this->t('Upload a CSV whose first row is the header <strong>nodeid,termid</strong>, followed by one mapping per row. Each organization page is set to the terms mapped to it (plus their ancestors), replacing any current value.') . '</p>',
    ];
    $form['template'] = [
      '#type' => 'link',
      '#title' => $this->t('Download CSV template'),
      '#url' => Url::fromRoute('mass_org_access.mapping_import_template'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Mapping CSV'),
      '#required' => TRUE,
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'csv'],
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import mappings'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // The managed_file element fires AJAX submits for its own Upload and Remove
    // buttons. Skip validation for those: setting an error would fail
    // validation and stop the Remove handler, leaving the file stuck in place.
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if (str_starts_with($trigger, 'csv_file')) {
      return;
    }

    $file = $this->loadUploadedFile($form_state);
    if (!$file) {
      // The managed_file #required constraint already reports a missing file.
      return;
    }

    $parsed = $this->importer->parse($file->getFileUri());
    if (!$parsed['valid_header']) {
      $form_state->setErrorByName('csv_file', $this->t('The file must start with the header row "nodeid,termid". Download the template for the expected format.'));
      return;
    }
    if (empty($parsed['mappings'])) {
      $form_state->setErrorByName('csv_file', $this->t('No "nodeid,termid" data rows were found below the header.'));
      return;
    }

    // Reuse the parsed result in submit instead of reading the file twice.
    $form_state->set('mapping_parsed', $parsed);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $parsed = $form_state->get('mapping_parsed');
    if (!$parsed || empty($parsed['mappings'])) {
      return;
    }

    $file = $this->loadUploadedFile($form_state);
    $log_uri = $this->importer->startLog($file ? $file->getFilename() : 'upload.csv', $parsed);

    // Summarize ignored rows in a single message — never one per row, which on
    // a large wrong file would overflow the session and break the request. The
    // per-row detail goes to the downloadable log instead.
    if (!empty($parsed['invalid'])) {
      $this->messenger()->addWarning($this->formatPlural(
        $parsed['invalid'],
        '1 row was not "nodeid,termid" and was ignored.',
        '@count rows were not "nodeid,termid" and were ignored.'
      ));
    }

    $operations = [];
    foreach ($parsed['mappings'] as $nid => $tids) {
      $operations[] = [[self::class, 'batchApply'], [$nid, $tids, $log_uri]];
    }
    batch_set([
      'title' => $this->t('Importing organization mappings'),
      'init_message' => $this->t('Applying @count organization mapping(s)…', ['@count' => count($operations)]),
      'operations' => $operations,
      'finished' => [self::class, 'batchFinished'],
    ]);
  }

  /**
   * Loads the uploaded CSV file entity, or NULL when none is present.
   */
  private function loadUploadedFile(FormStateInterface $form_state): ?FileInterface {
    $fids = (array) $form_state->getValue('csv_file');
    $fid = $fids ? (int) reset($fids) : 0;
    if (!$fid) {
      return NULL;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Batch operation: applies one org_page's mapping and logs the result.
   *
   * Runs outside the request that built the form, so the importer service is
   * resolved from the container here.
   */
  public static function batchApply(int $nid, array $tids, string $log_uri, array &$context): void {
    $importer = \Drupal::service('mass_org_access.mapping_importer');
    $result = $importer->apply($nid, $tids);
    $importer->logResult($log_uri, $result);

    $key = ($result['status'] ?? '') === OrgMappingImporter::IMPORTED ? 'imported' : 'skipped';
    $context['results'][$key] = ($context['results'][$key] ?? 0) + 1;
    $context['results']['log_uri'] = $log_uri;
  }

  /**
   * Batch finished callback: writes the log footer and offers the download.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();
    if (!$success) {
      $messenger->addError(t('The import did not complete successfully.'));
      return;
    }
    $imported = $results['imported'] ?? 0;
    $skipped = $results['skipped'] ?? 0;
    $log_uri = $results['log_uri'] ?? '';

    \Drupal::service('mass_org_access.mapping_importer')->finishLog($log_uri, $imported, $skipped);

    $messenger->addStatus(t('Updated Permission Groups on @imported organization page(s); @skipped node(s) skipped.', [
      '@imported' => $imported,
      '@skipped' => $skipped,
    ]));

    if ($log_uri !== '') {
      // Stash the log for the per-user download route, then link to it.
      \Drupal::service('tempstore.private')->get('mass_org_access')->set('import_log_uri', $log_uri);
      $messenger->addStatus(Link::createFromRoute(
        t('Download import log'),
        'mass_org_access.mapping_import_log'
      )->toString());
    }
  }

}
