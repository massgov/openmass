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
   * Alters the render array of an entity view display.
   *
   * @param array $build
   *   The render array for the entity view.
   * @param EntityInterface $entity
   *   The entity being viewed.
   * @param EntityViewDisplayInterface $display
   *   The display plugin used for rendering the entity.
   *
   * @return void
   *   No return value, modifies the $build array by reference.
   */
  public function alter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if ($entity instanceof NodeInterface
      && $entity->bundle() == 'form_page'
      && $build['#view_mode'] == 'full') {

      $build['#cache']['contexts'] = $build['#cache']['contexts'] ?? [];
      $build['#cache']['contexts'][] = 'url.query_args:gf_token';
      $build['#cache']['contexts'][] = 'url.query_args:t';
      $build['#cache']['contexts'][] = 'user.roles';

      if (empty($build["field_form_url"][0]["#url"])) {
        return;
      }

      $iframe_url = $build["field_form_url"][0]["#url"];
      if (!($iframe_url instanceof Url)) {
        return;
      }

      // Normalize iframe URL to ensure path ends with slash, but preserve query parameters.
      $iframe_url->setAbsolute();
      $iframe_url = $this->normalizeUrlPath($iframe_url);

      $build["field_form_url"]["iframe_url"]['#markup'] = $iframe_url->toString();
      $gf_token = $this->requestStack->getCurrentRequest()->get('gf_token');
      if (empty($gf_token)) {
        return;
      }

      $query_params = ['gf_token' => $gf_token];
      if ($timestamp = $this->requestStack->getCurrentRequest()->get('t')) {
        $query_params['t'] = $timestamp;
      }

      $iframe_url->setOption('query', $query_params);
      $build["field_form_url"]["iframe_url"]['#markup'] = $iframe_url->toString();
    }
  }

  /**
   * Normalizes a URL's path to end with a trailing slash, preserving query parameters.
   *
   * @param \Drupal\Core\Url $url
   *   The URL object to normalize.
   *
   * @return \Drupal\Core\Url
   *   The normalized URL object.
   */
  private function normalizeUrlPath(Url $url): Url {
    $absolute_url = $url->toString();
    
    $path = parse_url($absolute_url, PHP_URL_PATH);
    
    if (!$path || $path === '/' || str_ends_with($path, '/')) {
      return $url;
    }
    
    $parsed = parse_url($absolute_url);
    if (empty($parsed['scheme']) || empty($parsed['host'])) {
      return $url;
    }

    // Reconstruct URL with normalized path while preserving query
    $normalized_url = $parsed['scheme'] . '://' . $parsed['host']
      . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
      . $parsed['path'] . '/'
      . (isset($parsed['query']) ? '?' . $parsed['query'] : '')
      . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');

    return Url::fromUri($normalized_url);
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
