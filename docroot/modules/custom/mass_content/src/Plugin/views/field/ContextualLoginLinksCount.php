<?php

namespace Drupal\mass_content\Plugin\views\field;

use Drupal\node\NodeInterface;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Annotation\ViewsField;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mass_content\LogInLinksBuilder;

/**
 * Views field to show the count of contextual login links.
 *
 * @ViewsField("contextual_login_links_count")
 */
class ContextualLoginLinksCount extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The login links builder service.
   *
   * @var \Drupal\mass_content\LogInLinksBuilder
   */
  protected $loginLinksBuilder;

  /**
   * Constructs the ContextualLoginLinksCount object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LogInLinksBuilder $loginLinksBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loginLinksBuilder = $loginLinksBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FieldPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mass_content.log_in_links_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Prevent Views from adding a DB query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;

    if (!$entity instanceof NodeInterface) {
      return ['#markup' => '0'];
    }

    $links_data = $this->loginLinksBuilder->getLoginLinksWithCacheTags($entity);
    $count = 0;

    if (!empty($links_data['links'])) {
      foreach ($links_data['links'] as $link) {
        // Exclude links from the current node.
        if ($link['source'] != $entity->id()) {
          $count++;
        }
      }
    }

    return ['#markup' => $count];
  }

}
