<?php

namespace Drupal\mass_utility\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\node\Entity\NodeType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;

/**
 * Customizes New Relic transactions.
 */
class NewRelicTransactionSubscriber implements EventSubscriberInterface {

  private $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => [
        // Run after authentication (300), but before everything else.
        ['earlyRequest', 299],
        // Run after controller is determined, and after page cache.
        ['onRequest', 24],
      ],
      // Run after dynamic page cache.
      KernelEvents::RESPONSE => ['onResponse', 99],
    ];
  }

  /**
   * Create a new one.
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  /**
   * Early request handler.
   */
  public function earlyRequest(KernelEvent $event) {
    $this->setTransactionAttributes([
      'drupal_uid' => $this->currentUser->id(),
      'user_authenticated' => $this->currentUser->isAuthenticated(),
    ]);
  }

  /**
   * Handle a request event by attempting to determine some metadata.
   */
  public function onRequest(KernelEvent $event) {
    // Do nothing on subrequests.
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }
    $request = $event->getRequest();
    if ($txn_name = $this->getTransactionNameForRequest($request)) {
      $this->setTransactionName($txn_name);
    }
  }

  /**
   * Check if a response came from the dynamic page cache and name the txn.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    // Capture data for transactions that would not otherwise be named because
    // they do not match a route.
    if ($name = $this->getTransactionNameForResponse($response)) {
      $this->setTransactionName($name);
    }
    // Capture any additional response attributes.
    if ($attributes = $this->getTransactionAttributesForResponse($response)) {
      $this->setTransactionAttributes($attributes);
    }
  }

  /**
   * Determine a name for the transaction.
   *
   * We have a limit on the number of distinct transaction
   * names we can use in New Relic.  There doesn't seem to
   * be a hard cap, but 1000 is suggested.
   */
  private function getTransactionNameForRequest(Request $request) {
    $route_name = $request->attributes->get('_route');
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $request->attributes->get('_route_object');

    if ($route_name === NULL || !$route instanceof Route) {
      // Can't process if we don't have route data.
      return;
    }
    if (preg_match('~^(schemata|jsonapi)~', $route_name)) {
      // There are way too many jsonapi/schemata routes to deal with
      // here.  Just name them all JSONAPI for now and we can figure
      // out a better scheme when we need one.
      return 'JSONAPI';
    }
    if (preg_match('~^/admin/(structure|config|modules|appearance|reports)~', $route->getPath())) {
      // These are all admin level routes.  We don't care so much about
      // performance here.  Stick them all into one bucket.
      return 'Miscellaneous Admin';
    }
    // Add bundle to entity view/edit page views.
    if (preg_match('~^entity\.(\S+)\.(canonical|edit_form)$~', $route_name, $matches)) {
      if ($request->attributes->has($matches[1])) {
        $entity = $request->get($matches[1]);
        if ($entity instanceof EntityInterface) {
          $route_name = sprintf('%s:%s', $route_name, $entity->bundle());
        }
      }
    }
    // Add bundle to node add page views.
    if ($route_name === 'node.add' && $type = $request->attributes->get('node_type')) {
      if ($type instanceof NodeType) {
        $route_name = sprintf('%s:%s', $route_name, $type->id());
      }
    }

    return $route_name;
  }

  /**
   * Check if we want to override the transaction name based on the reponse.
   *
   * We handle "special" responses here (eg: dynamic cache views, redirects).
   */
  private function getTransactionNameForResponse(Response $response) {
    if ($response->headers->get(DynamicPageCacheSubscriber::HEADER) === 'HIT') {
      return 'dynamic.cache.view';
    }
    if ($response->headers->has('X-Redirect-ID')) {
      return 'redirect.redirect';
    }
    if ($response->headers->has('X-Drupal-Route-Normalizer')) {
      return 'redirect.canonical';
    }
  }

  /**
   * Gather any special attributes we want to set based on the response.
   */
  private function getTransactionAttributesForResponse(Response $response) {
    $attributes = [];
    if ($cacheability = $response->headers->get(DynamicPageCacheSubscriber::HEADER)) {
      $attributes['dynamic_cacheability'] = $cacheability;
    }
    if ($redirect_id = $response->headers->get('X-Redirect-ID')) {
      $attributes['redirect_id'] = $redirect_id;
    }
    return $attributes;
  }

  /**
   * Sends the name of the transaction to New Relic.
   */
  private function setTransactionName($name) {
    if (function_exists('newrelic_name_transaction')) {
      newrelic_name_transaction($name);
    }
  }

  /**
   * Sends an array of transaction attributes to New Relic.
   */
  private function setTransactionAttributes(array $attributes) {
    if (function_exists('newrelic_add_custom_parameter')) {
      foreach ($attributes as $key => $value) {
        newrelic_add_custom_parameter($key, $value);
      }
    }
  }

}
