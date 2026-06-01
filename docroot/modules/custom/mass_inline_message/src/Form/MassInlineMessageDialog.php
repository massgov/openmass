<?php

namespace Drupal\mass_inline_message\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dialog form for inserting/editing a Message box in CKEditor.
 */
class MassInlineMessageDialog extends FormBase {

  /**
   * Maximum title length.
   */
  public const TITLE_MAX_LENGTH = 60;

  /**
   * Maximum body plain-text length.
   */
  public const BODY_MAX_LENGTH = 300;

  /**
   * Checks access for the dialog route.
   */
  public static function access(FilterFormat $filter_format, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($account->hasPermission('use text format ' . $filter_format->id()));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_inline_message_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormat $filter_format = NULL) {
    $input = $form_state->getUserInput();
    $editor_object = $input['editor_object'] ?? [];

    $form['#prefix'] = '<div id="mass-inline-message-dialog-form">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'mass-inline-message-dialog-form';
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'mass_inline_message/dialog';

    $form['attributes'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['attributes']['data-title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message title'),
      '#required' => TRUE,
      '#maxlength' => self::TITLE_MAX_LENGTH,
      '#default_value' => $editor_object['data-title'] ?? '',
    ];
    mass_inline_message_apply_maxlength($form['attributes']['data-title'], self::TITLE_MAX_LENGTH, [
      'enforce' => TRUE,
      'label' => $this->t('Content limited to @limit characters, remaining: <strong>@remaining</strong>'),
    ]);

    $form['attributes']['data-type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Message type'),
      '#required' => TRUE,
      '#options' => [
        'info' => $this->t('Informational'),
        'warning' => $this->t('Alert'),
      ],
      '#default_value' => $editor_object['data-type'] ?? 'info',
    ];

    $body_default = $editor_object['body'] ?? '';
    $format_id = $filter_format->id();
    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message text'),
      '#default_value' => $body_default,
      '#format' => $format_id,
      '#allowed_formats' => [$format_id],
      '#rows' => 4,
      '#description' => $this->t('Optional. Uses the same editor as the parent field (@format). Up to @count characters (plain text, not including HTML).', [
        '@format' => $filter_format->label(),
        '@count' => self::BODY_MAX_LENGTH,
      ]),
    ];
    if (isset($form['body']['format'])) {
      $form['body']['format']['#access'] = FALSE;
    }
    if (isset($form['body']['guidelines'])) {
      $form['body']['guidelines']['#access'] = FALSE;
    }
    mass_inline_message_apply_maxlength($form['body'], self::BODY_MAX_LENGTH, [
      'enforce' => TRUE,
      'label' => $this->t('Content limited to @limit characters, remaining: <strong>@remaining</strong>'),
    ]);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#id' => 'mass-inline-message-dialog-save',
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['button', 'button--primary', 'js-form-submit', 'form-submit'],
      ],
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitFormAjax',
        'event' => 'click',
        'wrapper' => 'mass-inline-message-dialog-form',
        'disable-refocus' => TRUE,
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
      ],
    ];

    $form['#filter_format'] = $filter_format;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $title = trim($form_state->getValue(['attributes', 'data-title']) ?? '');
    if ($title === '') {
      $form_state->setErrorByName('attributes][data-title', $this->t('Message title is required.'));
    }

    $type = $form_state->getValue(['attributes', 'data-type']);
    if (!in_array($type, ['info', 'warning'], TRUE)) {
      $form_state->setErrorByName('attributes][data-type', $this->t('Message type is required.'));
    }
  }

  /**
   * Ajax submit handler.
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      unset($form['#prefix'], $form['#suffix']);
      $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#mass-inline-message-dialog-form', $form));
      return $response;
    }

    $attributes = $form_state->getValue('attributes');
    $attributes['data-title'] = trim($attributes['data-title']);

    $body_values = $form_state->getValue('body');
    $body_raw = '';
    if (is_array($body_values)) {
      $body_raw = trim($body_values['value'] ?? '');
    }
    else {
      $body_raw = trim((string) $body_values);
    }
    $body_html = '';
    if ($body_raw !== '') {
      $filter_format = $form['#filter_format'];
      $filtered = check_markup($body_raw, $filter_format->id());
      $body_html = mass_inline_message_normalize_body_html($filtered);
    }

    $response->addCommand(new EditorDialogSave([
      'attributes' => $attributes,
      'body' => $body_html,
    ]));
    $response->addCommand(new CloseModalDialogCommand());
    $form_state->setResponse($response);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handled by ::submitFormAjax(); non-JS fallback should not redirect.
  }

}
