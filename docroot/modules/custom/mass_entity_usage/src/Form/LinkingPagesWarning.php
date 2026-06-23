<?php

declare(strict_types=1);

namespace Drupal\mass_entity_usage\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\mass_entity_usage\MassEntityUsageInterface;

/**
 * Adds a warning + wires up unpublish modal.
 */
final class LinkingPagesWarning {

  /**
   * Node bundles that should not show the unpublish linking-pages warning.
   */
  private const DISABLED_NODE_BUNDLES = [
    'alert',
    'sitewide_alert',
    'external_data_resource',
    'api_service_card',
    'utility_drawer',
  ];

  /**
   * Constructs a LinkingPagesWarning object.
   */
  public function __construct(
    private readonly MassEntityUsageInterface $entityUsage,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Alters node and media edit forms.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    if (!$entity instanceof ContentEntityInterface || $entity->isNew()) {
      return;
    }

    if (!$this->isSupported($entity)) {
      return;
    }

    // Hidden flag to bypass the modal after user confirms.
    $form['mass_linking_unpublish_confirmed'] = [
      '#type' => 'hidden',
      '#value' => '0',
    ];

    $count = $this->entityUsage->listUniqueSourcesCount($entity);
    if ($count === 0) {
      return;
    }

    $usage_page_link = $entity->toUrl()->toString() . '/mass-usage';
    $is_document = $entity->getEntityTypeId() === 'media';

    if ($is_document) {
      $modal_message = $this->translation->formatPlural(
        $count,
        'There is 1 published page linking to this document. We recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> before unpublishing.',
        'There are @count published pages linking to this document. We recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> before unpublishing.',
        ['@usagePageLink' => $usage_page_link],
      );
    }
    else {
      $modal_message = $this->translation->formatPlural(
        $count,
        'There is 1 published page linking here. You can still unpublish it <strong>if it does not have any children</strong>. However, we recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> and update it.',
        'There are @count published pages linking here. You can still unpublish it <strong>if it does not have any children</strong>. However, we recommend that you review <a href="@usagePageLink" target="_blank">pages linking here</a> and update them.',
        ['@usagePageLink' => $usage_page_link],
      );
    }

    $form['#attached']['library'][] = 'mass_entity_usage/unpublish_modal';
    $form['#attached']['drupalSettings']['massEntityUsage'] = [
      'linkingPagesCount' => $count,
      'unpublishStates' => ['unpublished', 'trash'],
      'modalTitle' => (string) $this->translation->translate('Heads up'),
      'modalMessage' => (string) $modal_message,
    ];
  }

  /**
   * Whether this entity should show the unpublish linking-pages warning.
   */
  private function isSupported(ContentEntityInterface $entity): bool {
    if ($entity->getEntityTypeId() === 'node') {
      return !in_array($entity->bundle(), self::DISABLED_NODE_BUNDLES, TRUE);
    }
    if ($entity->getEntityTypeId() === 'media') {
      return $entity->bundle() === 'document';
    }
    return FALSE;
  }

}
