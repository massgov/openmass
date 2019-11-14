<?php

namespace Drupal\mass_superset\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Support editing configuration for pulling superset stats.
 *
 * @package Drupal\mass_superset\Form
 */
class MassSupersetConfig extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeBundleInfo definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $bundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $bundle_info) {
    parent::__construct($config_factory);
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mass_superset.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_superset_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mass_superset.config');

    $help_text = 'The following configuration controls the timing, batch size, and content for pulling data from '
      . 'Superset. During a cron run between start and end, Drupal will queue up all nodes that match the types '
      . 'defined below. The queueing process will only run once a day. After these are queued, each subsequent cron '
      . 'run will spend 120 seconds processing the queue, pulling records based on the quantity in batch. Each cron '
      . 'run will likely process multiple batches.';

    $form['help'] = [
      '#type' => 'item',
      '#markup' => $help_text,
    ];

    $form['start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start'),
      '#description' => $this->t('Enter a time in the format HH:MM where HH is between 00 and 23 and MM is between 00 and 59.'),
      '#default_value' => $config->get('start'),
    ];
    $form['end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End'),
      '#description' => $this->t('Enter a time in the format HH:MM where HH is between 00 and 23 and MM is between 00 and 59.'),
      '#default_value' => $config->get('end'),
    ];
    $form['batch'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch Size'),
      '#min' => 1,
      '#step' => 1,
      '#default_value' => $config->get('batch'),
    ];
    $options = [];
    $bundles = $this->bundleInfo->getBundleInfo('node');
    foreach ($bundles as $key => $bundle) {
      $options[$key] = $bundle['label'];
    }

    $form['types'] = [
      '#type' => 'select',
      '#title' => $this->t('Content types'),
      '#description' => $this->t('Select content types that should have superset data pulled in.'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#size' => count($options),
      '#default_value' => $config->get('types'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $start = $form_state->getValue('start');
    if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $start)) {
      $form_state->setErrorByName('start', 'Start has an incorrect format.');
    }
    $end = $form_state->getValue('end');
    if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $end)) {
      $form_state->setErrorByName('end', 'End has an incorrect format.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mass_superset.config');
    $config->set('batch', $form_state->getValue('batch'));
    $config->set('types', $form_state->getValue('types'));
    $config->set('start', $form_state->getValue('start'));
    $config->set('end', $form_state->getValue('end'));
    list($start_hour, $start_minute) = explode(':', $form_state->getValue('start'));
    list($end_hour, $end_minute) = explode(':', $form_state->getValue('end'));
    if ($start_hour > $end_hour) {
      $max_interval = ((int) $end_hour + 24 - (int) $start_hour) * 60 * 60;
    }
    else {
      $max_interval = ((int) $end_hour - (int) $start_hour) * 60 * 60;
    }
    $max_interval += (int) $end_minute * 60 - (int) $start_minute * 60;
    $config->set('max_interval', $max_interval);

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
