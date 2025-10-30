<?php

namespace Drupal\mass_bulk_file_replace\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Step 1: Upload form using DropzoneJS.
 */
class ReplaceUploadForm extends FormBase {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory */
  protected $tempStoreFactory;

  public function __construct(PrivateTempStoreFactory $tempStoreFactory) {
    $this->tempStoreFactory = $tempStoreFactory;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_bulk_file_replace_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Derive allowed extensions from the Media "document" bundle field settings.
    // We read the `file_extensions` setting from the file field `field_upload_file`.
    $fallback_exts = [
      'csv', 'doc', 'docm', 'docx', 'dot', 'dotx', 'dwg', 'geojson', 'gif', 'json', 'jpg', 'kml', 'kmz', 'mp3', 'mp4', 'mpp', 'msg', 'odf', 'ods', 'odt', 'pdf', 'png', 'pps', 'ppsx', 'potx', 'ppt', 'pptm', 'pptx', 'ppsm', 'prx', 'pub', 'rdf', 'rfa', 'rte', 'rtf', 'tiff', 'tsv', 'txt', 'xls', 'xlsb', 'xlsm', 'xlsx', 'xml', 'zip', 'rpt'
    ];

    $exts = $fallback_exts;
    try {
      $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', 'document');
      if (isset($definitions['field_upload_file'])) {
        $exts_setting = (string) $definitions['field_upload_file']->getSetting('file_extensions');
        if ($exts_setting !== '') {
          $parsed = preg_split('/\s+/', trim(strtolower($exts_setting))) ?: [];
          // Normalize, unique, and keep only non-empty values.
          $parsed = array_values(array_filter(array_unique($parsed)));
          if (!empty($parsed)) {
            $exts = $parsed;
          }
        }
      }
    }
    catch (\Throwable $e) {
      // Fallback silently to the predefined list if the bundle/field is missing.
    }

    // Build values for server-side validator and client-side dropzone.
    // Drupal's file_validate_extensions expects a SPACE-separated string.
    $exts_space = implode(' ', $exts);
    // Dropzone acceptedFiles wants a list of .ext tokens separated by commas.
    $accepted_files = '.' . implode(',.', $exts);

    $form['upload'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('Step 1: Upload replacement files'),
      '#description' => $this->t('Upload one or more replacement files. Each file will be matched to an existing media item by its filename, which must include the media ID.'),
      '#multiple' => TRUE,
      '#dropzone_description' => $this->t('Drag files here or click to upload.'),
      // Server-side validators:
      '#upload_validators' => [
        // 128MB per file
        'file_validate_size' => [128 * 1024 * 1024],
        // Use derived extensions:
        'file_validate_extensions' => [$exts_space],
      ],
      '#upload_location' => 'temporary://mass_bulk_file_replace',
      // Client-side Dropzone settings:
      '#dropzonejs_settings' => [
        'parallelUploads' => 1,
      // MB
        'maxFilesize' => 128,
        // Mirror the allowed types for client-side filtering:
        'acceptedFiles' => $accepted_files,
      ],
      '#extensions' => $exts_space,
    ];

    $form['instructions'] = [
      '#markup' => $this->t('<p><strong>Instructions:</strong> Make sure each uploaded file includes the text "DO_NOT_CHANGE_THIS_MEDIA_ID_{ID}" in its filename (e.g., <em>something_DO_NOT_CHANGE_THIS_MEDIA_ID_123.pdf</em>). This will be used to identify the correct media entity to replace.</p>'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to replacement confirmation'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $uploaded = $form_state->getValue('upload');
    $files = $uploaded['uploaded_files'] ?? [];

    if (empty($files) || !is_array($files)) {
      return;
    }

    $mid_counts = [];
    foreach ($files as $item) {
      $filename = (string) ($item['filename'] ?? '');
      if ($filename === '') {
        continue;
      }
      // Normalize filenames that may have an auto-suffix before the ID token.
      $normalized = preg_replace('/_\d+(?=_DO_NOT_CHANGE_THIS_MEDIA_ID_\d+)/i', '', $filename);
      if (preg_match('/DO_NOT_CHANGE_THIS_MEDIA_ID_(\d+)/i', $normalized, $m)) {
        $mid = (int) $m[1];
        $mid_counts[$mid] = ($mid_counts[$mid] ?? 0) + 1;
      }
    }

    $duplicate_mids = array_keys(array_filter($mid_counts, static fn($c) => $c > 1));
    if (!empty($duplicate_mids)) {
      $form_state->setErrorByName('upload', $this->t('Duplicate uploads detected for Media ID(s): @ids. Please keep only one file per Media ID in this upload.', [
        '@ids' => implode(', ', $duplicate_mids),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $this->currentUser();
    $uploaded = $form_state->getValue('upload');

    $operations = [];
    foreach ($uploaded['uploaded_files'] as $item) {
      if (!empty($item['path']) && file_exists($item['path'])) {
        $operations[] = [
          [get_class($this), 'processFileBatch'],
          [$item, $user->id()],
        ];
      }
    }

    $batch = [
      'title' => $this->t('Processing uploaded files...'),
      'operations' => $operations,
      'finished' => [get_class($this), 'batchFinished'],
    ];
    batch_set($batch);
  }

  public static function processFileBatch($item, $uid, &$context) {
    $file = File::create([
      'uri' => $item['path'],
      'filename' => $item['filename'],
      'status' => 0,
    ]);
    $file->save();

    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $existing = $store->get('uploaded_files_' . $uid) ?? [];
    $existing[] = $file->id();
    $store->set('uploaded_files_' . $uid, $existing);
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      return new RedirectResponse(Url::fromRoute('mass_bulk_file_replace.confirm')->setAbsolute()->toString());
    }
    else {
      \Drupal::messenger()->addError('Something went wrong while processing the files.');
      return new RedirectResponse(Url::fromRoute('mass_bulk_file_replace.upload')->setAbsolute()->toString());
    }
  }

}
