<?php

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Bundle;

/**
 * Filters by node bundles that user can add or edit.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_views_user_permitted_bundle")
 */
class UserPermittedBundle extends Bundle {

  /**
   * {@inheritdoc}
   *
   * Overrides the Bundle function to provide a more limited list.
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $types = $this->bundleInfoService->getBundleInfo($this->entityTypeId);
      $this->valueTitle = $this->t('@entity types', ['@entity' => $this->entityType->getLabel()]);

      $options = [];
      // We only know how to check permissions for nodes.
      // This check may not be necessary.
      if ('node' == $this->entityTypeId) {
        $user = \Drupal::currentUser();
        // The bypass node access permission is not dependent on bundle.
        $skip_check = $user->hasPermission('bypass node access');
        // Add each bundle if the user has add/edit permissions on that bundle.
        foreach ($types as $type => $info) {
          if ($skip_check) {
            $options[$type] = $info['label'];
            continue;
          }
          $perms_to_check = [
            'create ' . $type . ' content',
            'edit any ' . $type . ' content',
            'edit own ' . $type . ' content',
          ];
          foreach ($perms_to_check as $perm) {
            if ($user->hasPermission($perm)) {
              $options[$type] = $info['label'];
              break;
            }
          }
        }
      }
      // This code is probably never reached.
      else {
        foreach ($types as $type => $info) {
          $options[$type] = $info['label'];
        }
      }

      asort($options);
      $this->valueOptions = $options;
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides the InOperator function to provide a custom list.
   */
  public function operators() {
    $operators = [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'short_single' => $this->t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
      'not empty' => [
        'title' => $this->t('Is a permitted content type'),
        'method' => 'opEmpty',
        'short' => $this->t('not empty'),
        'values' => 0,
      ],
    ];

    return $operators;
  }

  /**
   * Checks that content type is one of selected item(s).
   *
   * Overrides the IN/NOT IN function in the InOperator class.
   */
  protected function opSimple() {
    if (empty($this->value)) {
      return;
    }
    $bundles = array_values($this->value);
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $bundles, $this->operator);
  }

  /**
   * Chackes that content type is permitted to the user.
   *
   * Overrides the NOT EMPTY action in the InOperator class.
   */
  protected function opEmpty() {
    $this->ensureMyTable();
    $bundles = array_keys($this->getValueOptions());
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $bundles, 'IN');
  }

}
