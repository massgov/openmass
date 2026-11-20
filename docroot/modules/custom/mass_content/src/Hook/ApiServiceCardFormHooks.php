<?php

namespace Drupal\mass_content\Hook;

use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mass_content\Entity\Bundle\node\ApiServiceCardBundle;
use Drupal\mass_content_moderation\MassModeration;

/**
 * Connects the service-card Active checkbox to its moderation workflow.
 */
class ApiServiceCardFormHooks {

  use StringTranslationTrait;

  /**
   * Constructs the service-card form hooks.
   */
  public function __construct(
    private readonly StateTransitionValidationInterface $transitionValidation,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Places publication above the tabs and submits it as a moderation transition.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getFormObject()->getEntity() instanceof ApiServiceCardBundle || !isset($form['status'])) {
      return;
    }

    unset($form['status']['#group']);
    // Moderation normally hides status; validate its mapped transition below.
    $form['status']['#access'] = TRUE;
    $form['status']['widget']['value']['#element_validate'][] = self::class . '::validateActive';
  }

  /**
   * Maps Active to the existing workflow and validates transition permissions.
   */
  public function validateActive(array &$element, FormStateInterface $form_state): void {
    $state = $element['#value'] ? MassModeration::PUBLISHED : MassModeration::UNPUBLISHED;
    $entity = $form_state->getFormObject()->getEntity();
    foreach ($this->transitionValidation->getValidTransitions($entity, $this->currentUser) as $transition) {
      if ($transition->to()->id() === $state) {
        $form_state->setValue('moderation_state', [['value' => $state]]);
        return;
      }
    }
    $form_state->setError($element, $this->t('You do not have permission to change the Active status.'));
  }

}
