<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mass_org_access\OrgAccessSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the mass_org_access module.
 *
 * Exposes the Permission Groups debug switch. The value lives in State (not
 * config) so it can be flipped per environment without a deploy and never
 * travels through configuration export.
 */
class OrgAccessSettingsForm extends FormBase {

  public function __construct(
    private readonly StateInterface $state,
    private readonly OrgAccessSettings $settings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('state'),
      $container->get('mass_org_access.settings'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mass_org_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permission Groups debug mode'),
      '#default_value' => $this->settings->isDebugModeEnabled(),
      '#description' => $this->t('When enabled, the Permission Groups field is shown to every editor — not just administrators — so you can see which organizations are attached to a page. Off by default.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->state->set(
      OrgAccessSettings::DEBUG_STATE_KEY,
      (bool) $form_state->getValue('debug_mode')
    );
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

}
