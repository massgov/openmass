<?php

namespace Drupal\mass_utility;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class EntityViewBuildAlterGravityFormToken.
 *
 * This class provides functionality to alter the render array of an entity.
 * It injects a `gf_token` query parameter into an iframe URL for nodes of a
 * specific type and view mode.
 */
class EntityViewBuildAlterGravityFormToken implements ContainerInjectionInterface {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * Alters the render array of a specified entity.
   *
   * @param array $build
   *   The render array of the entity to be altered, passed by reference.
   * @param EntityInterface $entity
   *   The entity object being rendered.
   * @param EntityViewDisplayInterface $display
   *   The display configuration for the entity's view mode.
   *
   * @return void
   *   This method does not return a value, it alters the render array by reference.
   */
  public function alter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if ($entity instanceof NodeInterface
      && $entity->bundle() == 'form_page'
      && $build['#view_mode'] == 'full') {

      $build['#cache']['contexts'] = $build['#cache']['contexts'] ?? [];
      $build['#cache']['contexts'][] = 'url.query_args:gf_token';
      $build['#cache']['contexts'][] = 'user.roles';

      if (empty($build["field_form_url"][0]["#url"])) {
        return;
      }

      $iframe_url = $build["field_form_url"][0]["#url"];
      if (!($iframe_url instanceof Url)) {
        return;
      }

      $build["field_form_url"]["iframe_url"]['#markup'] = $iframe_url->toString();
      $gf_token = $this->requestStack->getCurrentRequest()->get('gf_token');
      if (empty($gf_token)) {
        return;
      }

      $iframe_url->setOption('query', ['gf_token' => $gf_token]);
      $build["field_form_url"]["iframe_url"]['#markup'] = $iframe_url->toString();
    }
  }

  /**
   * Constructs a EntityViewBuildAlterGravityFormToken object.
   *
   * @param RequestStack $request_stack
   *   The RequestStack service.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('request_stack'));
  }

}
