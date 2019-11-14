<?php

namespace Drupal\mass_feedback_form\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'PageFeedbackForm' block.
 *
 * IMPORTANT: this functionality is used in the feedback form. Contact the
 * data team before making changes here.
 *
 * @Block(
 *  id = "page_feedback_form",
 *  admin_label = @Translation("Page feedback form"),
 * )
 */
class PageFeedbackForm extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          'mass_feedback_form/feedback-form',
        ],
      ],
      '#theme' => 'mass_feedback_form',
      '#node_id' => [
        '#lazy_builder' => [
          'mass_utility.lazy_builder:currentNid', [],
        ],
        '#create_placeholder' => TRUE,
      ],
    ];

    return $build;
  }

}
