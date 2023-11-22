<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block for the intro text on the node add page.
 *
 * @Block(
 *   id = "request_support_link_block",
 *   admin_label = @Translation("Request support link")
 * )
 */
class SupportTicketBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $link = [
      '#markup' => '<a href="https://massgov.service-now.com/sp?id=sc_cat_item&sys_id=0bb8e784dbec0700f132fb37bf9619fe" class="button button--support-request" target="_blank">Request Support</a>',
      '#attributes' => [
        'class' => [
          'block--support-request',
        ],
      ],
    ];
    return $link;
  }

}
