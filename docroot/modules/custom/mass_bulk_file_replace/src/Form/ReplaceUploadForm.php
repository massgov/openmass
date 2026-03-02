<?php

namespace Drupal\mass_bulk_file_replace\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\mass_bulk_file_replace\FilenameMediaMatchTrait;
use Drupal\media\Entity\Media;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Step 1: Upload form using DropzoneJS.
 */
class ReplaceUploadForm extends FormBase {
  use FilenameMediaMatchTrait;

  private const MAX_UPLOADS = 100;

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
      '#title' => $this->t('Upload replacement files'),
      '#description' => $this->t('<p>Upload one or more replacement files. Each file will be matched to an existing media item by its filename, which must include the media ID. You can upload up to @count files per batch.</p><p><strong>Instructions:</strong> Make sure each uploaded file includes the text "DO_NOT_CHANGE_THIS_MEDIA_ID_{ID}" in its filename (e.g., <em>something_DO_NOT_CHANGE_THIS_MEDIA_ID_123.pdf</em>). This will be used to identify the correct media entity to replace.</p><p><strong>Note:</strong> If all filenames match their corresponding media items, the replacements will be completed automatically after you click <strong>Start replacement</strong>.
If any filenames do not match, you will be redirected to a review screen to confirm those files manually.</p>', ['@count' => self::MAX_UPLOADS]),
      '#description_display' => 'before',
      '#multiple' => TRUE,
      '#max_files' => self::MAX_UPLOADS,
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
        'maxFilesize' => 128,
        'acceptedFiles' => $accepted_files,
      ],
      '#extensions' => $exts_space,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start replacement'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'mass_bulk_file_replace/dropzone_limit';
    $form['#attached']['drupalSettings']['massBulkFileReplace']['maxUploads'] = self::MAX_UPLOADS;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $uploaded = $form_state->getValue('upload');
    $files = $uploaded['uploaded_files'] ?? [];

    // Enforce maximum files per batch.
    $count = is_array($files) ? count($files) : 0;
    if ($count > self::MAX_UPLOADS) {
      $form_state->setErrorByName('upload', $this->t('You can upload up to @max files at a time. You selected @count.', [
        '@max' => self::MAX_UPLOADS,
        '@count' => $count,
      ]));
      return;
    }

    if (empty($files) || !is_array($files)) {
      return;
    }

    $mid_counts = [];
    foreach ($files as $item) {
      $filename = (string) ($item['filename'] ?? '');
      if ($filename === '') {
        continue;
      }
      $mid = static::extractMediaId($filename);
      if ($mid) {
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
    $items = $uploaded['uploaded_files'] ?? [];

    if (empty($items) || !is_array($items)) {
      $this->messenger()->addWarning($this->t('No files were uploaded.'));
      return;
    }

    // Clear any previous mismatch list for this user.
    $store = $this->tempStoreFactory->get('mass_bulk_file_replace');
    $store->delete('mismatch_files_' . $user->id());

    $operations = [];
    foreach ($items as $item) {
      if (!empty($item['path']) && file_exists($item['path'])) {
        $operations[] = [
          [get_class($this), 'processFileBatch'],
          [$item, $user->id(), $user->getDisplayName()],
        ];
      }
    }

    if (empty($operations)) {
      $this->messenger()->addWarning($this->t('No valid files were found to process.'));
      return;
    }

    $batch = [
      'title' => $this->t('Processing uploaded files...'),
      'operations' => $operations,
      'finished' => [get_class($this), 'batchFinished'],
    ];
    batch_set($batch);
  }

  public static function processFileBatch($item, $uid, $username, &$context) {
    // Basic sanity check on input.
    $filename = isset($item['filename']) ? (string) $item['filename'] : '';
    $path = isset($item['path']) ? (string) $item['path'] : '';

    if ($filename === '' || $path === '' || !file_exists($path)) {
      return;
    }

    $mid = static::extractMediaId($filename);

    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $mismatch_key = 'mismatch_files_' . $uid;
    $mismatch_fids = $store->get($mismatch_key) ?? [];

    $is_match = FALSE;
    $media = NULL;
    $old_file = NULL;

    if ($mid) {
      $media = Media::load($mid);
      if ($media && $media->bundle() === 'document' && !$media->get('field_upload_file')->isEmpty()) {
        $old_file = $media->get('field_upload_file')->entity;
        if ($old_file) {
          $existing_filename = $old_file->getFilename();

          if (static::isSafeFilenameMatch($filename, $existing_filename)) {
            $is_match = TRUE;
          }
        }
      }
    }

    if ($is_match && $media && $old_file) {
      // SAFE MATCH CASE:
      // Reuse the existing file entity attached to the media and overwrite
      // its underlying file with the uploaded file contents.

      $clean_name = static::getDisplayFilename($filename);
      if ($clean_name === '') {
        $clean_name = $filename;
      }

      // Resolve the destination directory using the media's field settings.
      $field_definition = $media->getFieldDefinition('field_upload_file');
      $settings = $field_definition ? $field_definition->getSettings() : [];
      $file_directory = $settings['file_directory'] ?? '';

      if (!empty($file_directory)) {
        $token_service = \Drupal::token();
        $data = ['media' => $media];
        $file_directory = $token_service->replace($file_directory, $data);
        $destination = 'public://' . $file_directory;
      }
      else {
        $destination = 'public://';
      }

      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      if (!$file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        \Drupal::logger('mass_bulk_file_replace')->error('Failed to prepare directory: @dest', ['@dest' => $destination]);
        return;
      }

      $destination_uri = rtrim($destination, '/') . '/' . $clean_name;

      // Copy uploaded file into final destination, overwriting any existing file.
      $copied = $file_system->copy($path, $destination_uri);
      if (!$copied) {
        \Drupal::logger('mass_bulk_file_replace')->error('Failed to copy uploaded file "@file" to "@dest".', ['@file' => $path, '@dest' => $destination_uri]);
        return;
      }

      // Update the existing file entity to point at the new URI and filename.
      $old_file->setFileUri($destination_uri);
      $old_file->setFilename($clean_name);
      $old_file->setPermanent();
      $old_file->save();

      $now = \Drupal::time()->getRequestTime();
      // Create a new media revision with a log message.
      $media->setNewRevision();
      $media->setRevisionUserId($uid);
      $media->setRevisionCreationTime($now);
      $media->setChangedTime($now);
      $media->setRevisionLogMessage("File has been replaced using bulk replace tool by $username.");
      $media->set('field_upload_file', $old_file);
      $media->save();

      return;
    }

    // MISMATCH CASE:
    // Anything that reaches here is treated as a mismatch needing manual
    // verification on the confirmation form. We now create a temporary file
    // entity and store only its fid for the confirm step to use.
    $file = File::create([
      'uri' => $path,
      'filename' => $filename,
      'status' => 0,
    ]);
    $file->save();
    $fid = $file->id();

    $mismatch_fids[] = $fid;
    $store->set($mismatch_key, $mismatch_fids);
  }

  public static function batchFinished($success, $results, $operations) {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');

    if (!$success) {
      \Drupal::messenger()->addError(t('Something went wrong while processing the files.'));
      return new RedirectResponse(Url::fromRoute('mass_bulk_file_replace.upload')->setAbsolute()->toString());
    }

    $mismatch_key = 'mismatch_files_' . $uid;
    $mismatch_fids = $store->get($mismatch_key) ?? [];

    if (!empty($mismatch_fids)) {
      \Drupal::messenger()->addStatus(t('Some uploaded files need manual verification before replacement.'));
      return new RedirectResponse(Url::fromRoute('mass_bulk_file_replace.mismatch')->setAbsolute()->toString());
    }

    \Drupal::messenger()->addStatus(t('All uploaded files were successfully replaced.'));
    return new RedirectResponse(Url::fromRoute('mass_bulk_file_replace.upload')->setAbsolute()->toString());
  }

}
