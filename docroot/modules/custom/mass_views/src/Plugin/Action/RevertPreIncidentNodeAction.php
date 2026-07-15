<?php

namespace Drupal\mass_views\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reverts nodes to the revision before compromised edits in the view filters.
 */
#[Action(
  id: 'mass_views_revert_pre_incident_node',
  label: new TranslatableMarkup('Revert to pre-incident revision'),
  type: 'node',
)]
class RevertPreIncidentNodeAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use PreIncidentRevisionRollbackTrait;
  use StringTranslationTrait;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Node IDs already reverted in this batch.
   *
   * @var array<int, bool>
   */
  protected array $processedNids = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->nodeStorage = $container->get('entity_type.manager')->getStorage('node');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof NodeInterface) {
      return $this->t('Skipped: not a node.');
    }

    $nid = (int) $entity->id();
    if (isset($this->processedNids[$nid])) {
      return $this->t('Skipped @title: already reverted in this batch.', ['@title' => $entity->label()]);
    }

    $target_revision = $this->getPreIncidentRevision($entity);
    if (!$target_revision) {
      return $this->t('Skipped @title: no earlier revision found before the incident window.', ['@title' => $entity->label()]);
    }

    if ($target_revision->isDefaultRevision()) {
      return $this->t('Skipped @title: already at the pre-incident revision.', ['@title' => $entity->label()]);
    }

    $reverted = $this->getNodeStorage()->createRevision($target_revision);
    $reverted->setRevisionLogMessage($this->buildRollbackRevisionLogMessage(
      (int) $target_revision->getRevisionId(),
      $target_revision->getRevisionLogMessage(),
    ));
    $reverted->setRevisionUserId(\Drupal::currentUser()->id());
    $reverted->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $reverted->setChangedTime(\Drupal::time()->getRequestTime());
    $reverted->save();

    $this->processedNids[$nid] = TRUE;

    return $this->t('Reverted @title to pre-incident revision @vid.', [
      '@title' => $entity->label(),
      '@vid' => $target_revision->getRevisionId(),
    ]);
  }

  protected function getNodeStorage(): EntityStorageInterface {
    if (!isset($this->nodeStorage)) {
      $this->nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    }
    return $this->nodeStorage;
  }

  /**
   * Finds the revision immediately before the earliest compromised revision.
   */
  protected function getPreIncidentRevision(NodeInterface $entity): ?NodeInterface {
    $previous_vid = $this->findPreviousRevisionId(
      (int) $entity->id(),
      (int) $entity->getRevisionId(),
      'node_field_revision',
      'node_revision',
      'nid',
      'revision_uid',
    );

    return $previous_vid ? $this->getNodeStorage()->loadRevision($previous_vid) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof NodeInterface) {
      $account = $account ?: \Drupal::currentUser();
      $bundle = $object->bundle();
      $can_revert = $this->canRollbackCompromisedAccountRevisions($account)
        && ($account->hasPermission('administer nodes')
          || $account->hasPermission('revert all revisions')
          || $account->hasPermission("revert {$bundle} revisions"));

      // Core denies the "revert revision" operation on the default revision,
      // but this action rolls the live revision back via createRevision().
      $access = $object->access('update', $account, TRUE);
      $access = $access->andIf($can_revert
        ? AccessResult::allowed()->cachePerPermissions()
        : AccessResult::forbidden()->cachePerPermissions());

      return $return_as_object ? $access : $access->isAllowed();
    }
    return TRUE;
  }

}
