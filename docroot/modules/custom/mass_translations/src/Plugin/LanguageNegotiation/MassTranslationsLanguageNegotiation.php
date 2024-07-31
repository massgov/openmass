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
 *   id = Drupal\mass_translations\Plugin\LanguageNegotiation\MassTranslationsLanguageNegotiation::METHOD_ID,
 *   weight = -99,
 *   name = @Translation("Content language"),
 *   description = @Translation("Language from content language."),
 * )
 */
class MassTranslationsLanguageNegotiation extends LanguageNegotiationMethodBase {

  /**
   * Language negotiation method ID.
   */
  const METHOD_ID = 'language-content';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;

    $params = Url::fromUserInput($request->getPathInfo())->getRouteParameters();
    if (isset($params['node'])) {
      if ($node = Node::load($params['node'])) {
        $langcode = $node->language()->getId();
      };
    }

    return $langcode;
  }

}
