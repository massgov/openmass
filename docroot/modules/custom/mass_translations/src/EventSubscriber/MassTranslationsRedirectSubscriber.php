<?php

namespace Drupal\mass_translations\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\mass_translations\MassTranslationsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects users when browser langcode doesn't match node langcode.
 *
 * If a translation exists for the current node, the user is redirected
 * permanently to the node that matches the browser language. This can be
 * disabled by disabling the Browser language detection method.
 */
class MassTranslationsRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The 'MassTranslationsService' service.
   *
   * @var \Drupal\mass_translations\MassTranslationsService
   */
  protected $massTranslationsService;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigurableLanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, MassTranslationsService $mass_translations_service) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->massTranslationsService = $mass_translations_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['redirectToTranslation'];
    return $events;
  }

  /**
   * Redirect requests for an English node to correct translation node.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function redirectToTranslation(GetResponseEvent $event) {
    $request = $event->getRequest();

    // If the path is not to a node page, skip redirect logic.
    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }

    // If the browser langcode matches the node language, skip redirect logic.
    $node = $request->attributes->get('node');
    $node_langcode = $node->get('langcode')->getValue()[0]['value'];
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    if ($current_langcode === $node_langcode) {
      return;
    }

    // Get translations for the current node.
    $languages = $this->massTranslationsService->getTranslationLanguages($node, $this->entityTypeManager->getStorage('node'), 'field_english_version');

    foreach ($languages as $entity) {
      // If a translation is found, redirect to that node.
      if ($current_langcode === $entity->get('langcode')->getValue()[0]['value']) {
        // Redirect to the correct translation using a 301 Moved Permanently
        // redirect code.
        $response = new RedirectResponse($entity->toUrl()->toString(), 301);
        $event->setResponse($response);
      }
    }
  }

}
