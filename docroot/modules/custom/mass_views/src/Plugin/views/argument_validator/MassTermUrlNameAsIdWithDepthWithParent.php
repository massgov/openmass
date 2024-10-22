<?php

namespace Drupal\mass_views\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Validates an argument as a term URL name and converts it to the term ID.
 *
 * @ViewsArgumentValidator(
 *   id = "mass_taxonomy_term_url_name_into_id_depth_parent",
 *   title = @Translation("Taxonomy term URL Name as ID with depth and parent"),
 *   entity_type = "taxonomy_term"
 * )
 */
class MassTermUrlNameAsIdWithDepthWithParent extends Entity {

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info);
    // Not handling exploding term names.
    $this->multipleCapable = FALSE;
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
  }

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
    $returns = [];
    // The argument should match a term for custom field, field_url_name.
    $properties = ['field_url_name' => $argument];
    // If bundles is set then restrict the loaded terms to the given bundles.
    if (!empty($this->options['bundles'])) {
      $properties['vid'] = $this->options['bundles'];
    }
    $terms = $this->termStorage->loadByProperties($properties);

    // If there are no terms found matching the argument, return false.
    if (empty($terms)) {
      return FALSE;
    }
    // $terms are already bundle tested, but we need to test access control.
    foreach ($terms as $term) {
      // Set default return for this validation to FALSE.
      $returns = ['term' => FALSE];
      if ($this->validateEntity($term)) {
        // We only need one of the terms to be valid, so set the argument to
        // the term ID return TRUE when we find one.
        $this->argument->argument = $term->id();
        // Set the validation return to TRUE.
        $returns['term'] = TRUE;
      }
      else {
        continue;
      }

      // If term depth validation is enabled, validate the value.
      $parents = [];
      if ($this->options['term_depth']) {
        // Set default return for this validation to FALSE.
        $returns['term_depth'] = FALSE;
        $parents = $this->termStorage->loadAllParents($term->id());
        $depth = count($parents);
        if ((string) $depth !== (string) $this->options['term_depth_value']) {
          continue;
        }
        else {
          // Set the validation return to TRUE.
          $returns['term_depth'] = TRUE;
        }
      }

      // If the term parent validation is enabled, validate the specified
      // argument is a parent term.
      if ($this->options['term_parent']) {
        // Set default return for this validation to FALSE.
        $returns['term_parent'] = FALSE;
        // Load the term parents if term_depth validation was skipped.
        if (empty($parents)) {
          $parents = $this->termStorage->loadAllParents($term->id());
        }
        // Get the parent term argument.
        $parent_argument = $this->view->args[$this->options['term_parent_value']];
        if ($parent_argument) {
          // Load potential parent terms by argument.
          $parent_terms = $this->termStorage->loadByProperties([
            'field_url_name' => $parent_argument,
          ]);
          foreach ($parent_terms as $parent_term) {
            if ($this->validateEntity($parent_term)) {
              if (array_key_exists($parent_term->id(), $parents)) {
                // Set the validation return to TRUE.
                $returns['term_parent'] = TRUE;
                break 2;
              }
            }
          }
        }
      }
    }
    // If any validations failed, return FALSE.
    if (in_array(FALSE, $returns)) {
      return FALSE;
    }
    return TRUE;
  }

}
