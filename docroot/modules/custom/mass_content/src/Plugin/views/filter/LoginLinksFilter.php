<?php

namespace Drupal\mass_content\Plugin\views\filter;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Filter nodes by login links.
 *
 * @ViewsFilter("login_links_filter")
 */
class LoginLinksFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query alteration; filtering is handled in postExecute().
  }

  public function valueForm(&$form, FormStateInterface $form_state) {
    // Define the textfield with autocomplete functionality.
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login link URL'),
      '#default_value' => $this->value ?? '',
      '#description' => $this->t('Enter part of a login link URI. Internal paths support autocomplete. Filter will match any login link that contains this value.'),
      '#autocomplete_route_name' => 'system.entity_autocomplete',
      '#autocomplete_route_parameters' => [
        'target_type' => 'node',
        'selection_handler' => 'default',
        'selection_settings_key' => $this->getSelectionSettingsKey(),
      ],
      '#placeholder' => $this->t('/node/123 or https://example.com'),
      '#size' => 60,
    ];
  }

  /**
   * Generates a selection settings key for the autocomplete.
   *
   * @return string
   *   The selection settings key.
   */
  protected function getSelectionSettingsKey() {
    $selection_settings = [];
    $data = serialize($selection_settings) . 'nodedefault';
    return Crypt::hmacBase64($data, Settings::getHashSalt());
  }

  /**
   * Loads the selected node entity.
   */
  protected function getSelectedNode() {
    if (!empty($this->value) && is_numeric($this->value)) {
      return \Drupal::entityTypeManager()->getStorage('node')->load($this->value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function postExecute(&$results) {
    $filter_url = trim($this->value[0]);
    if (empty($filter_url)) {
      return;
    }

    // Filter each result row.
    foreach ($results as $index => $row) {
      $node = $row->_entity;
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $links_data = \Drupal::service('mass_content.log_in_links_builder')->getLoginLinksWithCacheTags($node);
      $match_found = FALSE;

      foreach ($links_data['links'] as $link) {
        if (!empty($link['href']) && $link['href'] instanceof Url) {
          if ($link['href']->toUriString() === $filter_url) {
            $match_found = TRUE;
            break;
          }
        }
      }

      if (!$match_found) {
        unset($results[$index]);
      }
    }
  }

}
