<?php

namespace Drupal\mass_decision_tree\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an administrative block for Decision Trees.
 *
 * @Block(
 *   id = "mass_decision_tree_admin_block",
 *   admin_label = @Translation("Decision Tree admin"),
 *   category = @Translation("Mass")
 * )
 */
class DecisionTreeAdminBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\mass_decision_tree\Form\DecisionTreeAdminForm');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($account->hasPermission('edit own decision_tree content')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = [
      'node_list:decision_tree',
      'node_list:decision_tree_branch',
      'node_list:decision_tree_conclusion',
    ];
    $cacheTags = Cache::mergeTags(parent::getCacheTags(), $tags);

    return $cacheTags;
  }

}
