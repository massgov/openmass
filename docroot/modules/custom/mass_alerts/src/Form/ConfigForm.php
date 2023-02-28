<?php

namespace Drupal\mass_alerts\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom alert emails config form.
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
    return 'mass_alerts_email_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Gets current email values.
    $alert_emails = $this->state->get('mass_alerts.alert_emails', []);

    // Gather the number of emails in the form already.
    $num_emails = $form_state->get('num_emails');
    // We have to ensure that there is at least one email field.
    if ($num_emails === NULL) {
      // Tries to set number of fields according to saved values.
      if ($count = count($alert_emails)) {
        $form_state->set('num_emails', $count);
        $num_emails = $count;
      }
      else {
        $form_state->set('num_emails', 1);
        $num_emails = 1;
      }
    }

    $instructions = [
      $this->t('Enter the email address of an existing user.'),
      $this->t('You may create/register new user accounts for the intended party, but only give the party the "Authenticated" role.'),
    ];
    $form['#prefix'] = '<div class="description">' . implode('<br>', $instructions) . '</div>';

    $form['#tree'] = TRUE;
    $form['alert_emails_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email Addresses'),
      '#description' => $this->t('Input the email addresses for individuals that should receive emails when alerts are published, one per line.'),
      '#prefix' => '<div id="alert-emails-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];
    // Allows for multiple values via AJAX-controlled Add/Remove buttons.
    for ($i = 0; $i < $num_emails; $i++) {
      $form['alert_emails_fieldset']['alert_emails'][$i] = [
        '#type' => 'email',
        '#default_value' => (!empty($alert_emails)) ? $alert_emails[$i] : NULL,
        '#attributes' => [
          'placeholder' => $this->t('Email address'),
          'autocomplete' => 'off',
        ],
        '#autocomplete_route_name' => 'mass_alerts.user_autocomplete',
        '#autocomplete_route_parameters' => ['field_name' => 'mail', 'count' => 10],
      ];
    }
    $form['alert_emails_fieldset']['actions'] = [
      '#type' => 'actions',
    ];
    $form['alert_emails_fieldset']['actions']['add_email'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one email'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'alert-emails-fieldset-wrapper',
      ],
    ];
    // If there is more than one name, add the remove button.
    if ($num_emails > 1) {
      $form['alert_emails_fieldset']['actions']['remove_email'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove last email'),
        '#submit' => ['::removeOne'],
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'alert-emails-fieldset-wrapper',
        ],
      ];
    }
    // Disables form state caching to allow Add/Remove AJAX-based functionality.
    $form_state->setCached(FALSE);
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the emails in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['alert_emails_fieldset'];
  }

  /**
   * Submit handler for the "Add one" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $email_field = $form_state->get('num_emails');
    $add_button = $email_field + 1;
    $form_state->set('num_emails', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "Remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeOne(array &$form, FormStateInterface $form_state) {
    $email_field = $form_state->get('num_emails');
    if ($email_field > 1) {
      $remove_button = $email_field - 1;
      $form_state->set('num_emails', $remove_button);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Gets emails and removes empty values.
    $alert_emails = array_values(array_filter($form_state->getValue(['alert_emails_fieldset', 'alert_emails'])));
    $this->state->set('mass_alerts.alert_emails', $alert_emails);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Checks for duplicate emails.
    $alert_emails = array_values(array_filter($form_state->getValue(['alert_emails_fieldset', 'alert_emails'])));
    $dupes = array_diff_assoc($alert_emails, array_unique($alert_emails));
    foreach ($dupes as $key => $value) {
      $form_state->setErrorByName('alert_emails_fieldset][alert_emails][' . $key, $this->t('Email cannot be duplicated.'));
    }
    // Checks for missing top-level domain in email.
    // @see https://www.drupal.org/project/drupal/issues/2822142
    foreach ($alert_emails as $key => $email) {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_state->setErrorByName('alert_emails_fieldset][alert_emails][' . $key, $this->t('Email must include valid domain.'));
      }
      // Check that email address matches a user in the system.
      $query = \Drupal::entityTypeManager()->getStorage('user')->getQuery();
      $uids = $query->accessCheck(FALSE)->condition('mail', $email)
        ->range(0, 1)
        ->execute();
      if (count($uids) <= 0) {
        $form_state->setErrorByName('alert_emails_fieldset][alert_emails][' . $key, $this->t('Email must correspond to a user account.'));
      }
    }
  }

  /**
   * Return the configuration names.
   */
  protected function getEditableConfigNames() {
    return ['mass_alerts.alert_emails'];
  }

}
