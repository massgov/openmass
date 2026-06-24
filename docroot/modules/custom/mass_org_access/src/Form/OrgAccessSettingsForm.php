<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mass_org_access\OrgAccessSettings;

/**
 * Landing page for the mass_org_access admin tabs.
 *
 * The Permission Groups debug switch is no longer a stored toggle: the field
 * is revealed per-request by a secret URL parameter (see buildForm). This form
 * just documents that and anchors the Import / Edit mappings local tasks. But
 * also keep it here in case we need some settings for the Permissions Group.
 */
class OrgAccessSettingsForm extends FormBase {

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
    $param = OrgAccessSettings::DEBUG_QUERY_PARAM;
    $env = OrgAccessSettings::DEBUG_SECRET_ENV;
    $secret = getenv($env);

    $intro = $this->t('The Permission Groups field is hidden on all content and documents except Organization pages. To reveal it temporarily for troubleshooting, append a secret query parameter to an edit URL. The secret is the switch — there is no on/off toggle to store, and nothing is saved here.');

    if (is_string($secret) && $secret !== '') {
      // Only users with "administer site configuration" reach this form, so it
      // is safe to print the live secret as a ready-to-use parameter.
      $detail = $this->t('Append <code>?@param=@secret</code> to an edit URL. (Shown because you can administer site configuration; the value comes from the <code>@env</code> environment variable.)', [
        '@param' => $param,
        '@secret' => $secret,
        '@env' => $env,
      ]);
    }
    else {
      $detail = $this->t('Debug mode is not configured: set the <code>@env</code> environment variable to a secret string, then append <code>?@param=&lt;secret&gt;</code> to an edit URL.', [
        '@param' => $param,
        '@env' => $env,
      ]);
    }

    $form['debug_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Permission Groups debug mode'),
      '#markup' => '<p>' . $intro . '</p><p>' . $detail . '</p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Nothing to submit — this page only documents the URL-secret debug mode.
  }

}
