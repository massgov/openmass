<?php

namespace Drupal\mass_feedback_loop\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with a feedback description.
 *
 * @Block(
 *   id = "feedback_description_block",
 *   admin_label = @Translation("Feedback Description Block")
 * )
 */
class FeedbackDescriptionBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = \Drupal::routeMatch()->getRouteName();

    if ($route_name !== 'mass_feedback_loop.per_node_feedback_form') {
      return [];
    }

    $neg_url = Url::fromRoute('view.pages_with_high_negative_feedback.page_2')->toString();
    $mgr_url = Url::fromRoute('mass_feedback_loop.mass_feedback_loop_author_interface_form')->toString();

    return [
      '#markup' => '<p class="ma__page-header__description">Also see: <a href="' . $neg_url . '">Pages with high negative feedback</a> and <a href="' . $mgr_url . '">Feedback Manager</a> where you can view, filter and sort feedback submissions.</p>',
    ];
  }

}
