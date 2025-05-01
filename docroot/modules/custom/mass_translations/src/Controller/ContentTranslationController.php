<?php

namespace Drupal\mass_translations\Controller;

use Drupal\content_translation\Controller\ContentTranslationController as ContentTranslationControllerBase;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Overrides the content translation controller.
 */
class ContentTranslationController extends ContentTranslationControllerBase {

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    $build = parent::overview($route_match, $entity_type_id);

    foreach ($build["content_translation_overview"]["#rows"] as &$row) {
      foreach ($row as &$cell) {
        if ($cell instanceof GeneratedLink) {
          $cell = new TranslatableMarkup($this->addContentLangToLink((string) $cell));
          continue;
        }

        if (!is_array($cell) || !isset($cell['data']) || empty($cell["data"]["#links"])) {
          continue;
        }

        $links = &$cell["data"]["#links"];
        $this->addLanguageParameterToUrl($links, "edit");
        $this->addLanguageParameterToUrl($links, "delete");
      }
    }

    return $build;
  }

  /**
   * Adds language parameter to a specific URL in the links array.
   *
   * @param array $links
   *   The links array containing URLs to process.
   * @param string $link_type
   *   The type of link to process (edit, delete, etc.).
   */
  private function addLanguageParameterToUrl(array &$links, string $link_type): void {
    if (empty($links[$link_type]['url']) || !$links[$link_type]['url'] instanceof Url) {
      return;
    }

    /** @var \Drupal\Core\Url $url */
    $url = &$links[$link_type]['url'];
    /** @var \Drupal\Core\Language\Language $language */
    $language = &$links[$link_type]['language'];

    $session_negotiator_parameter = $this->getLanguageNegotiatorParameter();
    $url->setOption('query', [$session_negotiator_parameter => $language->getId()]);
  }

  /**
   * Modifies an HTML anchor tag to include the hreflang value as a query parameter.
   *
   * @param string $html
   *   The HTML anchor tag to modify.
   *
   * @return string
   *   The modified HTML with content_lang query parameter added.
   */
  private function addContentLangToLink(string $html): string {
    // Use a regex pattern to extract the href and hreflang attributes
    if (!preg_match('/<a\s+[^>]*href="([^"]*)"[^>]*hreflang="([^"]*)"[^>]*>/', $html, $matches) &&
      !preg_match('/<a\s+[^>]*hreflang="([^"]*)"[^>]*href="([^"]*)"[^>]*>/', $html, $matches2)) {
      // If neither pattern matches, return the original HTML
      return $html;
    }

    // Check which pattern matched and set variables accordingly
    if (!empty($matches)) {
      $href = $matches[1];
      $hreflang = $matches[2];
    } else {
      $href = $matches2[2];
      $hreflang = $matches2[1];
    }

    // Prepare the new URL with content_lang parameter
    $session_negotiator_parameter = $this->getLanguageNegotiatorParameter();
    $separator = (str_contains($href, '?')) ? '&' : '?';
    $newHref = $href . $separator . $session_negotiator_parameter . '=' . $hreflang;

    // Replace the original href with the new one
    return str_replace('href="' . $href . '"', 'href="' . $newHref . '"', $html);
  }

  /**
   * Retrieves the language negotiator session parameter.
   *
   * This method utilizes the language negotiator service and the current user
   * service to determine the configuration for language negotiation. The session
   * parameter is fetched from the configuration factory service.
   *
   * @return string|null
   *   Returns the session parameter for language negotiation if available, or
   *   null if not set.
   */
  private function getLanguageNegotiatorParameter() {
    $negotiator = \Drupal::service('language_negotiator');
    $current_user = \Drupal::currentUser();
    $negotiator->setCurrentUser($current_user);
    return \Drupal::service('config.factory')
      ->get('language.negotiation')
      ->get('session.parameter');
  }


}
