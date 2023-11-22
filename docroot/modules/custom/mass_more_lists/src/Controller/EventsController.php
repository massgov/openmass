<?php

namespace Drupal\mass_more_lists\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\mass_content\EventManager;
use Drupal\mass_hierarchy\MassHierarchyBasedBreadcrumbBuilder;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Event pages controller.
 */
class EventsController extends ControllerBase {

  private $eventManager;

  protected $breadcrumb;

  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(EventManager $eventManager, MassHierarchyBasedBreadcrumbBuilder $breadcrumb, RouteMatchInterface $routeMatch) {
    $this->eventManager = $eventManager;
    $this->breadcrumb = $breadcrumb;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_content.event_manager'),
      $container->get('entity_hierarchy.breadcrumb'),
      $container->get('current_route_match')
    );
  }

  /**
   * Build the upcoming events page.
   */
  public function upcomingPage(NodeInterface $node) {
    // We only worry about cache tags for the parent node.
    // The parent node's tags should be cleared when a referencing
    // node is modified or added.
    // @see mass_fields_entity_clear_referenced().
    $metadata = CacheableMetadata::createFromObject($node);

    if ($events = $this->eventManager->getUpcoming($node, -1)) {
      $metadata->setCacheMaxAge($this->eventManager->getMaxAge($node));
      $more_link = FALSE;
      if ($this->eventManager->getPastCount($node) > 0) {
        $more_link = [
          'text' => $node->bundle() === 'event' ? t('See past related events') : t('See past events'),
          'href' => Url::fromRoute('mass_more_lists.events_past', ['node' => $node->id()]),
        ];
      }
      $breadcrumb = $this->breadcrumb->build($this->routeMatch)->toRenderable();
      if ($node->hasField('field_organizations')) {
        $organizations = $node->field_organizations->view();
      }
      $build = [
        '#title' => $node->bundle() === 'event' ? t('Upcoming events related to @name', ['@name' => $node->label()]) : t('Upcoming events for @name', ['@name' => $node->label()]),
        '#related' => [
          [
            'text' => $node->label(),
            'href' => $node->toUrl(),
          ],
        ],
        '#breadcrumb' => $breadcrumb,
        '#organizations' => $organizations,
        '#theme' => 'events_page__upcoming',
        '#events' => $events,
        '#parent' => $node,
        '#more_link' => $more_link,
      ];
      // Any time an event is added, updated, or deleted, recalculate this to see if it has changed.
      $metadata->addCacheTags(['node_list:event']);
      $metadata->applyTo($build);

      return $build;
    }
    throw new CacheableNotFoundHttpException($metadata);
  }

  /**
   * Build the past events page.
   */
  public function pastPage(NodeInterface $node) {
    // We only worry about cache tags for the parent node.
    // The parent node's tags should be cleared when a referencing
    // node is modified or added.
    // @see mass_fields_entity_clear_referenced().
    $metadata = CacheableMetadata::createFromObject($node);

    if ($events = $this->eventManager->getPast($node, -1)) {
      $more_link = FALSE;
      if ($this->eventManager->hasUpcoming($node)) {
        $more_link = [
          'text' => $node->bundle() === 'event' ? t('See all related events') : t('See upcoming events'),
          'href' => Url::fromRoute('mass_more_lists.events_upcoming', ['node' => $node->id()]),
        ];
        // This should allow it to invalidate when a future event is finished.
        $metadata->setCacheMaxAge($this->eventManager->getMaxAge($node));
      }
      $breadcrumb = $this->breadcrumb->build($this->routeMatch)->toRenderable();
      if ($node->hasField('field_organizations')) {
        $organizations = $node->field_organizations->view();
      }
      $build = [
        '#title' => $node->bundle() === 'event' ? t('Past events related to @name', ['@name' => $node->label()]) : t('Past events for @name', ['@name' => $node->label()]),
        '#related' => [
          [
            'text' => $node->label(),
            'href' => $node->toUrl(),
          ],
        ],
        '#breadcrumb' => $breadcrumb,
        '#organizations' => $organizations,
        '#theme' => 'events_page__past',
        '#events' => $events,
        '#more_link' => $more_link,
      ];
      // Any time an event is added, updated, or deleted, recalculate this to see if it has changed.
      $metadata->addCacheTags(['node_list:event']);
      $metadata->applyTo($build);

      return $build;
    }
    throw new CacheableNotFoundHttpException($metadata);
  }

}
