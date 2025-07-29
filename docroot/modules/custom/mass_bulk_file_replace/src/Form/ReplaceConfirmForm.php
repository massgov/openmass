<?php

namespace Drupal\mass_bulk_file_replace\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 2: Confirm replacement mapping.
 */
class ReplaceConfirmForm extends FormBase {

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
      'old' => $this->t('Existing Media File'),
      'new' => $this->t('Uploaded File'),
      'media' => $this->t('Media Title'),
    ];

    $options = [];
    foreach ($fids as $fid) {
      $new_file = File::load($fid);
      if (!$new_file) {
        continue;
      }
      $filename = $new_file->getFilename();

      $media_storage = \Drupal::entityTypeManager()->getStorage('media');
      $query = $media_storage->getQuery()->accessCheck('FALSE')
        ->condition('bundle', 'document')
        ->condition('field_upload_file.entity.filename', '%' . $filename . '%', 'LIKE');
      $ids = $query->execute();

      if (!empty($ids)) {
        $mid = reset($ids);
        $media = Media::load($mid);
        $old_file = $media->field_upload_file->entity;

        $options[$fid] = [
          'old' => $old_file ? $old_file->getFilename() : $this->t('Unknown'),
          'new' => $filename,
          'media' => Link::fromTextAndUrl(
            $media->label(),
            Url::fromRoute('entity.media.canonical', ['media' => $media->id()])
          )->toString(),
        ];
      }
    }

    $form['replacements'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('No matching media entities found for the uploaded files.'),
    ];

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
    foreach ($values as $fid => $selected) {
      /** @var \Drupal\file\Entity\File $new_file */
      $new_file = File::load($fid);
      if (!$new_file) {
        continue;
      }
      $filename = $new_file->getFilename();
      $query = \Drupal::entityTypeManager()->getStorage('media')->getQuery()->accessCheck('FALSE')
        ->condition('bundle', 'document')
        ->condition('field_upload_file.entity.filename', '%' . $filename . '%', 'LIKE');
      $ids = $query->execute();

      if (!empty($ids)) {
        $mid = reset($ids);
        $media = Media::load($mid);
        $media->setNewRevision();
        $media->setRevisionLogMessage('File has been replaced using bulk replace tool by ' . $this->currentUser()->getDisplayName());
        $media->set('field_upload_file', $new_file);
        $media->save();

        $new_file->setPermanent();
        $new_file->save();
      }
    }

    $this->messenger()->addStatus($this->t('Approved files have been replaced and saved as new revisions.'));
    $form_state->setRedirect('<front>');
  }
}
