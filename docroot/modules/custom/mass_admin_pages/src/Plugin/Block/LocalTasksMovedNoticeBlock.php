<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a local tasks moved notice block.
 *
 * @Block(
 *   id = "mass_admin_pages_local_tasks_moved_notice",
 *   admin_label = @Translation("Local Tasks Moved Notice"),
 *   category = @Translation("Custom")
 * )
 */
class LocalTasksMovedNoticeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $body = 'The Edit link has moved to the left toolbar. The <em>Revisions</em> and similar links have moved under the <em>Published</em> link in upper right.';
    $title = 'Edit links moved';
    $markup = <<<EOM
<div class="admonition tip">
    <div class="title">$title</div>
    <div class="content">$body</div>
</div>
EOM;
    $build['content'] = [
      '#markup' => $markup,
      '#cache' => ['max-age' => -1],
    ];
    return $build;
  }

  public function getCacheMaxAge() {
    return -1;
  }

}
