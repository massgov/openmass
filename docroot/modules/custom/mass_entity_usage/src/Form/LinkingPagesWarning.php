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

    // Hidden flag to bypass the modal after user confirms.
    $form['mass_linking_unpublish_confirmed'] = [
      '#type' => 'hidden',
      '#value' => '0',
    ];

    // Get count via your controller.
    /** @var \Drupal\mass_entity_usage\Controller\ListUsageController $ctrl */
    $ctrl = \Drupal::classResolver(ListUsageController::class);
    $count = $ctrl->countPublishedLinkingPages('node', (int) $node->id());

    // Attach library + settings for modal behavior.
    if ($count > 0) {
      $form['#attached']['library'][] = 'mass_entity_usage/unpublish_modal';
      $form['#attached']['drupalSettings']['massEntityUsage'] = [
        'linkingPagesCount' => $count,
        'unpublishStates' => ['unpublished', 'trash'],
        'modalTitle' => (string) t('Heads up'),
        'modalMessageSingular' => (string) t('There is 1 published page linking here. You can still unpublish.'),
        'modalMessagePlural' => (string) t('There are @count published pages linking here. You can still unpublish.'),
      ];
    }
    // Static, non-blocking info above the Save area.
    $message = t('There @is_are @n published page@s linking here.', [
      '@is_are' => $count === 1 ? 'is' : 'are',
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
