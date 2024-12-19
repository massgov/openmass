<?php

namespace Drupal\mass_translations\Plugin\LanguageNegotiation;

use Drupal\Core\Url;
use Drupal\language\LanguageNegotiationMethodBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin for using content language for language detection.
 *
 * @LanguageNegotiation(
 *   id = Drupal\mass_translations\Plugin\LanguageNegotiation\LanguageNegotiationSetFromContent::METHOD_ID,
 *   weight = -99,
 *   name = @Translation("Language set from content"),
 *   description = @Translation("Determines the content language based on the language field value set on content."),
 * )
 */
class LanguageNegotiationSetFromContent extends LanguageNegotiationMethodBase {

  /**
   * Language negotiation method ID.
   */
  const METHOD_ID = 'set-from-content';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;
    $url = Url::fromUserInput($request->getPathInfo());

    if ($url->isRouted()) {
      $params = $url->getRouteParameters();
      if (isset($params['node'])) {
        if ($node = Node::load($params['node'])) {
          $langcode = $node->language()->getId();
        };
      }
    }

    return $langcode;
  }

}
