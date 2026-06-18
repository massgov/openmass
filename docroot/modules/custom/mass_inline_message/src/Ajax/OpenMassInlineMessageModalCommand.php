<?php

namespace Drupal\mass_inline_message\Ajax;

use Drupal\Core\Ajax\OpenDialogCommand;

/**
 * Opens the Message box dialog in a dedicated modal container.
 *
 * Uses #mass-inline-message-modal so nested CKEditor dialogs (entity embed,
 * media, etc.) can continue to use #drupal-modal without replacing this form.
 */
class OpenMassInlineMessageModalCommand extends OpenDialogCommand {

  /**
   * Constructs an OpenMassInlineMessageModalCommand object.
   *
   * @param string|\Stringable|null $title
   *   The title of the dialog.
   * @param string|array $content
   *   The content for the dialog.
   * @param array $dialog_options
   *   jQuery UI dialog options.
   * @param array|null $settings
   *   Drupal settings for dialog content behaviors.
   */
  public function __construct(string|\Stringable|null $title, $content, array $dialog_options = [], $settings = NULL) {
    $dialog_options['modal'] = TRUE;
    parent::__construct('#mass-inline-message-modal', $title, $content, $dialog_options, $settings);
  }

}
