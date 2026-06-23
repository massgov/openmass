<?php

declare(strict_types=1);

namespace Drupal\mass_entity_usage\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mass_entity_usage\Form\LinkingPagesWarning;

/**
 * Form alter hooks for unpublish linking-pages warnings.
 */
final class FormHooks {

  /**
   * The linking pages warning form alterer.
   */
  private LinkingPagesWarning $linkingPagesWarning;

  /**
   * Constructs a FormHooks object.
   */
  public function __construct(LinkingPagesWarning $linking_pages_warning) {
    $this->linkingPagesWarning = $linking_pages_warning;
  }

  /**
   * Adds unpublish warning to node edit forms.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $this->linkingPagesWarning->alter($form, $form_state);
  }

  /**
   * Adds unpublish warning to media edit forms.
   */
  #[Hook('form_media_form_alter')]
  public function formMediaFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $this->linkingPagesWarning->alter($form, $form_state);
  }

}
