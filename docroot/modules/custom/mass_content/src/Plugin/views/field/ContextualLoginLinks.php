<?php

namespace Drupal\mass_content\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Annotation\ViewsField;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mass_content\LogInLinksBuilder;

/**
 * Views field to render contextual login links.
 *
 * @ViewsField("contextual_login_links")
 */
class ContextualLoginLinks extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The login links builder service.
   *
   * @var \Drupal\mass_content\LogInLinksBuilder
   */
  protected $loginLinksBuilder;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs the ContextualLoginLinks object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LogInLinksBuilder $loginLinksBuilder, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loginLinksBuilder = $loginLinksBuilder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FieldPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mass_content.log_in_links_builder'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;

    if (!$entity instanceof NodeInterface) {
      return ['#markup' => ''];
    }

    $links_data = $this->loginLinksBuilder->getLoginLinksWithCacheTags($entity);
    $links = [];

    if (!empty($links_data['links'])) {
      foreach ($links_data['links'] as $link) {
        if ($link['source'] == $entity->id()) {
          continue;
        }

        $url = $link['href'];
        if ($url instanceof Url) {
          if ($link['type'] === 'external') {
            $url->setOption('attributes', ['target' => '_blank']);
          }
          $links[] = [
            '#type' => 'link',
            '#title' => $link['text'],
            '#url' => $url,
          ];
        }
      }
    }

    if (!empty($links)) {
      $render_array = [
        '#theme' => 'item_list',
        '#items' => $links,
        '#attributes' => ['class' => ['contextual-login-links']],
        '#cache' => [
          'tags' => $links_data['cache_tags'] ?? [],
        ],
      ];
      return $this->renderer->render($render_array);
    }

    return [
      '#markup' => '<em>No inherited login links</em>',
    ];
  }

}
