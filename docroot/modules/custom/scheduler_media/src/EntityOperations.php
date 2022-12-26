<?php

namespace Drupal\scheduler_media;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\scheduler\SchedulerEvents;

/**
 * Defines a class for reacting to entity events.
 *
 * @internal
 */
class EntityOperations implements ContainerInjectionInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The event dispatcher service.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The scheduler media manager service.
   *
   * @var \Drupal\scheduler_media\SchedulerMediaManager
   */
  protected $schedulerMediaManager;

  /**
   * Constructs a new EntityOperations object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The data formatter service.
   * @param \Drupal\scheduler_media\SchedulerMediaManager $scheduler_media_manager
   *   The scheduler media manager service.
   */
  public function __construct(ConfigFactory $config_factory,
    ContainerAwareEventDispatcher $event_dispatcher,
    DateFormatter $date_formatter,
    SchedulerMediaManager $scheduler_media_manager) {
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $event_dispatcher;
    $this->dateFormatter = $date_formatter;
    $this->schedulerMediaManager = $scheduler_media_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('event_dispatcher'),
      $container->get('date.formatter'),
      $container->get('scheduler_media.manager')
    );
  }

  /**
   * Get scheduler settings.
   */
  protected function getSettings() {
    return $this->configFactory->get('scheduler.settings');
  }

  /**
   * Determine if the media entity is able to be published via scheduler.
   *
   * @param \Drupal\media\Entity\MediaType $media
   *   The media entity.
   */
  protected function isPublishEnabled(MediaType $media) {
    return $this->schedulerMediaManager->isDefaultSetting($media, 'publish_enable') && !empty($media->publish_on->value);
  }

  /**
   * Determine whether the media entity should be published on save.
   *
   * @param \Drupal\media\Entity\Media $entity
   *   The media entity.
   */
  protected function isPublishImmediately(Media $entity) {
    $media_entity = $entity->bundle->entity;
    return $this->schedulerMediaManager->isAllowed($entity, 'publish') && $this->schedulerMediaManager->isDefaultSetting($media_entity, 'publish_past_date');
  }

  /**
   * Detect if the current route is a media page.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media entity.
   */
  protected function mediaIsPage(Media $media) {
    $route_match = \Drupal::routeMatch();
    if ($route_match->getRouteName() == 'entity.media.canonical') {
      $page_media = $route_match->getParameter('media');
    }
    return (!empty($page_media) ? $page_media->id() == $media->id() : FALSE);
  }

  /**
   * Acts on an entity and set published status based on the moderation state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   *
   * @see hook_entity_presave()
   */
  public function entityPresave(EntityInterface $entity) {
    $config = $this->getSettings();
    $media_entity = NULL;

    if ($entity instanceof ContentEntityInterface || $entity instanceof ContentEntityForm) {
      $media_entity = $entity->bundle->entity;
    }

    if ($entity->getEntityTypeId() === 'media') {
      // Only set the publish_state value if the publish_on field is set.
      if (($entity->hasField('publish_on') && $entity->hasField('publish_state')) && !empty($entity->publish_on->value)) {
        $entity->publish_state->setValue('published');
      }
      // Only set the unpublish_state value if the unpublish_on field is set.
      if (($entity->hasField('unpublish_on') && $entity->hasField('unpublish_state')) && !empty($entity->unpublish_on->value)) {
        $entity->unpublish_state->setValue('unpublished');
      }
    }

    if (is_null($media_entity) || !get_class($media_entity) == 'Drupal\media\Entity\MediaType') {
      return;
    }

    if (isset($entity->devel_generate)) {
      static $publishing_enabled_types;
      static $unpublishing_enabled_types;
      static $publishing_percent;
      static $unpublishing_percent;
      static $time_range;

      if (!isset($publishing_enabled_types)) {
        $publishing_enabled_types = array_keys(_scheduler_media_get_scheduler_media_enabled_media_types('publish'));
        $unpublishing_enabled_types = array_keys(_scheduler_media_get_scheduler_media_enabled_media_types('unpublish'));
        $publishing_percent = $entity->devel_generate['scheduler_media_publishing'];
        $unpublishing_percent = $entity->devel_generate['scheduler_media_unpublishing'];
        // Reuse the selected 'media creation' time range for our future date span.
        $time_range = $entity->devel_generate['time_range'];
      }
      if ($publishing_percent && in_array($entity->getType(), $publishing_enabled_types)) {
        if (rand(1, 100) <= $publishing_percent) {
          // Randomly assign publish_on value in the range starting with the
          // created date and up to the selected time range in the future.
          $entity->set('publish_on', rand($entity->created->value + 1, \Drupal::time()->getRequestTime() + $time_range));
        }
      }
      if ($unpublishing_percent && in_array($entity->getType(), $unpublishing_enabled_types)) {
        if (rand(1, 100) <= $unpublishing_percent) {
          // Randomly assign an unpublish_on value in the range from the later of
          // created datpublish_on date up to the time range in the future.
          $entity->set('unpublish_on', rand(max($entity->created->value, $entity->publish_on->value), \Drupal::time()->getRequestTime() + $time_range));
        }
      }
    }

    if ($this->schedulerMediaManager->isDefaultSetting($media_entity, 'publish_enable') && !empty($entity->publish_on->value)) {
      $publication_allowed = $this->schedulerMediaManager->isAllowed($entity, 'publish');
      if ($this->schedulerMediaManager->isAllowed($entity, 'publish') && $this->isPublishImmediately($entity)
          && $entity->publish_on->value <= \Drupal::time()->getRequestTime()) {

        $event = new SchedulerMediaEvent($entity);
        $this->eventDispatcher->dispatch(SchedulerEvents::PRE_PUBLISH_IMMEDIATELY, $event);
        $entity = $event->getEntity();

        if ($this->schedulerMediaManager->isDefaultSetting($media_entity, 'publish_touch')) {
          $entity->setCreatedTime($media_entity->publish_on->value);
        }

        $entity->publish_on->value = NULL;
        $entity->setPublished(TRUE);

        $event = new SchedulerMediaEvent($entity);
        $this->eventDispatcher->dispatch(SchedulerEvents::PUBLISH_IMMEDIATELY, $event);
        $entity = $event->getEntity();
      }
      else {
        $entity->setPublished(FALSE);

        if ($publication_allowed) {
          \Drupal::messenger()->addStatus(t('This post is unpublished and will be published @publish_time.', [
            '@publish_time' => $this->dateFormatter->format($entity->publish_on->value, 'long'),
          ]), FALSE);
        }
      }
    }

    if ($this->schedulerMediaManager->isDefaultSetting($media_entity, 'unpublish_enable') && !empty($entity->unpublish_on->value)) {
      $this->schedulerMediaManager->isAllowed($entity, 'unpublish');
    }
  }

  /**
   * Act on entities being assembled before rendering.
   *
   * @see hook_entity_view()
   * @see EntityFieldManagerInterface::getExtraFields()
   */
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    if ($entity->getEntityTypeId() !== 'media') {
      return;
    }
    if ($this->mediaIsPage($entity) && !empty($entity->unpublish_on->value)) {
      $unavailable_after = date(DATE_RFC850, $entity->unpublish_on->value);
      $build['#attached']['http_header'][] = ['X-Robots-Tag', 'unavailable_after: ' . $unavailable_after];
    }
  }

}
