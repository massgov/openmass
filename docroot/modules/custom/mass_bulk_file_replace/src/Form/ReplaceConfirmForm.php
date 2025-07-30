<?php

namespace Drupal\mass_bulk_file_replace\Form;

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

      // Extract media ID from filename using pattern.
      if (preg_match('/DO_NOT_CHANGE_THIS_MEDIA_ID_(\d+)/i', $filename, $matches)) {
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
    foreach ($values as $fid => $selected) {
      /** @var \Drupal\file\Entity\File $new_file */
      $new_file = File::load($fid);
      if (!$new_file) {
        continue;
      }
      $filename = $new_file->getFilename();
      // Extract media ID from filename.
      if (preg_match('/DO_NOT_CHANGE_THIS_MEDIA_ID_(\d+)/i', $filename, $matches)) {
        $mid = (int) $matches[1];
        $media = Media::load($mid);
        if ($media && $media->bundle() === 'document') {
          $media->setNewRevision();
          $media->setRevisionLogMessage('File has been replaced using bulk replace tool by ' . $this->currentUser()->getDisplayName());
          $media->set('field_upload_file', $new_file);
          $media->save();

          $new_file->setPermanent();
          $new_file->save();
        }
      }
    }

    $this->messenger()->addStatus($this->t('Approved files have been replaced and saved as new revisions.'));
    $form_state->setRedirect('<front>');
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
