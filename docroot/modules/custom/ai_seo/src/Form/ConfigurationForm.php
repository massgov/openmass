<?php

namespace Drupal\ai_seo\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_seo\AiSeoAnalyzer;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI SEO configuration form.
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * AI analyzer.
   *
   * @var \Drupal\ai_seo\AiSeoAnalyzer
   */
  protected $analyzer;

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;


  /**
   * Constructs a new ConfigurationForm object.
   *
   * @param \Drupal\ai_seo\AiSeoAnalyzer $analyzer
   *   AI analyzer.
   */
  public function __construct(AiSeoAnalyzer $analyzer, AiProviderPluginManager $provider_manager) {
    $this->analyzer = $analyzer;
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_seo.service'),
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ai_seo.configuration',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_seo_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $preferences_token = NULL) {
    // Create the form.
    $config = $this->config('ai_seo.configuration');
    // Get models from AI provider.
    $chat_models = $this->providerManager->getSimpleProviderModelOptions('chat');
    $default_chat_model = $this->providerManager->getSimpleDefaultProviderOptions('chat');

    $form['provider_and_model'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t(''),
      '#default_value' => $config->get('provider_and_model') ?? $default_chat_model,
      '#title' => $this->t('Choose Provider and Model used for SEO analyzing.'),
    ];

    $form['prompt'] = [
      '#type' => 'details',
      '#title' => $this->t('Prompts'),
      '#open' => TRUE,
    ];

    $form['prompt']['system'] = [
      '#type' => 'details',
      '#title' => $this->t('System prompt'),
      '#open' => FALSE,
    ];

    $form['prompt']['system']['default_system_prompt'] = [
      '#type' => 'textarea',
      '#readonly' => TRUE,
      '#disabled' => TRUE,
      '#title' => $this->t('Default prompt'),
      '#description' => $this->t('The default system prompt comes with the module and it is the one that is used unless a custom prompt is provided below.'),
      '#value' => $this->analyzer->getDefaultSystemPrompt(),
    ];

    $form['prompt']['system']['custom_system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#default_value' => $config->get('custom_system_prompt') ?? '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, string $preferences_token = NULL) {
    $config = $this->config('ai_seo.configuration');
    $custom_system_prompt = $form_state->getValue('custom_system_prompt') ?? '';
    $config
      ->set('custom_system_prompt', trim($custom_system_prompt))
      ->set('provider_and_model', $form_state->getValue('provider_and_model'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Page title.
   */
  public function getTitle() {
    return $this->t('Administer AI SEO analyzer settings');
  }

}
