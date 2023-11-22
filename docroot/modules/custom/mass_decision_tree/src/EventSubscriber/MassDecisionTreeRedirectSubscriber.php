<?php

namespace Drupal\mass_decision_tree\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class MassDecisionTreeRedirectSubscriber.
 *
 * @package Drupal\mass_decision_tree\EventSubscriber
 */
class MassDecisionTreeRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => [
        ['redirectTreeSteps'],
      ],
    ];
  }

  /**
   * Conditionally redirects when viewing a Decision Tree Branch or Conclusion.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function redirectTreeSteps(RequestEvent $event) {
    $request = $event->getRequest();

    // Only redirect when directly viewing a node.
    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }

    // Only redirect when viewing a branch or conclusion node.
    $node = $request->attributes->get('node');
    $content_type = $node->getType();
    if (array_search($content_type, ['decision_tree_branch', 'decision_tree_conclusion']) === FALSE) {
      return;
    }

    // Redirect to the root of the decision tree, skipping ahead to this step.
    $parent = $node->field_decision_root_ref->getString();
    $redirect_url = Url::fromUri('entity:node/' . $parent)->toString();
    $response = new RedirectResponse($redirect_url . '#s=' . $node->id(), 301);
    $event->setResponse($response);
  }

}
