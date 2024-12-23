<?php

namespace Drupal\mass_content;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides contextual log in links based on the page being viewed.
 */
class LogInLinksBuilder {

  public const MAX_ANCESTORS = 6;

  /**
   * Searches for contextual login links on current node and its ancestors.
   */
  public function getContextualLoginLinks($entity, &$entities_hierarchy = [], $max_level = self::MAX_ANCESTORS) {
    // No login links found and we have reached the max number of ancestors
    // to look for them. Bye!
    if ($max_level <= 0) {
      return [];
    }

    $bundle = $entity->bundle();
    // If page is service or organization, try to get direct links.
    if (in_array($bundle, ['service_page', 'org_page'])) {
      $login_links_fields_per_bundle = [
        'service_page' => 'field_log_in_links',
        'org_page' => 'field_application_login_links',
      ];
      $field = $login_links_fields_per_bundle[$bundle];
      /** @var \Drupal\Core\Field\FieldItemList */
      $login_links = $entity->$field ?? FALSE;
      // Login links found.
      if ($login_links && $login_links->count()) {
        $list = [];
        // Collecting links.
        foreach ($login_links as $login_link) {
          $list[] = $login_link;
        }
        return $list;
      }
    }
    $refs = $entity->getPrimaryParent()->referencedEntities();
    $parent_entity = $refs[0] ?? FALSE;
    $entities_hierarchy[] = $entity;
    return $parent_entity ? $this->getContextualLoginLinks($parent_entity, $entities_hierarchy, --$max_level) : [];
  }

  /**
   * Retrieves login links with cache tags.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to retrieve links for.
   *
   * @return array
   *   An array with 'links' and 'cache_tags'.
   */
  public function getLoginLinksWithCacheTags(NodeInterface $node) {
    $links = [];
    $cache_tags = [];

    if (
      $node->hasField('field_log_in_links') ||
      $node->hasField('field_application_login_links') ||
      $node->hasField('computed_log_in_links')
    ) {
      $entities_hierarchy = [];
      $list_links = $this->getContextualLoginLinks($node, $entities_hierarchy);

      // Adding cache tags of all the ancestors needed to build the links.
      foreach ($entities_hierarchy as $entity) {
        $cache_tags[] = 'node:' . $entity->id();
      }

      foreach ($list_links as $link) {
        if ($uri = $link->uri) {
          $is_external = UrlHelper::isExternal($uri);
          $links[] = [
            'type' => $is_external ? 'external' : 'internal',
            'text' => $link->computed_title,
            'href' => $is_external ? $uri : Url::fromUri($uri),
          ];
          if (!$is_external && strpos($uri, 'entity:node') !== FALSE) {
            $cache_tags[] = 'node:' . preg_replace('/\D/', '', $uri);
          }
        }
      }
    }

    return [
      'links' => $links,
      'cache_tags' => array_unique($cache_tags),
    ];
  }

  /**
   * Builds the render array for contextual log in links.
   *
   * @param array $build
   *   The render array to augment with contextual link data.
   * @param \Drupal\node\NodeInterface $node
   *   The node to use when adding contextual nav links.
   */
  public function buildContextualLogInLinks(array &$build, NodeInterface $node) {
    $login_links_data = $this->getLoginLinksWithCacheTags($node);

    if (!empty($login_links_data['links'])) {
      $build['contextual_log_in_links'] = [
        '#theme' => 'mass_content_contextual_log_in_links',
        '#links' => [
          'description' => [
            'richText' => [
              'rteElements' => [
                [
                  'path' => '@atoms/11-text/paragraph.twig',
                  'data' => [
                    'paragraph' => [
                      'text' => t('Log in links for this page'),
                    ],
                  ],
                ],
              ],
            ],
          ],
          'class' => 'gtm-login-contextual',
          'links' => $login_links_data['links'],
        ],
        '#cache' => [
          'tags' => $login_links_data['cache_tags'],
        ],
      ];
    }
  }

}
