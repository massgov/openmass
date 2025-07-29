<?php

namespace Drupal\mass_bulk_file_replace\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 1: Upload form using DropzoneJS.
 */
class ReplaceUploadForm extends FormBase {

  /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory */
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
      '#title' => $this->t('Upload replacement files'),
      '#description' => $this->t('Upload one or more replacement files. Filenames must exactly match the originals.'),
      '#dropzone_description' => $this->t('Drag files here or click to upload.'),
      '#multiple' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf'],
      ],
      '#upload_location' => 'temporary://mass_bulk_file_replace',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to file mapping'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $this->currentUser();
    $store = $this->tempStoreFactory->get('mass_bulk_file_replace');
    $uploaded = $form_state->getValue('upload');

    $files = [];

    foreach ($uploaded['uploaded_files'] as $item) {
      if (!empty($item['path']) && file_exists($item['path'])) {
        // Create a File entity manually.
        $file = File::create([
          'uri' => $item['path'],
          'filename' => $item['filename'],
          'status' => 0, // Temporary
        ]);
        $file->save();

        $files[] = $file->id();
      }
    }

    // ✅ Check if any files were created.
    if (empty($files)) {
      $this->messenger()->addError($this->t('No valid files were uploaded. Please upload at least one valid file.'));
      return;
    }

    // Store file IDs in tempstore for step 2.
    $store->set('uploaded_files_' . $user->id(), $files);
    // Proceed to confirmation step.
    $form_state->setRedirect('mass_bulk_file_replace.confirm');
  }
}
