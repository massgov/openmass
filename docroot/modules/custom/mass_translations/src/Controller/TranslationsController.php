<?php

namespace Drupal\mass_translations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\Language;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;

/**
 * Class TranslationsController.
 *
 * @package Drupal\mass_translations\Controller
 */
class TranslationsController extends ControllerBase {

  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(NodeStorageInterface $node_storage) {
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function content(NodeInterface $node) {
    $markup = '';

    $languages = $this->getTranslationLanguages($node);

    foreach ($languages as $node) {
      $node_lang = $this->nodeStorage->load($node->id());
      $markup .= '<h3>' . $node_lang->language()->getName() . '</h3>';
      $markup .= Link::fromTextAndUrl($node_lang->getTitle(), $node_lang->toUrl())->toString();
    }

    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }

  /**
   * Gets all node translations based on custom English version field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   *
   * @return array
   *   Array of node IDs keyed by language code.
   */
  public function getTranslationLanguages(NodeInterface $node): array {
    $languages = [];

    $en_node_id = $node->id();

    $language = $node->language()->getId();
    if ($language !== 'en') {
      foreach ($node->get('field_english_version')->referencedEntities() as $field_english_version) {
        $en_node_id = $field_english_version->id();
      }
    }

    $languages[Language::LANGCODE_DEFAULT] = $this->nodeStorage->load($en_node_id);

    $non_english_languages = $this->nodeStorage->getQuery()
      ->condition('field_english_version', $en_node_id)
      ->execute();

    foreach ($non_english_languages as $non_english_language) {
      $non_english_node = $this->nodeStorage->load($non_english_language);
      $languages[$non_english_node->language()->getId()] = $this->nodeStorage->load($non_english_language);
    }

    return $languages;
  }

}
