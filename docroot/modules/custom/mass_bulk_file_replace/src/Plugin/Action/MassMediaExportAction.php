<?php

namespace Drupal\mass_bulk_file_replace\Plugin\Action;

use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\media\Entity\Media;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;

/**
 * Entity action to download files from media entities.
 *
 * @Action(
 *   id = "mass_bulk_file_replace_export_action",
 *   label = @Translation("Download selected media as ZIP"),
 *   type = "media",
 *   confirm = TRUE,
 * )
 */
class MassMediaExportAction extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface, ContainerFactoryPluginInterface {

  protected DownloadManagerInterface $downloadManager;

  /**
   * The file_system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Failed files.
   *
   * @var array
   */
  protected $failedMediaTypes = [];

  /**
   * Constructs a NotFound object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system manager.
   * @param \Drupal\Core\File\FileUrlGenerator $fileUrlGenerator
   *   The file URL generator service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The tempstore factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FileSystemInterface $fileSystem,
    FileUrlGenerator $fileUrlGenerator,
    MessengerInterface $messenger,
    PrivateTempStoreFactory $tempStoreFactory,
    TimeInterface $time,
    LanguageManagerInterface $languageManager,
    EntityTypeManagerInterface $entityTypeManager,
    DownloadManagerInterface $downloadManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $fileSystem;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->messenger = $messenger;
    $this->tempStore = $tempStoreFactory->get('download_media_action');
    $this->time = $time;
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->downloadManager = $downloadManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('file_url_generator'),
      $container->get('messenger'),
      $container->get('tempstore.private'),
      $container->get('datetime.time'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('stage_file_proxy.download_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state): array {
    // Fetch available media types. Replace with your data source if needed.
    $media_types = $this->entityTypeManager
      ->getStorage('media_type')
      ->loadMultiple();

    $form['field_config'] = [
      '#type' => 'table',
      '#caption' => $this->t('Select the media field you want to include in the export. Enter its machine name below.'),
      '#header' => [
        $this->t('Media type'),
        $this->t('Field machine name'),
      ],
    ];

    // Iterate over media types to populate rows.
    foreach ($media_types as $media_type_id => $media_type) {
      $form['field_config'][$media_type_id]['media_type'] = [
        '#markup' => $media_type->label(),
      ];

      $form['field_config'][$media_type_id]['field_machine_name'] = [
        '#type' => 'textfield',
        '#default_value' => $values['field_config'][$media_type_id]['field_machine_name'] ?? '',
        '#size' => 100,
        // Add custom validation for machine name.
        '#element_validate' => [
          [get_class($this), 'validateFieldMachineName'],
        ],
        '#media_type_id' => $media_type_id,
      ];
    }

    return $form;
  }

  /**
   * Validate the field machine name.
   */
  public static function validateFieldMachineName(array &$element, FormStateInterface $form_state, array &$form) {
    $media_type_id = $element['#media_type_id'];
    $machine_name = $element['#value'];

    if (!empty($machine_name)) {
      // Load the media type and its fields.
      $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type_id);
      if ($media_type) {
        $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', $media_type_id);

        // Check if the machine name exists in the fields.
        if (!isset($field_definitions[$machine_name])) {
          $form_state->setError($element, t('The machine name "%name" does not exist for the media type %type.', [
            '%name' => $machine_name,
            '%type' => $media_type->label(),
          ]));
        }
      }
    }
  }

  /**
   * Helper to get the media field name based on media type.
   */
  protected function getMediaFieldName($media_type) {
    if (isset($this->configuration['field_config'][$media_type]['field_machine_name'])) {
      return $this->configuration['field_config'][$media_type]['field_machine_name'];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $batch_id = $this->context['sandbox']['current_batch'] ?? 1;
    $cid = $this->getCid($batch_id);

    // Collect media file paths in the current batch.
    $file_paths = [];
    $failed_file_paths = [];

    foreach ($entities as $entity) {
      if ($entity instanceof Media) {
        $media_file_path = $this->getMediaFilePath($entity);
        $media_real_file_path = $media_file_path['file_path'];
        $media_file_uri = $media_file_path['file_uri'];

        // Validate the paths.
        if ($media_real_file_path) {
          if ($this->validateMediaFilePath($media_file_uri)) {
            // Use the media ID as the key.
            $file_paths[$entity->id()] = $media_real_file_path;
          }
          else {
            $failed_file_paths[$entity->id()] = $media_real_file_path;
          }
        }
      }
    }

    // Save file paths to TempStore for this batch.
    $this->tempStore->set($cid, $file_paths);
    $this->tempStore->set($cid . '_failed', $failed_file_paths);

    $processed = $this->context['sandbox']['processed'] + count($this->view->result);

    if (!isset($this->context['sandbox']['total']) || $processed >= $this->context['sandbox']['total']) {
      $all_files = $this->getAllFilePaths();
      $all_failed_files = $this->getAllFailedFilePaths();

      $this->generateAndLinkZipFile($all_files, $all_failed_files);

      // Clear TempStore for the next action run.
      $this->clearTempStore();
    }
  }

  /**
   * Retrieve all file paths across batches from TempStore.
   */
  protected function getAllFilePaths() {
    $all_files = [];
    for ($i = 1; $i <= $this->context['sandbox']['current_batch']; $i++) {
      $cid = $this->getCid($i);
      $chunk = $this->tempStore->get($cid);
      if ($chunk) {
        $all_files += $chunk;
        $this->tempStore->delete($cid);
      }
    }
    return $all_files;
  }

  /**
   * Retrieve all file paths across batches from TempStore.
   */
  protected function getAllFailedFilePaths() {
    $all_files = [];
    for ($i = 1; $i <= $this->context['sandbox']['current_batch']; $i++) {
      $cid = $this->getCid($i);
      $chunk = $this->tempStore->get($cid . '_failed');

      if ($chunk) {
        $all_files += $chunk;
        // $all_files = array_merge($all_files, $chunk);
        $this->tempStore->delete($cid . '_failed');
      }
    }
    return $all_files;
  }

  /**
   * Summary of validateMediaFilePath.
   *
   * @param mixed $media_file_path
   *   The media file path.
   *
   * @return bool
   *   True if the media file path is valid, false otherwise.
   */
  protected function validateMediaFilePath($media_file_path) {
    return (file_exists($media_file_path) && is_readable($media_file_path));
  }

  /**
   * Helper to retrieve the file path for a media entity.
   */
  protected function getMediaFilePath(Media $media) {
    $target_media_field = $this->getMediaFieldName($media->bundle());

    if (!$target_media_field) {
      $this->failedMediaTypes[] = $media->bundle();
    }

    if ($target_media_field && $media->get($target_media_field)->entity) {
      $file = $media->get($target_media_field)->entity;
      $uri = $file->getFileUri();

      // Ensure file exists locally via Stage File Proxy.
      if (!file_exists($this->fileSystem->realpath($uri))) {
        $server = \Drupal::config('stage_file_proxy.settings')->get('origin');
        $origin_dir = trim(\Drupal::config('stage_file_proxy.settings')->get('origin_dir') ?? 'sites/default/files');
        $relative_path = str_replace('public://', '', $uri);
        $options = ['verify' => \Drupal::config('stage_file_proxy.settings')->get('verify')];

        $this->downloadManager->fetch($server, $origin_dir, $relative_path, $options);
      }

      return [
        'file_path' => $this->fileSystem->realpath($file->getFileUri()),
        'file_uri' => $file->getFileUri(),
      ];
    }
    return NULL;
  }

  /**
   * Generates a ZIP file and creates a download link.
   *
   * This method creates a ZIP file from the given list of file
   * paths and provides a download link for the generated ZIP.
   *  If any files fail to be added to the
   * ZIP, a warning message is displayed listing the failed files along with
   * clickable links to their corresponding media entity pages.
   *
   * @param array $file_paths
   *   An array of file paths to be included in the ZIP file.
   * @param array $failed_file_paths
   *   (optional) An associative array where the keys are media
   *   entity IDs and the values are the file paths that failed
   *   to be added to the ZIP.
   *
   * @return void
   *   The method does not return any value but displays
   *   success or warning messages using the messenger service.
   */
  protected function generateAndLinkZipFile(array $file_paths, array $failed_file_paths = []) {
    $directory = $this->fileSystem->realpath("private://");
    $zip_path = $directory . '/' . $this->getFilename();

    $zip = new \ZipArchive();
    if ($zip->open($zip_path, \ZipArchive::CREATE) !== TRUE) {
      $this->messenger->addError($this->t('Could not create the ZIP file.'));
      return;
    }

    foreach ($file_paths as $media_id => $file_path) {
      if (file_exists($file_path) && is_readable($file_path)) {
        $path_parts = pathinfo($file_path);
        $custom_filename = $path_parts['filename'] . '_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $media_id . '.' . $path_parts['extension'];
        $zip->addFile($file_path, $custom_filename);
      }
    }
    $zip->close();

    // Generate the download link for the ZIP file.
    $download_url = $this->fileUrlGenerator->generateAbsoluteString('private://' . basename($zip_path));
    $this->messenger->addStatus($this->t('Your download is ready: <a href=":url">Download ZIP</a>', [':url' => $download_url]));

    // Prepare and display the warning message for failed files.
    if (!empty($failed_file_paths)) {
      $failed_links = [];
      foreach ($failed_file_paths as $entity_id => $file_path) {
        $file_path_basename = basename($file_path);

        // Generate the media entity edit URL.
        $current_language = $this->languageManager->getCurrentLanguage()->getId();
        $media_entity_url = Url::fromRoute('entity.media.edit_form', ['media' => $entity_id])
          ->setOption('language', $this->languageManager->getLanguage($current_language));

        // Prepare the failed file message with a
        // clickable link for the media entity.
        $failed_links[] = $this->t('<a href=":url">media entity: @entity_id</a> file: @file', [
          ':url' => $media_entity_url->toString(),
          '@entity_id' => $entity_id,
          '@file' => $file_path_basename,
        ]);
      }

      // Display the warning message with
      // the failed links, each on a new line.
      $failed_links_output = implode('<br>', $failed_links);

      // Use Markup::create to render the message as raw HTML,
      // allowing links and line breaks.
      $this->messenger->addWarning($this->t('The following files could not be added to the ZIP: <br> @failed_links', [
        '@failed_links' => Markup::create($failed_links_output),
      ]));
    }
  }

  /**
   * Clear TempStore after the ZIP file is generated.
   */
  protected function clearTempStore() {
    for ($i = 1; $i <= $this->context['sandbox']['current_batch']; $i++) {
      $this->tempStore->delete($this->getCid($i));
    }
  }

  /**
   * Helper to get the CID for the current batch.
   */
  protected function getCid($batch_id) {
    if (!isset($this->context['sandbox']['cid_prefix'])) {
      $this->context['sandbox']['cid_prefix'] = $this->context['view_id'] . ':'
        . $this->context['display_id'] . ':' . $this->context['action_id'] . ':'
        . md5(serialize(array_keys($this->context['list']))) . ':';
    }
    return $this->context['sandbox']['cid_prefix'] . $batch_id;
  }

  /**
   * A method to get filename destination.
   *
   * @return string
   *   The output file name.
   */
  protected function getFilename() {
    $rand = substr(hash('ripemd160', uniqid()), 0, 8);
    return $this->context['view_id'] . '_' . date('Y_m_d_H_i', $this->time->getRequestTime()) . '-media-' . $rand . '.zip';
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->executeMultiple([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('view', $account, $return_as_object);
  }

}
