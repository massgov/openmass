<?php

declare(strict_types=1);

namespace Drupal\mass_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views_autocomplete_filters\Plugin\views\filter\ViewsAutocompleteFiltersCombine;
use Wamania\Snowball\StemmerManager;

class StemmableCombineFilter extends ViewsAutocompleteFiltersCombine {

  public function defineOptions() {
    $options = parent::defineOptions();
    $options['expose']['contains']['stem_query'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultExposeOptions() {
    parent::defaultExposeOptions();
    $this->options['expose']['stem_query'] = FALSE;
  }

  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    $form['expose']['stem_query'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stem the search query to return more inexact results'),
      '#description' => $this->t('@todo: Link to upstream docs on the porter-stemmer algorithm'),
      '#default_value' => $this->options['expose']['stem_query'],
    ];
  }

  public function query() {
    if (isset($this->options['expose']['stem_query']) && $this->options['expose']['stem_query']) {
      $manager = new StemmerManager();
      $this->value = $manager->stem($this->value, 'en');
    }
    parent::query();
  }

}
