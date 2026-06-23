<?php

namespace Drupal\mass_feedback_loop\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
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

    $negative_feedback_url = Url::fromRoute('view.pages_with_high_negative_feedback.page_2')->toString();
    $feedback_manager_url = Url::fromRoute('mass_feedback_loop.mass_feedback_loop_author_interface_form')->toString();
    $message = '<p class="ma__page-header__description">Also see: <a href="@negative_feedback_url">Pages with high negative feedback</a> and <a href="@feedback_manager_url">Feedback Manager</a> where you can view, filter and sort feedback submissions.</p>';
    $markup = Markup::create($this->t($message, [
      '@negative_feedback_url' => $negative_feedback_url,
      '@feedback_manager_url' => $feedback_manager_url,
    ]));

    return [
      '#markup' => $markup
    ];
  }

}
