<?php

namespace Drupal\mass_views\Plugin\views\argument_validator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views_taxonomy_term_name_into_id\Plugin\views\argument_validator\TermNameAsId;

/**
 * Validates an argument as a term name and converts it to the term ID and depth.
 *
 * @ViewsArgumentValidator(
 *   id = "mass_taxonomy_term_name_into_id_depth_parent",
 *   title = @Translation("Taxonomy term name as ID with depth and parent"),
 *   entity_type = "taxonomy_term"
 * )
 */
class MassTermNameAsIdWithDepthWithParent extends TermNameAsId {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['term_depth'] = ['default' => FALSE];
    $options['term_depth_value'] = ['default' => ''];
    $options['term_parent'] = ['default' => FALSE];
    $options['term_parent_value'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $sanitized_id = ArgumentPluginBase::encodeValidatorId($this->definition['id']);

    $form['term_depth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate the term depth'),
      '#default_value' => $this->options['term_depth'],
    ];

    $depth_options = [];
    foreach (range(1, 3) as $option) {
      $depth_options[$option] = $option;
    }

    $form['term_depth_value'] = [
      '#type' => 'radios',
      '#title' => $this->t('Term depth'),
      '#options' => $depth_options,
      '#default_value' => (string) $this->options['term_depth_value'],
      '#states' => [
        'visible' => [
          ':input[name="options[validate][options][' . $sanitized_id . '][term_depth]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['term_parent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate the term parent'),
      '#default_value' => $this->options['term_parent'],
    ];

    $arg_options = [];
    foreach (range(1, 5) as $option) {
      $arg_options[$option] = $option;
    }

    $form['term_parent_value'] = [
      '#type' => 'radios',
      '#title' => $this->t('Term parent contextual filter position'),
      '#options' => $arg_options,
      '#default_value' => (string) $this->options['term_parent_value'],
      '#states' => [
        'visible' => [
          ':input[name="options[validate][options][' . $sanitized_id . '][term_parent]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => t('The numbering starts from 1.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    if ($this->options['transform']) {
      $argument = str_replace('-', ' ', $argument);
    }

    // If bundles is set then restrict the loaded terms to the given bundles.
    if (!empty($this->options['bundles'])) {
      $terms = $this->termStorage->loadByProperties(['name' => $argument, 'vid' => $this->options['bundles']]);
    }
    else {
      $terms = $this->termStorage->loadByProperties(['name' => $argument]);
    }

    // $terms are already bundle tested but we need to test access control.
    foreach ($terms as $term) {
      if ($this->validateEntity($term)) {
        // We only need one of the terms to be valid, so set the argument to
        // the term ID return TRUE when we find one.
        $this->argument->argument = $term->id();

        // If term depth validation is enabled, validate the value.
        if ($this->options['term_depth']) {
          $parents = $this->termStorage->loadAllParents($term->id());
          $depth = count($parents);
          if ((string) $depth !== (string) $this->options['term_depth_value']) {
            return FALSE;
          }

          // If the term parent validation is enabled, validate the specified
          // argument is a parent term.
          if ($this->options['term_parent']) {
            $parent_argument = $this->view->args[$this->options['term_parent_value']];
            $parent_argument = str_replace('-', ' ', $parent_argument);
            $parent_terms = $this->termStorage->loadByProperties(['name' => $parent_argument]);
            foreach ($parent_terms as $parent_term) {
              if ($this->validateEntity($parent_term)) {
                if (!array_key_exists($parent_term->id(), $parents)) {
                  return FALSE;
                }
                return TRUE;
              }
            }
            return FALSE;
          }
          return TRUE;
        }
        // @todo If there are other values in $terms, maybe it'd be nice to
        // warn someone that there were multiple matches and we're only using
        // the first one.
      }
    }
    return FALSE;
  }

}
