<?php

namespace Drupal\mass_analytics\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Header Configuration form class.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * State service object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    parent::__construct($config_factory);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_analytics_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $default = $this->state->get('mass_analytics.looker_studio_url', '');

    $form['looker_studio_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Looker Studio URL'),
      '#description' => $this->t('Add Looker Studio URL without query string, it will be added in the code.'),
      '#default_value' => $default,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('looker_studio_url');
    $this->state->set('mass_analytics.looker_studio_url', $value);
    parent::submitForm($form, $form_state);
  }

  /**
   * Return the configuration names.
   */
  protected function getEditableConfigNames() {
    return [
      'mass_analytics.settings',
    ];
  }

}
