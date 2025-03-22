<?php

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Allows modules to alter the AJAX response for parent page entity reference.
 *
 * @param \Drupal\Core\Ajax\AjaxResponse $response
 *   The AjaxResponse object that will be returned to the client.
 * @param array $form
 *   The form API render array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   Form state.
 */
function hook_mass_hierarchy_breadcrumb_ajax_alter(AjaxResponse $response, array &$form, FormStateInterface $form_state) {
  $response->addCommand(new ReplaceCommand('#mass-microsites-field-primary-parent-wrapper', $form['field_primary_parent']['widget'][0]));
}
