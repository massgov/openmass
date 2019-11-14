<?php

namespace Drupal\mayflower\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a mayflower Block for @organisms/by-template/ajax-pattern.
 *
 * @Block(
 *   id = "mayflower_ajax_pattern",
 *   admin_label = @Translation("Ajax pattern"),
 *   category = @Translation("Mayflower"),
 * )
 */
class AjaxPatternBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['ajax_pattern_endpoint'] = [
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#title' => $this->t('Data Endpoint'),
      '#description' => $this->t('The url for the endpoint which returns a data structure for the pattern you want to render (i.e. https://www.mass.gov/jsonapi/v1/alerts).'),
      '#default_value' => isset($config['ajax_pattern_endpoint']) ? $config['ajax_pattern_endpoint'] : '',
    ];

    $form['ajax_pattern_render_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern to render'),
      '#description' => $this->t('The namespaced path to the pattern you want to render (i.e. @organisms/by-template/emergency-alerts.twig).'),
      '#default_value' => isset($config['ajax_pattern_render_pattern']) ? $config['ajax_pattern_render_pattern'] : '',
    ];

    $form['ajax_pattern_custom_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom selector'),
      '#description' => $this->t('The selector that you can use to iterate over in your js to pass a custom data transform function (i.e. js-ajax-site-alerts-jsonapi).'),
      '#default_value' => isset($config['ajax_pattern_custom_selector']) ? $config['ajax_pattern_custom_selector'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['ajax_pattern_endpoint'] = $values['ajax_pattern_endpoint'];
    $this->configuration['ajax_pattern_render_pattern'] = $values['ajax_pattern_render_pattern'];
    $this->configuration['ajax_pattern_custom_selector'] = $values['ajax_pattern_custom_selector'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    $endpoint = '';
    $pattern = '';
    $selector = '';

    if (!empty($config['ajax_pattern_endpoint'])) {
      $endpoint = $config['ajax_pattern_endpoint'];
    }

    if (!empty($config['ajax_pattern_render_pattern'])) {
      $pattern = $config['ajax_pattern_render_pattern'];
    }

    if (!empty($config['ajax_pattern_custom_selector'])) {
      $selector = $config['ajax_pattern_custom_selector'];
    }

    return [
      '#theme' => 'ajax_pattern',
      '#ajaxPattern' => [
        'endpoint' => $endpoint,
        'renderPattern' => $pattern,
        'customSelector' => $selector,
      ],
    ];
  }

}
