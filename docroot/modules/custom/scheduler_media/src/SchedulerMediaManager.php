<?php

namespace Drupal\scheduler_media;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\scheduler\SchedulerEvents;
use Drupal\media\Entity\MediaType;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\media\Entity\Media;
use Drupal\scheduler\Exception\SchedulerMissingDateException;
use Drupal\scheduler\Exception\SchedulerNodeTypeNotEnabledException;
use Psr\Log\LoggerInterface;

/**
 * Defines a scheduler manager.
 */
class SchedulerMediaManager {

  /**
   * Date formatter service object.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Scheduler Logger service object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Entity Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity Type Manager object from the container.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a SchedulerManager object.
   */
  public function __construct(DateFormatter $dateFormatter, LoggerInterface $logger, ModuleHandler $moduleHandler, EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager, $configFactory, ModerationInformationInterface $moderationInformation = NULL) {
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->moduleHandler = $moduleHandler;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->moderationInfo = $moderationInformation;
    $this->schedulerModerationEnabled = $this->moduleHandler->moduleExists('scheduler_content_moderation_integration');
  }

  /**
   * Retrieve a thridPartySetting from the MediaType entity.
   *
   * @param \Drupal\media\Entity\MediaType $media
   *   The MediaType entity.
   * @param string $key
   *   A setting to search for on the MediaType entity.
   */
  public function isDefaultSetting(MediaType $media, string $key) {
    return $media->getThirdPartySetting('scheduler_media', $key, $this->setting('default_' . $key));
  }

  /**
   * Publish scheduled nodes.
   *
   * @return bool
   *   TRUE if any node has been published, FALSE otherwise.
   *
   * @throws \Drupal\scheduler\Exception\SchedulerMissingDateException
   * @throws \Drupal\scheduler\Exception\SchedulerNodeTypeNotEnabledException
   */
  public function publish() {
    // @todo \Drupal calls should be avoided in classes.
    // Replace \Drupal::service with dependency injection?
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = \Drupal::service('event_dispatcher');

    $result = FALSE;
    $action = 'publish';

    // Select all nodes of the types that are enabled for scheduled publishing
    // and wherpublish_on is less than or equal to the current time.
    $mids = [];
    $scheduler_enabled_types = array_keys(_scheduler_media_get_scheduler_media_enabled_media_types($action));
    if (!empty($scheduler_enabled_types)) {
      // @todo \Drupal calls should be avoided in classes.
      // Replace \Drupal::entityQuery with dependency injection?
      $query = \Drupal::entityQuery('media')
        ->exists('publish_on')
        ->condition('publish_on', \Drupal::time()->getRequestTime(), '<=')
        ->condition('bundle', $scheduler_enabled_types, 'IN')
        ->latestRevision()
        ->sort('publish_on')
        ->sort('mid');
      // Disable access checks for this query.
      // @see https://www.drupal.org/node/2700209
      $query->accessCheck(FALSE);
      $mids = $query->execute();
    }

    // Allow other modules to add to the list of nodes to be published.
    $mids = array_unique(array_merge($mids, $this->midList($action)));

    // Allow other modules to alter the list of nodes to be published.
    $this->moduleHandler->alter('scheduler_mid_list', $mids, $action);

    // In 8.x the entity translations are all associated with one node id
    // unlike 7.x where each translation was a separate node. This means that
    // the list of node ids returned above may have some translations that need
    // processing now and others that do not.
    $media_entities = Media::loadMultiple($mids);
    // @todo Media::loadMultiple calls should be avoided in classes.
    // Replace with dependency injection?
    foreach ($media_entities as $media_multilingual) {
      $mm_bundle = \Drupal::entityTypeManager()->getStorage('media_type')
        ->load($media_multilingual->bundle());

      // The API calls could return nodes of types which are not enabled for
      // scheduled publishing, so do not process these. This check can be done
      // once, here, as the setting will be the same for all translations.
      if (!$this->isDefaultSetting($mm_bundle, 'publish_enable')) {
        throw new SchedulerNodeTypeNotEnabledException(sprintf("Media %d '%s' will not be published because node type '%s' is not enabled for scheduled publishing", $media_multilingual->id(), $media_multilingual->getTitle(), node_get_type_label($media_multilingual)));
      }

      $languages = $media_multilingual->getTranslationLanguages();
      foreach ($languages as $language) {
        // The object returned by getTranslation() behaves the same as a $media.
        $media = $media_multilingual->getTranslation($language->getId());
        $media_bundle = \Drupal::entityTypeManager()->getStorage('media_type')
          ->load($media->bundle());

        // If the current translation does not have a publish on value, or it is
        // later than the date we are processing then move on to the next.
        $publish_on = $media->publish_on->value;
        if (empty($publish_on) || $publish_on > \Drupal::time()->getRequestTime()) {
          continue;
        }

        // Check that other modules allow the action on this node.
        if (!$this->isAllowed($media, $action)) {
          continue;
        }

        // $media->set('changed', $publish_on) will fail badly if an API call has
        // removed the date. Trap this as an exception here and give a
        // meaningful message.
        // @todo This will now never be thrown due to the emptpublish_on)
        // check above to cater for translations. Remove this exception?
        if (empty($media->publish_on->value)) {
          $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $media->getType());
          $field = (string) $field_definitions['publish_on']->getLabel();
          throw new SchedulerMissingDateException(sprintf("Node %d '%s' will not be published because field '%s' has no value", $media->id(), $media->getTitle(), $field));
        }

        // Trigger the PRE_PUBLISH event so that modules can react before the
        // node is published.
        $event = new SchedulerMediaEvent($media);
        $dispatcher->dispatch(SchedulerEvents::PRE_PUBLISH, $event);
        $media = $event->getEntity();

        // Update timestamps.
        $media->set('changed', $publish_on);
        $old_creation_date = $media->getCreatedTime();
        if ($this->isDefaultSetting($media_bundle, 'publish_touch')) {
          $media->setCreatedTime($publish_on);
        }

        if ($this->isDefaultSetting($media_bundle, 'publish_revision')) {
          $media->setNewRevision();
          // Use a core date format to guarantee a time is included.
          // @todo 't' calls should be avoided in classes.
          // Replace with dependency injection?
          $media->revision_log = t('Node published by Scheduler on @now. Previous creation date was @date.', [
            '@now' => $this->dateFormatter->format(\Drupal::time()->getRequestTime(), 'short'),
            '@date' => $this->dateFormatter->format($old_creation_date, 'short'),
          ]);
        }
        // Unsepublish_on so the node will not get rescheduled by subsequent
        // calls to $media->save().
        $media->publish_on->value = NULL;

        // Log the fact that a scheduled publication is about to take place.
        // Please confirm that `$media` is an instance of `\Drupal\Core\Entity\EntityInterface`. Only the method name and not the class name was checked for this replacement, so this may be a false positive.
        $view_link = $media->toLink(t('View node'))->toString();
        $mediatype_url = Url::fromRoute('entity.media_type.edit_form', ['media_type' => $media->bundle()]);
        // @todo \Drupal calls should be avoided in classes.
        // Replace \Drupal::l with dependency injection?
        $mediatype_link = Link::fromTextAndUrl($media->bundle() . ' ' . t('settings'), $mediatype_url)->toString();
        $logger_variables = [
          '@type' => $media->bundle(),
          '%title' => $media->label(),
          'link' => $mediatype_link . ' ' . $view_link,
        ];
        $this->logger->notice('@type: scheduled publishing of %title.', $logger_variables);

        // If scheduler_content_moderation_integration is enabled, set to
        // published state.
        if ($this->schedulerModerationEnabled && $this->moderationInfo->isModeratedEntity($media)) {
          $state = $media->publish_state->value;
          $media->publish_state->value = NULL;

          /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
          $type_plugin = $this->moderationInfo->getWorkflowForEntity($media)->getTypePlugin();
          try {
            // If transition is not valid, throw exception.
            $type_plugin->getTransitionFromStateToState($media->moderation_state->value, $state);
            $media->set('moderation_state', $state);
          }
          catch (\InvalidArgumentException $exception) {
            $media->save();
            continue;
          }
        }
        else {
          // Use the actions system to publish the node.
          $this->entityTypeManager->getStorage('action')->load('media_publish_action')->getPlugin()->execute($media);
        }

        // Invoke the event to tell Rules that Scheduler has published the node.
        if ($this->moduleHandler->moduleExists('scheduler_rules_integration')) {
          _scheduler_rules_integration_dispatch_cron_event($media, 'publish');
        }

        // Trigger the PUBLISH event so that modules can react after the node is
        // published.
        $event = new SchedulerMediaEvent($media);
        $dispatcher->dispatch(SchedulerEvents::PUBLISH, $event);
        $event->getEntity()->save();

        $result = TRUE;
      }
    }

    return $result;
  }

  /**
   * Unpublish scheduled nodes.
   *
   * @return bool
   *   TRUE if any node has been unpublished, FALSE otherwise.
   *
   * @throws \Drupal\scheduler\Exception\SchedulerMissingDateException
   * @throws \Drupal\scheduler\Exception\SchedulerNodeTypeNotEnabledException
   */
  public function unpublish() {
    // @todo \Drupal calls should be avoided in classes.
    // Replace \Drupal::service with dependency injection?
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = \Drupal::service('event_dispatcher');

    $result = FALSE;
    $action = 'unpublish';

    // Select all nodes of the types that are enabled for scheduled unpublishing
    // and where unpublish_on is less than or equal to the current time.
    $mids = [];
    $scheduler_enabled_types = array_keys(_scheduler_media_get_scheduler_media_enabled_media_types($action));
    if (!empty($scheduler_enabled_types)) {
      // @todo \Drupal calls should be avoided in classes.
      // Replace \Drupal::entityQuery with dependency injection?
      $query = \Drupal::entityQuery('media')
        ->exists('unpublish_on')
        ->condition('unpublish_on', \Drupal::time()->getRequestTime(), '<=')
        ->condition('bundle', $scheduler_enabled_types, 'IN')
        ->latestRevision()
        ->sort('unpublish_on')
        ->sort('mid');
      // Disable access checks for this query.
      // @see https://www.drupal.org/node/2700209
      $query->accessCheck(FALSE);
      $mids = $query->execute();
    }

    // Allow other modules to add to the list of nodes to be unpublished.
    $mids = array_unique(array_merge($mids, $this->midList($action)));

    // Allow other modules to alter the list of nodes to be unpublished.
    $this->moduleHandler->alter('scheduler_mid_list', $mids, $action);

    // @todo Media::loadMultiple calls should be avoided in classes.
    // Replace with dependency injection?
    $media_entities = Media::loadMultiple($mids);
    foreach ($media_entities as $media_multilingual) {
      $mm_bundle = \Drupal::entityTypeManager()->getStorage('media_type')
        ->load($media_multilingual->bundle());
      // The API calls could return nodes of types which are not enabled for
      // scheduled unpublishing. Do not process these.
      if (!$this->isDefaultSetting($mm_bundle, 'unpublish_enable')) {
        throw new SchedulerNodeTypeNotEnabledException(sprintf("Media %d '%s' will not be unpublished because node type '%s' is not enabled for scheduled unpublishing", $media_multilingual->id(), $media_multilingual->getTitle(), node_get_type_label($media_multilingual)));
      }

      $languages = $media_multilingual->getTranslationLanguages();
      foreach ($languages as $language) {
        // The object returned by getTranslation() behaves the same as a $media.
        $media = $media_multilingual->getTranslation($language->getId());
        $media_bundle = \Drupal::entityTypeManager()->getStorage('media_type')
          ->load($media->bundle());

        // If the current translation does not have an unpublish on value, or it
        // is later than the date we are processing then move on to the next.
        $unpublish_on = $media->unpublish_on->value;
        if (empty($unpublish_on) || $unpublish_on > \Drupal::time()->getRequestTime()) {
          continue;
        }

        // Do not process the node if it still has publish_on time which is in
        // the past, as this implies that scheduled publishing has been blocked
        // by one of the hook functions we provide, and is still being blocked
        // now that the unpublishing time has been reached.
        $publish_on = $media->publish_on->value;
        if (!empty($publish_on) && $publish_on <= \Drupal::time()->getRequestTime()) {
          continue;
        }

        // Check that other modules allow the action on this node.
        if (!$this->isAllowed($media, $action)) {
          continue;
        }

        // $media->set('changed', $unpublish_on) will fail badly if an API call
        // has removed the date. Trap this as an exception here and give a
        // meaningful message.
        // @todo This will now never be thrown due to the empty(unpublish_on)
        // check above to cater for translations. Remove this exception?
        if (empty($unpublish_on)) {
          $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $media->getType());
          $field = (string) $field_definitions['unpublish_on']->getLabel();
          throw new SchedulerMissingDateException(sprintf("Media %d '%s' will not be unpublished because field '%s' has no value", $media->id(), $media->getTitle(), $field));
        }

        // Trigger the PRE_UNPUBLISH event so that modules can react before the
        // node is unpublished.
        $event = new SchedulerMediaEvent($media);
        $dispatcher->dispatch(SchedulerEvents::PRE_UNPUBLISH, $event);
        $media = $event->getEntity();

        // Update timestamps.
        $old_change_date = $media->getChangedTime();
        $media->set('changed', $unpublish_on);

        $create_unpublishing_revision = $this->isDefaultSetting($media_bundle, 'unpublish_revision');
        if ($create_unpublishing_revision) {
          $media->setNewRevision();
          // Use a core date format to guarantee a time is included.
          // @todo 't' calls should be avoided in classes.
          // Replace with dependency injection?
          $media->revision_log = t('Media unpublished by Scheduler on @now. Previous change date was @date.', [
            '@now' => $this->dateFormatter->format(\Drupal::time()->getRequestTime(), 'short'),
            '@date' => $this->dateFormatter->format($old_change_date, 'short'),
          ]);
        }
        // Unset unpublish_on so the node will not get rescheduled by subsequent
        // calls to $media->save(). Save the value for use when calling Rules.
        $media->unpublish_on->value = NULL;

        // Log the fact that a scheduled unpublication is about to take place.
        // Please confirm that `$media` is an instance of `\Drupal\Core\Entity\EntityInterface`. Only the method name and not the class name was checked for this replacement, so this may be a false positive.
        $view_link = $media->toLink(t('View media'))->toString();
        $mediatype_url = Url::fromRoute('entity.media_type.edit_form', ['media_type' => $media->bundle()]);
        // @todo \Drupal calls should be avoided in classes.
        // Replace \Drupal::l with dependency injection?
        $mediatype_link = Link::fromTextAndUrl($media->bundle() . ' ' . t('settings'), $mediatype_url)->toString();
        $logger_variables = [
          '@type' => $media_bundle->label(),
          '%title' => $media->label(),
          'link' => $mediatype_link . ' ' . $view_link,
        ];
        $this->logger->notice('@type: scheduled unpublishing of %title.', $logger_variables);

        // If scheduler_content_moderation_integration is enabled, set to
        // unpublished state.
        if ($this->schedulerModerationEnabled && $this->moderationInfo->isModeratedEntity($media)) {
          $state = $media->unpublish_state->value;
          $media->unpublish_state->value = NULL;

          /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
          $type_plugin = $this->moderationInfo->getWorkflowForEntity($media)->getTypePlugin();
          try {
            // If transition is not valid, throw exception.
            $type_plugin->getTransitionFromStateToState($media->moderation_state->value, $state);
            $media->set('moderation_state', $state);
          }
          catch (\InvalidArgumentException $exception) {
            $media->save();
            continue;
          }
        }
        else {
          // Use the actions system to publish the node.
          $this->entityTypeManager->getStorage('action')->load('media_unpublish_action')->getPlugin()->execute($media);
        }

        // Invoke event to tell Rules that Scheduler has unpublished this node.
        if ($this->moduleHandler->moduleExists('scheduler_rules_integration')) {
          _scheduler_rules_integration_dispatch_cron_event($media, 'unpublish');
        }

        // Trigger the UNPUBLISH event so that modules can react before the node
        // is unpublished.
        $event = new SchedulerMediaEvent($media);
        $dispatcher->dispatch(SchedulerEvents::UNPUBLISH, $event);
        $event->getEntity()->save();

        $result = TRUE;
      }
    }

    return $result;
  }

  /**
   * Checks whether a scheduled action on a node is allowed.
   *
   * This provides a way for other modules to prevent scheduled publishing or
   * unpublishing, by implementing hook_scheduler_media_allow_publishing() or
   * hook_scheduler_media_allow_unpublishing().
   *
   * @param \Drupal\media\Media $media
   *   The node on which the action is to be performed.
   * @param string $action
   *   The action that needs to be checked. Can be 'publish' or 'unpublish'.
   *
   * @return bool
   *   TRUE if the action is allowed, FALSE if not.
   *
   * @see hook_scheduler_media_allow_publishing()
   * @see hook_scheduler_media_allow_unpublishing()
   */
  public function isAllowed(Media $media, $action) {
    // Default to TRUE.
    $result = TRUE;
    // Check that other modules allow the action.
    $hook = 'scheduler_media_allow_' . $action . 'ing';
    foreach ($this->moduleHandler->getImplementations($hook) as $module) {
      $function = $module . '_' . $hook;
      $result &= $function($media);
    }

    return $result;
  }

  /**
   * Gather node IDs for all nodes that need to be $action'ed.
   *
   * Modules can implement hook_scheduler_nid_list($action) and return an array
   * of node ids which will be added to the existing list.
   *
   * @param string $action
   *   The action being performed, either "publish" or "unpublish".
   *
   * @return array
   *   An array of node ids.
   */
  public function midList($action) {
    $mids = [];

    foreach ($this->moduleHandler->getImplementations('scheduler_media_mid_list') as $module) {
      $function = $module . '_scheduler_media_mid_list';
      $mids = array_merge($mids, $function($action));
    }

    return $mids;
  }

  /**
   * Run the lightweight cron.
   *
   * The Scheduler part of the processing performed here is the same as in the
   * normal Drupal cron run. The difference is that only scheduler_cron() is
   * executed, no other modules hook_cron() functions are called.
   *
   * This function is called from the external crontab job via url
   * /scheduler/cron/{access key} or it can be run interactively from the
   * Scheduler configuration page at /admin/config/content/scheduler/cron.
   */
  public function runLightweightCron() {
    $log = $this->setting('log');
    if ($log) {
      $this->logger->notice('Lightweight cron run activated.');
    }
    scheduler_cron();
    if (ob_get_level() > 0) {
      $handlers = ob_list_handlers();
      if (isset($handlers[0]) && $handlers[0] == 'default output handler') {
        ob_clean();
      }
    }
    if ($log) {
      // @todo \Drupal calls should be avoided in classes.
      // Replace \Drupal::l with dependency injection?
      $this->logger->notice('Lightweight cron run completed.', ['link' => Link::fromTextAndUrl(t('settings'), Url::fromRoute('scheduler.cron_form'))->toString()]);
    }
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The value of the configuration item requested.
   */
  public function setting($key) {
    return $this->configFactory->get('scheduler_media.settings')->get($key);
  }

}
