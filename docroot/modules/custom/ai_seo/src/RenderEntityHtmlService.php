<?php

namespace Drupal\ai_seo;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Service to render an entity's HTML output.
 */
class RenderEntityHtmlService {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * HTML renderer.
   *
   * @var \Drupal\Core\Render\MainContent\HtmlRenderer
   */
  protected $htmlRenderer;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The HTML response attachments processor service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  protected $htmlResponseAttachmentsProcessor;

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $drupalKernel;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Creates the service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   * @param \Drupal\Core\Render\MainContent\HtmlRenderer $html_renderer
   *   HTML renderer.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $html_response_attachments_processor
   *   The HTML response attachments processor service.
   * @param \Drupal\Core\DrupalKernelInterface $drupal_kernel
   *   The Drupal kernel.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      RequestStack $request_stack,
      HtmlRenderer $html_renderer,
      RouteProviderInterface $route_provider,
      AttachmentsResponseProcessorInterface $html_response_attachments_processor,
      DrupalKernelInterface $drupal_kernel,
      LoggerChannelFactoryInterface $logger,
      MessengerInterface $messenger,
      AccountSwitcherInterface $account_switcher,
      AccountProxyInterface $current_user
    ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->htmlRenderer = $html_renderer;
    $this->routeProvider = $route_provider;
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
    $this->drupalKernel = $drupal_kernel;
    $this->logger = $logger->get('ai_seo');
    $this->messenger = $messenger;
    $this->accountSwitcher = $account_switcher;
    $this->currentUser = $current_user;
  }

  /**
   * Renders HTML for a specified entity.
   *
   * @param string $entity_type_id
   *   The type of the entity (e.g., 'node', 'user').
   * @param int $entity_id
   *   The unique identifier of the entity to be rendered.
   * @param int|null $revision_id
   *   Optional entity revision ID. (optional)
   * @param string $view_mode
   *   The view mode in which the entity will be rendered. (optional)
   *   Defaults to 'full'. Other common view modes include 'teaser', 'compact'.
   * @param string|null $langcode
   *   The language code for the rendering of the entity. (optional)
   *   If NULL, the default site language will be used.
   * @param array $options
   *  Additional options for rendering. (optional)
   *
   * @return string
   *   The HTML rendering of the entity.
   */
  public function renderHtml(string $entity_type_id, int $entity_id, int $revision_id = NULL, string $view_mode = 'full', string $langcode = NULL, array $options = []): ?string {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    if (!empty($revision_id)) {
      // If revision ID is specified, load the entity at that revision.
      $entity = $storage->loadRevision($revision_id);
    }
    else {
      // Otherwise load the default entity.
      $entity = $storage->load($entity_id);
      if (!empty($langcode) && $entity->hasTranslation($langcode)) {
        // Get translation if necessary.
        $entity = $entity->getTranslation($langcode);
      }
    }

    if (empty($entity)) {
      $this->logger->error($this->t('Entity not found - Entity type ID: :entity_type_id - Entity ID: :entity_id - Revision ID: :revision_id - Langcode: :langcode', [
        ':entity_type_id' => $entity_type_id,
        ':entity_id' => $entity_id,
        ':revision_id' => $revision_id,
        ':langcode' => $langcode,
      ]));
      return NULL;
    }

    // Check whether to request as anonymous.
    $request_as_anonymous = $options['request_as_anonymous'] ?? TRUE;

    // Switch to the anonymous user BEFORE view building to ensure proper permissions and theme.
    if ($request_as_anonymous) {
      $this->accountSwitcher->switchTo(new AnonymousUserSession());

      // Force default theme (not admin theme) for anonymous requests.
      $default_theme = \Drupal::config('system.theme')->get('default');
      $active_theme = \Drupal::service('theme.initialization')->getActiveThemeByName($default_theme);
      \Drupal::theme()->setActiveTheme($active_theme);
    }

    $viewBuilder = $this->entityTypeManager->getViewBuilder($entity_type_id);
    $build = $viewBuilder->view($entity, $view_mode);

    try {
      // Get the URL to either revision or full node.
      $url = (!empty($revision_id)) ? $entity->toUrl('revision') : $entity->toUrl();
      $url = $url->toString();

      // Create a sub request to fix the context.
      // Otherwise the response below will return invalid <head> section.
      $current_request = $this->requestStack->getCurrentRequest();

      $included_cookies = !$request_as_anonymous ? $current_request->cookies->all() : [];

      // Create the request manually so that we fetch the right.
      $server_vars = $current_request->server->all();

      // Remove authentication-related headers for anonymous requests.
      if ($request_as_anonymous) {
        unset($server_vars['HTTP_AUTHORIZATION'], $server_vars['PHP_AUTH_USER'], $server_vars['PHP_AUTH_PW']);
      }

      $request = Request::create($url, 'GET', [], $included_cookies, [], $server_vars);

      if (!$request_as_anonymous) {
        $request->setSession($current_request->getSession());
      }

      // Do the sub-request and clean up.
      $response = $this->drupalKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
      if (\Drupal::request()->getPathInfo() !== $current_request->getPathInfo()) {
        \Drupal::requestStack()->pop();
      }

      // Create a RouteMatch object with the correct context.
      $route_parameters = [$entity_type_id => $entity_id];
      $route_name = 'entity.' . $entity_type_id . '.canonical';
      $route = $this->routeProvider->getRouteByName($route_name);
      $route_match = new RouteMatch($route_name, $route, $route_parameters);

      // Render the page.
      $response = $this->htmlRenderer->renderResponse($build, $request, $route_match);

      // Finish the render.
      $response = $this->htmlResponseAttachmentsProcessor->processAttachments($response);

      // Grab the content from the response.
      $content = $response->getContent();
    }
    finally {
      if ($request_as_anonymous) {
        // Revert back to the original user.
        $this->accountSwitcher->switchBack();

        $admin_theme = \Drupal::config('system.theme')->get('admin');
        $active_theme = \Drupal::service('theme.initialization')->getActiveThemeByName($admin_theme);
        \Drupal::theme()->setActiveTheme($active_theme);
      }
    }

    return $content;
  }

}
