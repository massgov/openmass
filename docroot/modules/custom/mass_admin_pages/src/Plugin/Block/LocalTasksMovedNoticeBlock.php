<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

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
    $body = 'The Edit link has moved to the left side of toolbar. The <em>Revisions</em> and similar links have moved under the <em>Published</em> link in upper right.';
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

  /**
  * {@inheritdoc}
  */
  protected function blockAccess(AccountInterface $account) {
    // @todo cacheability.
    $node = \Drupal::routeMatch()->getParameter('node');
    return AccessResult::allowedIfHasPermission($account, 'use mass dashboard')
      ->andIf(AccessResult::allowedIf($node))
      ->addCacheableDependency($node);
  }

  public function getCacheMaxAge() {
    return -1;
  }

}
