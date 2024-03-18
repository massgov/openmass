<?php

namespace Drupal\mass_feedback_form\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @file
 * Contains \Drupal\mass_feedback_form\Plugin\Block\NodeFeedbackContainer.
 */

/**
 * Provides a 'NodeFeedbackContainer' block.
 */
#[Block(
  id: 'node_feedback_container',
  admin_label: new TranslatableMarkup('Node Feedback Container'),
  category: new TranslatableMarkup('Mass.gov'),
)]
class NodeFeedbackContainer extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $message = \Drupal::state()->get('mass_feedback_form.message', []);
    $notification = \Drupal::state()->get('mass_feedback_form.notification', '');
    return [
      '#theme' => 'block__node_feedback_container',
      '#message' => isset($message['value']) ? $message['value'] : '',
      '#notification' => $notification,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'state:mass_feedback_form.feedback';
    return $cache_tags;
  }

}
