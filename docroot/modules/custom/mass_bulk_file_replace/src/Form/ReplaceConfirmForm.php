<?php

namespace Drupal\mass_bulk_file_replace\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Tableselect;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Step 2: Confirm replacement mapping.
 */
class ReplaceConfirmForm extends FormBase {

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
    return 'mass_bulk_file_replace_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = $this->currentUser();
    $store = $this->tempStoreFactory->get('mass_bulk_file_replace');
    $fids = $store->get('uploaded_files_' . $user->id()) ?? [];

    $header = [
      'fid' => $this->t('New File ID'),
      'new' => $this->t('Uploaded File'),
      'old' => $this->t('Existing Media File'),
      'mid' => $this->t('Matched Media ID'),
      'media' => $this->t('Media Title'),
      'operations' => $this->t('Operations'),
    ];

    $options = [];
    $default_keys = [];
    $seen_media_ids = [];
    foreach ($fids as $fid) {
      $new_file = File::load($fid);
      if (!$new_file) {
        continue;
      }
      $filename = $new_file->getFilename();
      $normalized_filename = preg_replace('/_\d+(?=_DO_NOT_CHANGE_THIS_MEDIA_ID_\d+)/i', '', $filename);

      // Extract media ID from filename using pattern.
      if (preg_match('/DO_NOT_CHANGE_THIS_MEDIA_ID_(\d+)/i', $normalized_filename, $matches)) {
        $mid = (int) $matches[1];
        if (isset($seen_media_ids[$mid])) {
          continue;
        }
        $media = Media::load($mid);
        if ($media && $media->bundle() === 'document' && !$media->get('field_upload_file')->isEmpty()) {
          $old_file = $media->field_upload_file->entity;
          $result = [
            'fid' => $fid,
            'new' => $filename,
            'old' => $old_file ? $old_file->getFilename() : $this->t('Unknown'),
            'mid' => $mid,
            'media' => Link::fromTextAndUrl(
              $media->label(),
              Url::fromRoute('entity.media.canonical', ['media' => $media->id()])
            )->toString(),
            'operations' => [
              'data' => [
                '#type' => 'operations',
                '#links' => [
                  'view' => [
                    'title' => $this->t('View'),
                    'url' => Url::fromRoute('entity.media.canonical', ['media' => $media->id()]),
                  ],
                  'edit' => [
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('entity.media.edit_form', ['media' => $media->id()]),
                  ],
                ],
              ],
            ],
          ];
          $options[$fid] = $result;
          $default_keys[$fid] = $result;
          $seen_media_ids[$mid] = TRUE;
        }
      }
      else {
        $options[$fid] = [
          'fid' => $fid,
          'new' => $new_file->getFilename(),
          'old' => $this->t('No match'),
          'mid' => $this->t('N/A'),
          'media' => $this->t('No matching media'),
          'operations' => $this->t('N/A'),
          '#disabled' => TRUE,
        ];
      }
    }

    $form['replacements'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#default_value' => $default_keys,
      '#empty' => $this->t('No matching media entities found for the uploaded files.'),
      '#js_select' => TRUE,
      '#process' => [
        [Tableselect::class, 'processTableselect'],
        [static::class, 'processDisabledRows'],
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.tabledrag';

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Replace Approved Files'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = array_filter($form_state->getValue('replacements'));

    if (empty($values)) {
      $this->messenger()->addWarning($this->t('No files were selected for replacement.'));
      return;
    }

    $operations = [];
    foreach ($values as $fid => $selected) {
      $operations[] = [
        [static::class, 'processBatch'],
        [$fid, $this->currentUser()->getDisplayName()],
      ];
    }

    $batch = [
      'title' => $this->t('Replacing approved media files...'),
      'operations' => $operations,
      'finished' => [static::class, 'batchFinished'],
    ];

    batch_set($batch);
  }

  public static function processBatch($fid, $username, array &$context) {
    $file = File::load($fid);
    if (!$file) {
      \Drupal::logger('mass_bulk_file_replace')->error('File with fid @fid could not be loaded.', ['@fid' => $fid]);
      return;
    }

    $filename = $file->getFilename();
    $normalized_filename = preg_replace('/_\d+(?=_DO_NOT_CHANGE_THIS_MEDIA_ID_\d+)/i', '', $filename);
    if (preg_match('/DO_NOT_CHANGE_THIS_MEDIA_ID_(\d+)/i', $normalized_filename, $matches)) {
      $mid = (int) $matches[1];
      $media = Media::load($mid);
      if ($media && $media->bundle() === 'document') {
        $file->setPermanent();
        $file->save();

        $field_definition = $media->getFieldDefinition('field_upload_file');
        $settings = $field_definition->getSettings();
        $file_directory = $settings['file_directory'] ?? '';
        if (!empty($file_directory)) {
          $token_service = \Drupal::token();
          $data = ['media' => $media];
          $file_directory = $token_service->replace($file_directory, $data);
          $destination = 'public://' . $file_directory;
          /** @var \Drupal\Core\File\FileSystemInterface $file_system */
          $file_system = \Drupal::service('file_system');
          if (!$file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            \Drupal::logger('mass_bulk_file_replace')->error('Failed to prepare directory: @dest', ['@dest' => $destination]);
            return;
          }
          $moved_file = \Drupal::service('file.repository')->move($file, $destination);
          if ($moved_file) {
            $file = $moved_file;
          } else {
            \Drupal::logger('mass_bulk_file_replace')->error('Failed to move file to destination: @dest', ['@dest' => $destination]);
            return;
          }
        }

        $old_file = $media->get('field_upload_file')->entity;
        $uri = $old_file->getFileUri();

        // Ensure file exists locally via Stage File Proxy.
        if (!file_exists(\Drupal::service('file_system')->realpath($uri))) {
          $server = \Drupal::config('stage_file_proxy.settings')->get('origin');
          $origin_dir = trim(\Drupal::config('stage_file_proxy.settings')->get('origin_dir') ?? 'sites/default/files');
          $relative_path = str_replace('public://', '', $uri);
          $options = ['verify' => \Drupal::config('stage_file_proxy.settings')->get('verify')];

          \Drupal::service('stage_file_proxy.download_manager')->fetch($server, $origin_dir, $relative_path, $options);
        }

        $media->setNewRevision();
        $media->setRevisionLogMessage("File has been replaced using bulk replace tool by $username.");
        $media->set('field_upload_file', $file);

        $media->save();

        // Remove file from tempstore to avoid reprocessing.
        $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
        $key = 'uploaded_files_' . \Drupal::currentUser()->id();
        $fids = $store->get($key) ?? [];
        $fids = array_diff($fids, [$fid]);
        $store->set($key, $fids);

        // Clean up filename to remove DO_NOT_CHANGE_THIS_MEDIA_ID_ pattern.
        $cleaned_filename = preg_replace('/_\d+(?=_DO_NOT_CHANGE_THIS_MEDIA_ID_\d+)/i', '', $filename);
        if ($cleaned_filename && $cleaned_filename !== $filename) {
          $file->setFilename($cleaned_filename);
          $file->save();
        }
      }
      else {
        \Drupal::logger('mass_bulk_file_replace')->error('Media with ID @mid not found or not a document.', ['@mid' => $mid]);
      }
    }
    else {
      \Drupal::logger('mass_bulk_file_replace')->error('Filename "@filename" does not contain a valid media ID.', ['@filename' => $filename]);
    }
  }

  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addStatus(t('All approved files have been replaced.'));
    }
    else {
      \Drupal::messenger()->addError(t('Some replacements failed.'));
    }
    return new RedirectResponse(Url::fromRoute('<front>')->setAbsolute()->toString());
  }

  /**
   * Custom process to apply #disabled to individual tableselect rows.
   */
  public static function processDisabledRows(array $element): array {
    foreach (Element::children($element) as $key) {
      if (!empty($element['#options'][$key]['#disabled'])) {
        $element[$key]['#disabled'] = TRUE;
      }
    }
    return $element;
  }

}
