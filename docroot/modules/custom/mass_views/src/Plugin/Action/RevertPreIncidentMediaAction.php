<?php

namespace Drupal\mass_views\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mass_media\StageFileProxyHelper;
use Drupal\media\MediaInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reverts media to the revision before compromised edits in the view filters.
 */
#[Action(
  id: 'mass_views_revert_pre_incident_media',
  label: new TranslatableMarkup('Revert to pre-incident revision'),
  type: 'media',
)]
class RevertPreIncidentMediaAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use PreIncidentRevisionRollbackTrait;
  use StringTranslationTrait;

  /**
   * The media storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected EntityStorageInterface $mediaStorage;

  /**
   * Media IDs already reverted in this batch.
   *
   * @var array<int, bool>
   */
  protected array $processedMids = [];

  /**
   * Stage file proxy helper.
   *
   * @var \Drupal\mass_media\StageFileProxyHelper
   */
  protected StageFileProxyHelper $stageFileProxyHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaStorage = $container->get('entity_type.manager')->getStorage('media');
    $instance->stageFileProxyHelper = $container->get('mass_media.stage_file_proxy_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof MediaInterface) {
      return $this->t('Skipped: not a media item.');
    }

    $mid = (int) $entity->id();
    if (isset($this->processedMids[$mid])) {
      return $this->t('Skipped @label: already reverted in this batch.', ['@label' => $entity->label()]);
    }

    $target_revision = $this->getPreIncidentRevision($entity);
    if (!$target_revision) {
      return $this->t('Skipped @label: no earlier revision found before the incident window.', ['@label' => $entity->label()]);
    }

    if ($target_revision->isDefaultRevision()) {
      return $this->t('Skipped @label: already at the pre-incident revision.', ['@label' => $entity->label()]);
    }

    $reverted = $this->getMediaStorage()->createRevision($target_revision);
    $reverted->setRevisionLogMessage($this->buildRollbackRevisionLogMessage(
      (int) $target_revision->getRevisionId(),
      $target_revision->getRevisionLogMessage(),
    ));
    $reverted->setRevisionUserId(\Drupal::currentUser()->id());
    $reverted->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $reverted->setChangedTime(\Drupal::time()->getRequestTime());
    if (!$this->ensureMediaFilesExistLocally($reverted)) {
      return $this->t('Skipped @label: document file not found locally or on origin.', ['@label' => $entity->label()]);
    }
    $reverted->save();

    $this->processedMids[$mid] = TRUE;

    return $this->t('Reverted @label to pre-incident revision @vid.', [
      '@label' => $entity->label(),
      '@vid' => $target_revision->getRevisionId(),
    ]);
  }

  protected function getMediaStorage(): EntityStorageInterface {
    if (!isset($this->mediaStorage)) {
      $this->mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
    }
    return $this->mediaStorage;
  }

  /**
   * Finds the revision immediately before the earliest compromised revision.
   */
  protected function getPreIncidentRevision(MediaInterface $entity): ?MediaInterface {
    $previous_vid = $this->findPreviousRevisionId(
      (int) $entity->id(),
      (int) $entity->getRevisionId(),
      'media_field_revision',
      'media_revision',
      'mid',
      'revision_user',
    );

    return $previous_vid ? $this->getMediaStorage()->loadRevision($previous_vid) : NULL;
  }

  /**
   * Fetches missing document files from the Stage File Proxy origin.
   *
   * @return bool
   *   FALSE when a required upload file is missing locally and on the origin.
   */
  protected function ensureMediaFilesExistLocally(MediaInterface $media): bool {
    if ($media->bundle() !== 'document' || !$media->hasField('field_upload_file') || $media->get('field_upload_file')->isEmpty()) {
      return TRUE;
    }

    $file = $media->get('field_upload_file')->entity;
    if (!$file) {
      return TRUE;
    }

    $this->stageFileProxyHelper->ensureLocalFile($file);
    return $this->stageFileProxyHelper->fileExistsLocally($file->getFileUri());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof MediaInterface) {
      $account = $account ?: \Drupal::currentUser();
      $bundle = $object->bundle();
      $can_revert = $this->canRollbackCompromisedAccountRevisions($account)
        && ($account->hasPermission('administer media')
          || $account->hasPermission("revert any {$bundle} media revisions"));

      $access = $object->access('update', $account, TRUE);
      $access = $access->andIf($can_revert
        ? AccessResult::allowed()->cachePerPermissions()
        : AccessResult::forbidden()->cachePerPermissions());

      return $return_as_object ? $access : $access->isAllowed();
    }
    return TRUE;
  }

}
