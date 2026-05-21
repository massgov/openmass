<?php

namespace Drupal\mass_entity_usage\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adds a warning + wires up unpublish modal.
 */
final class LinkingPagesWarning {

  /**
   * Alters node and media edit forms.
   */
  public static function alter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    // Only existing entities.
    if ($entity->isNew()) {
      return;
    }

    $disable_bundles = ['alert', 'sitewide_alert', 'external_data_resource', 'api_service_card', 'utility_drawer'];
    if ($entity->getEntityTypeId() === 'node') {
      if (in_array($entity->bundle(), $disable_bundles)) {
        return;
      }
    }
    elseif ($entity->getEntityTypeId() === 'media') {
      if ($entity->bundle() !== 'document') {
        return;
      }
    }
    else {
      return;
    }

    // Hidden flag to bypass the modal after user confirms.
    $form['mass_linking_unpublish_confirmed'] = [
      '#type' => 'hidden',
      '#value' => '0',
    ];

    $count = \Drupal::service('mass_entity_usage.usage')->listUniqueSourcesCount($entity);

    if ($count == 0) {
      return;
    }

    // Attach library + settings for modal behavior.
    if ($count > 0) {
      $form['#attached']['library'][] = 'mass_entity_usage/unpublish_modal';
      $form['#attached']['drupalSettings']['massEntityUsage'] = [
        'linkingPagesCount' => $count,
        'unpublishStates' => ['unpublished', 'trash'],
        'modalTitle' => (string) t('Heads up'),
        'modalMessageSingular' => t('There is 1 published page linking here. You can still unpublish it <strong>if it does not have any children</strong>. However, we recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> and update it.', ['@usagePageLink' => $entity->toUrl()->toString() . '/mass-usage']),
        'modalMessagePlural' => t('There are @count published pages linking here. You can still unpublish it <strong>if it does not have any children</strong>. However, we recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> and update them.', ['@usagePageLink' => $entity->toUrl()->toString() . '/mass-usage']),
      ];
    }
  }

}
