<?php

namespace Drupal\scheduler_media;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines whether a route is the "Latest version" tab of a node.
 *
 * @internal
 */
class ContentPreprocess implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   Date formatter service.
   */
  public function __construct(DateFormatter $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter')
    );
  }

  /**
   * Add the publish_on and unpublish_on dates as content variables.
   *
   * @param array $variables
   *   Theme variables to preprocess.
   *
   * @see hook_preprocess_HOOK()
   */
  public function preprocessMedia(array &$variables) {
    $media = $variables['media'];
    if ($this->isScheduledForPublish($media)) {
      $variables['publish_on'] = $this->dateFormatter->format($media->publish_on->value, 'long');
    }
    if ($this->isScheduledForUnpublish($media)) {
      $variables['unpublish_on'] = $this->dateFormatter->format($media->unpublish_on->value, 'long');
    }
  }

  /**
   * Checks whether the publish_on field is populated for a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   A media entity.
   *
   * @return bool
   *   True if the publish_on field is populated for the given media entity.
   */
  public function isScheduledForPublish(Media $media) {
    return !empty($media->publish_on->value) && $media->publish_on->value
           && is_numeric($media->publish_on->value);
  }

  /**
   * Checks whether the unpublish_on field is populated for a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   A media entity.
   *
   * @return bool
   *   True if the unpublish_on field is populated for the given media entity.
   */
  public function isScheduledForUnpublish(Media $media) {
    return !empty($media->unpublish_on->value) && $media->unpublish_on->value
           && is_numeric($media->unpublish_on->value);
  }

}
