<?php

namespace Drupal\mass_moderation_migration;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\workbench_moderation\ModerationStateInterface;
use Drupal\workbench_moderation\ModerationStateTransitionInterface;

/**
 * Class WorkflowCollector.
 */
class WorkflowCollector {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation state entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $stateStorage;

  /**
   * The moderation state transition entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $transitionStorage;

  /**
   * WorkflowCollector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   (optional) The string translation service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $translation = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stateStorage = $entity_type_manager->getStorage('moderation_state');
    $this->transitionStorage = $entity_type_manager->getStorage('moderation_state_transition');

    if ($translation) {
      $this->setStringTranslation($translation);
    }
  }

  /**
   * Returns all unique content type workflows.
   *
   * @return array
   *   An array of arrays, each of which is a set of values representing a
   *   workflow config entity.
   */
  public function getWorkflows() {
    $workflows = [];

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle */
    foreach ($this->enabled() as $id => $bundle) {
      $states = $bundle->getThirdPartySetting('workbench_moderation', 'allowed_moderation_states', []);
      sort($states);
      $hash = sha1(implode('', $states));

      if (empty($workflows[$hash])) {
        $workflows[$hash] = [
          'id' => substr($hash, 0, 8),
          'type' => 'content_moderation',
          'type_settings' => [
            'states' => $this->mapStates($states),
            'transitions' => $this->mapTransitions($states),
            'entity_types' => [],
          ],
        ];
      }

      $bundle_of = $bundle->getEntityType()->getBundleOf();
      $workflows[$hash]['type_settings']['entity_types'][$bundle_of][] = $id;
    }
    $i = 0;
    foreach ($workflows as &$workflow) {
      $workflow['label'] = $this->t('Workflow @number', [
        '@number' => ++$i,
      ]);
    }
    return $workflows;
  }

  /**
   * Generates Content Moderation-compatible moderation state definitions.
   *
   * @param string[] $states
   *   The moderation state entity IDs.
   *
   * @return array
   *   The Content Moderation-compatible moderation state definitions.
   */
  protected function mapStates(array $states) {
    $weight = 1;

    $map = function (ModerationStateInterface $state) use (&$weight) {
      return [
        'label' => $state->label(),
        'published' => $state->isPublishedState(),
        'default_revision' => $state->isDefaultRevisionState(),
        'weight' => $weight++,
      ];
    };
    return array_map($map, $this->stateStorage->loadMultiple($states));
  }

  /**
   * Generates Content Moderation-compatible state transition definitions.
   *
   * @param string[] $states
   *   The moderation state entity IDs for which transition definitions should
   *   be generated.
   *
   * @return array
   *   The Content Moderation-compatible state transition definitions.
   */
  protected function mapTransitions(array $states) {
    $excluded_states = array_diff(
      $this->stateStorage->getQuery()->execute(),
      $states
    );

    $transitions = $this->transitionStorage->getQuery()
      ->condition('stateFrom', $excluded_states, 'NOT IN')
      ->condition('stateTo', $excluded_states, 'NOT IN')
      ->execute();

    $weight = 1;

    $map = function (ModerationStateTransitionInterface $transition) use (&$weight) {
      return [
        'label' => $transition->label(),
        'from' => (array) $transition->getFromState(),
        'to' => $transition->getToState(),
        'weight' => $weight++,
      ];
    };
    return array_map($map, $this->transitionStorage->loadMultiple($transitions));
  }

  /**
   * Placeholder.
   */
  protected function enabled() {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    foreach ($this->supported() as $id => $entity) {
      if ($entity->getThirdPartySetting('workbench_moderation', 'enabled', FALSE)) {
        yield $id => $entity;
      }
    }
  }

  /**
   * Placeholder.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function supported() {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      if ($entity_type->getBundleOf()) {
        $storage = $this->entityTypeManager->getStorage($id);

        foreach ($storage->getQuery()->execute() as $entity_id) {
          /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
          $entity = $storage->load($entity_id);

          if (in_array('workbench_moderation', $entity->getThirdPartyProviders(), TRUE)) {
            yield $entity_id => $entity;
          }
        }
      }
    }
  }

}
