<?php

namespace Drupal\mass_utility;

use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\PlaceholderGeneratorInterface;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\HttpFoundation\RequestStack;

class Renderer extends \Drupal\Core\Render\Renderer {

  public function __construct(CallableResolver|ControllerResolverInterface $callable_resolver, ThemeManagerInterface $theme, ElementInfoManagerInterface $element_info, PlaceholderGeneratorInterface $placeholder_generator, RenderCacheInterface $render_cache, RequestStack $request_stack, array $renderer_config) {
    // See https://massgov.atlassian.net/browse/DP-33081
    if (\Drupal::request()->getHost() !== 'www.mass.gov') {
      $rendererConfig['debug'] = TRUE;
    }
    parent::__construct($callable_resolver, $theme, $element_info, $placeholder_generator, $render_cache, $request_stack, $renderer_config);
  }

}
