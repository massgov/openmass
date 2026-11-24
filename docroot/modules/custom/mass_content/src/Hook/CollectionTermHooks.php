<?php

namespace Drupal\mass_content\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;

/**
 * Editing workflow for Collection terms (DP-48552).
 *
 * The submit callbacks are static so that they stay serializable in cached
 * forms; mass_content_form_taxonomy_term_collections_form_alter() attaches
 * them.
 */
class CollectionTermHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Exposes the collection_operations field to Views.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['taxonomy_term_data']['collection_operations'] = [
      'title' => $this->t('Collection operations'),
      'field' => [
        'id' => 'collection_operations',
      ],
    ];
  }

  /**
   * Hides the raw term page tab on Collection edit forms.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(array &$data, string $route_name, RefinableCacheableDependencyInterface &$cacheability): void {
    if ($route_name !== 'entity.taxonomy_term.edit_form') {
      return;
    }

    $term = $this->routeMatch->getParameter('taxonomy_term');
    if (!$term instanceof TermInterface || $term->bundle() !== 'collections' || $this->currentUser->hasPermission('bypass org access')) {
      return;
    }

    unset($data['tabs'][0]['entity.taxonomy_term.canonical']);
    $cacheability->addCacheableDependency($term);
    $cacheability->addCacheContexts(['user.permissions']);
  }

  /**
   * Form submit callback: replaces the core term update message.
   */
  public static function collectionTermSaved(array &$form, FormStateInterface $form_state): void {
    $term = $form_state->getFormObject()->getEntity();
    $core_message = (string) t('Updated term %term.', ['%term' => $term->toLink()->toString()]);
    $messenger = \Drupal::messenger();
    $messages = $messenger->messagesByType(MessengerInterface::TYPE_STATUS);
    $replacement_needed = FALSE;

    foreach ($messages as $message) {
      if ((string) $message === $core_message) {
        $replacement_needed = TRUE;
        break;
      }
    }

    if (!$replacement_needed) {
      return;
    }

    $messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    foreach ($messages as $message) {
      $messenger->addStatus((string) $message === $core_message ? t('Collection updated.') : $message);
    }
  }

  /**
   * Form submit callback: sends users to the public Collection page.
   *
   * After an update core redirects to the term page when the user has view
   * access, and Rabbit Hole answers that page with a 404 for users without
   * "rabbit hole bypass taxonomy_term". A destination query still wins.
   */
  public static function collectionTermRedirect(array &$form, FormStateInterface $form_state): void {
    if (\Drupal::currentUser()->hasPermission('rabbit hole bypass taxonomy_term')) {
      return;
    }
    $term = $form_state->getFormObject()->getEntity();
    if (!$term instanceof TermInterface || !$term->hasField('field_url_name') || !empty($term->get('parent')->target_id)) {
      return;
    }
    // The Collection page denies unpublished Collections to users who cannot
    // view them; core then keeps them on the form.
    $url_name = $term->get('field_url_name')->value;
    if ($url_name && $term->access('view')) {
      $form_state->setRedirectUrl(Url::fromUserInput('/collections/' . $url_name));
    }
  }

}
