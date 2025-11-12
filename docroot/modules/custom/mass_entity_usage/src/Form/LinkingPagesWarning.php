<?php

namespace Drupal\mass_entity_usage\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mass_entity_usage\Controller\ListUsageController;
use Drupal\node\NodeInterface;

/**
 * Adds a warning + wires up unpublish modal.
 */
final class LinkingPagesWarning {

  public static function alter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }

    // Only existing nodes.
    if ($node->isNew()) {
      return;
    }

    $disable_bundles = ['alert', 'sitewide_alert', 'external_data_resource', 'api_service_card', 'utility_drawer'];
    if (in_array($node->bundle(), $disable_bundles)) {
      return;
    }

    // Hidden flag to bypass the modal after user confirms.
    $form['mass_linking_unpublish_confirmed'] = [
      '#type' => 'hidden',
      '#value' => '0',
    ];

    $count = \Drupal::service('mass_entity_usage.usage')->listUniqueSourcesCount($node);

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
        'modalMessageSingular' => t('There is 1 published page linking here. You can still unpublish it if it does not have any children. However, we recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> and update it.', ['@usagePageLink' => $node->toUrl()->toString() . '/mass-usage']),
        'modalMessagePlural' => t('There are @count published pages linking here. You can still unpublish it if it does not have any children. However, we recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> and update them.', ['@usagePageLink' => $node->toUrl()->toString() . '/mass-usage']),
      ];
    }

    // Static, non-blocking info above the Save area.
    $message = t('There @is_are <a href="@usagePageLink" target="_blank">@n published page@s using this piece of content</a>.', [
      '@is_are' => $count === 1 ? 'is' : 'are',
      '@usagePageLink' => $node->toUrl()->toString() . '/mass-usage',
      '@n' => $count,
      '@s' => $count === 1 ? '' : 's',
    ]);

    $form['mass_linking_pages_warning'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['messages', 'messages--warning', 'mass-linking-pages-warning'],
      ],
      'markup' => ['#markup' => $message],
      '#group' => 'footer',
    ];
  }

}
