<?php

namespace Drupal\mass_more_lists\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\mass_content\EventManager;
use Drupal\mass_hierarchy\MassHierarchyBasedBreadcrumbBuilder;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event pages controller.
 */
class EventsController extends ControllerBase {

  /**
   * Number of upcoming events to load and render per page.
   */
  const EVENTS_PER_PAGE = 10;

  /**
   * Number of past events to load and render per page.
   */
  const PAST_EVENTS_PER_PAGE = 100;

  private $eventManager;

  protected $breadcrumb;

  protected $routeMatch;

  protected $requestStack;

  /**
   * Pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EventManager $eventManager, MassHierarchyBasedBreadcrumbBuilder $breadcrumb, RouteMatchInterface $routeMatch, RequestStack $requestStack, PagerManagerInterface $pagerManager) {
    $this->eventManager = $eventManager;
    $this->breadcrumb = $breadcrumb;
    $this->routeMatch = $routeMatch;
    $this->requestStack = $requestStack;
    $this->pagerManager = $pagerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_content.event_manager'),
      $container->get('entity_hierarchy.breadcrumb'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('pager.manager')
    );
  }

  /**
   * Build the upcoming events page.
   */
  public function upcomingPage(NodeInterface $node) {
    if ($redirect = $this->redirectLegacyPageQuery('mass_more_lists.events_upcoming', $node)) {
      return $redirect;
    }

    // We only worry about cache tags for the parent node.
    // The parent node's tags should be cleared when a referencing
    // node is modified or added.
    // @see mass_fields_entity_clear_referenced().
    $metadata = CacheableMetadata::createFromObject($node);

    $more_link = FALSE;
    if ($this->eventManager->getPastCount($node) > 0) {
      $more_link = [
        'text' => $node->bundle() === 'event' ? t('See past related events') : t('See past events'),
        'href' => Url::fromRoute('mass_more_lists.events_past', ['node' => $node->id()]),
      ];
    }
    $breadcrumb = $this->breadcrumb->build($this->routeMatch)->toRenderable();
    $organizations = [];
    if ($node->hasField('field_organizations')) {
      $organizations = $node->field_organizations->view();
    }

    $total = $this->eventManager->getUpcomingCount($node);
    $events = [];
    $results_heading = [];
    $pager = [];
    if ($total) {
      $metadata->setCacheMaxAge($this->eventManager->getMaxAge($node));
      $page_data = $this->getPagedEvents($node, 'upcoming', $total);
      $events = $page_data['events'];
      $results_heading = $page_data['results_heading'];
      $pager = $page_data['pager'];
      $title = $node->bundle() === 'event'
        ? t('Upcoming events related to @name', ['@name' => $node->label()])
        : t('Upcoming events for @name', ['@name' => $node->label()]);
    }
    else {
      $title = $node->bundle() === 'event'
        ? t('No upcoming events related to @name', ['@name' => $node->label()])
        : t('No upcoming events for @name', ['@name' => $node->label()]);
    }

    $build = [
      '#title' => $title,
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
      '#results_heading' => $results_heading,
      '#pager' => $pager,
    ];
    // Any time an event is added, updated, or deleted, recalculate this to see if it has changed.
    $metadata->addCacheTags(['node_list:event']);
    $metadata->addCacheContexts(['url.query_args:page']);
    $metadata->applyTo($build);
    return $build;
  }

  /**
   * Build the past events page.
   */
  public function pastPage(NodeInterface $node) {
    if ($redirect = $this->redirectLegacyPageQuery('mass_more_lists.events_past', $node)) {
      return $redirect;
    }

    // We only worry about cache tags for the parent node.
    // The parent node's tags should be cleared when a referencing
    // node is modified or added.
    // @see mass_fields_entity_clear_referenced().
    $metadata = CacheableMetadata::createFromObject($node);
    $metadata->addCacheContexts(['url.query_args:page']);

    $total = $this->eventManager->getPastCount($node);
    if (!$total) {
      throw new CacheableNotFoundHttpException($metadata);
    }

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
    $organizations = [];
    if ($node->hasField('field_organizations')) {
      $organizations = $node->field_organizations->view();
    }
    $page_data = $this->getPagedEvents($node, 'past', $total);
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
      '#events' => $page_data['events'],
      '#more_link' => $more_link,
      '#results_heading' => $page_data['results_heading'],
      '#pager' => $page_data['pager'],
    ];
    // Any time an event is added, updated, or deleted, recalculate this to see if it has changed.
    $metadata->addCacheTags(['node_list:event']);
    $metadata->applyTo($build);

    return $build;
  }

  /**
   * Load one page of events and matching heading/pager render data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The parent node.
   * @param string $type
   *   Either 'past' or 'upcoming'.
   * @param int $total
   *   Total matching events.
   *
   * @return array
   *   Events, results heading, and pager render array.
   */
  private function getPagedEvents(NodeInterface $node, string $type, int $total): array {
    $limit = $type === 'past' ? self::PAST_EVENTS_PER_PAGE : self::EVENTS_PER_PAGE;
    $page = $this->pagerManager->createPager($total, $limit)->getCurrentPage();
    $offset = $page * $limit;
    $events = $type === 'past'
      ? $this->eventManager->getPast($node, $limit, $offset)
      : $this->eventManager->getUpcoming($node, $limit, $offset);

    $start = $offset + 1;
    $end = min($offset + $limit, $total);

    $pager = [];
    if ($total > $limit) {
      $pager = [
        '#type' => 'pager',
        '#tags' => [
          '',
          'Previous',
          '',
          'Next',
        ],
      ];
    }

    return [
      'events' => $events,
      'results_heading' => [
        'numResults' => $start . '–' . $end,
        'totalResults' => $total,
      ],
      'pager' => $pager,
    ];
  }

  /**
   * Convert legacy Mayflower `_page` query strings to Drupal pager `page`.
   *
   * `_page` is 1-indexed (from the client-side listing). Drupal pagers use
   * 0-indexed `page`.
   *
   * @param string $route_name
   *   The events listing route.
   * @param \Drupal\node\NodeInterface $node
   *   The parent node.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect when `_page` is present.
   */
  private function redirectLegacyPageQuery(string $route_name, NodeInterface $node): ?RedirectResponse {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request->query->has('_page')) {
      return NULL;
    }

    $query = $request->query->all();
    $mayflower_page = (int) $query['_page'];
    unset($query['_page']);
    if ($mayflower_page > 1 && !isset($query['page'])) {
      $query['page'] = $mayflower_page - 1;
    }

    return $this->redirect($route_name, ['node' => $node->id()], ['query' => $query]);
  }

}
