<?php

namespace Drupal\mass_feedback_form\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @file
 * Contains \Drupal\mass_feedback_form\Form\NodeFeedbackForm.
 */

/**
 * Configure Site Wide Notification.
 */
class NodeFeedbackForm extends ConfigFormBase {

  /**
   * Constructs a NodeFeedbackForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_node_feedback_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mass_feedback_form.feedback'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    // Disable caching on this form.
    $form_state->setCached(FALSE);

    $message = \Drupal::state()->get('mass_feedback_form.message', []);

    $form['message'] = [
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#title' => $this->t('Message on page'),
      '#description' => $this->t('The text you put into this field will appear underneath the save button on node/edit pages.'),
      '#default_value' => isset($message['value']) ? $message['value'] : '',
    ];

    $form['notification'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notification in top messages bar'),
      '#description' => $this->t('The text you put into this field will show up on the top of the page in the message notification bar area.'),
      '#default_value' => \Drupal::state()->get('mass_feedback_form.notification', ""),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set('mass_feedback_form.message', $form_state->getValue('message'));
    \Drupal::state()->set('mass_feedback_form.notification', $form_state->getValue('notification'));
    parent::submitForm($form, $form_state);

    $tags[] = 'state:mass_feedback_form.feedback';
    \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
  }

}
