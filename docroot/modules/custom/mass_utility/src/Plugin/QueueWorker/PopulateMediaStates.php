<?php

namespace Drupal\mass_utility\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued media items to set their content moderation state.
 *
 * @QueueWorker(
 *   id = "mass_utility_populate_media_states",
 *   title = @Translation("Populate the media state for given document media."),
 *   cron = {"time" = 180}
 * )
 */
class PopulateMediaStates extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $cm_state_storage = $this->entityTypeManager->getStorage('content_moderation_state');
    $default_state_values = [
      'workflow' => 'media_states',
      'content_entity_type_id' => 'media',
    ];
    // Creates a new moderation state for the given media entity.
    foreach ($data as $media) {
      $cm_state_values = array_merge($default_state_values, $media);
      $cm_state_values['moderation_state'] = !empty($cm_state_values['moderation_state']) ? 'published' : 'unpublished';
      try {
        $moderation_state = $cm_state_storage->create($cm_state_values);
        $moderation_state->updateOrCreateFromEntity($moderation_state);
      }
      catch (\Exception $e) {
        // Do nothing when there's an error. This will usually only happen when
        // there is already a content moderation state for a given revision.
        // This is unlikely but could occur if the moderation state for a
        // revision was created while processing a queued data set which later
        // errored out for some reason.
      }
    }
  }

}
