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
    $form['upload'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('Step 1: Upload replacement files'),
      '#description' => $this->t('Upload one or more replacement files. Each file will be matched to an existing media item by its filename, which should include the media ID.'),
      '#multiple' => TRUE,
      '#dropzone_description' => $this->t('Drag files here or click to upload.'),
      '#upload_validators' => [
    // 128MB size per file
        'file_validate_size' => [128 * 1024 * 1024],
      ],
      '#upload_location' => 'temporary://mass_bulk_file_replace',
      '#dropzonejs_settings' => [
      // One file per request
        'parallelUploads' => 1,
      // MB, client-side validation
        'maxFilesize' => 128,
      ],
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
