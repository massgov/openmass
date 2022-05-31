<?php

namespace Drupal\mass_content;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\mass_content_api\DescendantManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides contextual log in links based on the page being viewed.
 */
class LogInLinksBuilder {

  protected const MAX_ANCESTORS = 5;
  /**
   * Drupal\mass_content_api\DescendantManagerInterface definition.
   *
   * @var \Drupal\mass_content_api\DescendantManagerInterface
   */
  protected $descendantManager;

  /**
   * Constructor.
   *
   * @param \Drupal\mass_content_api\DescendantManagerInterface $descendant_manager
   *   The descendant manager service.
   */
  public function __construct(DescendantManagerInterface $descendant_manager) {
    $this->descendantManager = $descendant_manager;
  }

  /**
   * Searches for contextual login links on current node and its ancestors.
   */
  public function getContextualLoginLinks($entity, $max_level = SELF::MAX_ANCESTORS) {
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
          $list[] =  $login_link;
        }
        return $list;
      }
    }
    $refs = $entity->field_primary_parent->referencedEntities();
    $parent_entity = $refs[0] ?? FALSE;
    return $parent_entity ? $this->getContextualLoginLinks($parent_entity, --$max_level) : [];
  }


  /**
   * Build the list of contextual log in links for the current page.
   *
   * @param array $build
   *   The render array to augment with contextual link data.
   * @param \Drupal\node\NodeInterface $node
   *   The node to use when adding contextual nav links.
   */
  public function buildContextualLogInLinks(array &$build, NodeInterface $node) {
    $links = $list_links = $cache_tags = [];

    // Gather all the parent pages and set their ids as cache tags against
    // this child page. This way if a link is added to the parent page it'll
    // trickle down to the child page.
    $this->getParentCacheTags($cache_tags, $node->id());

    if (
      $node->hasField('field_log_in_links') ||
      $node->hasField('field_application_login_links') ||
      $node->hasField('computed_log_in_links')
      ) {

      $list_links = $this->getContextualLoginLinks($node);

      foreach ($list_links as $link) {
        $uri = $link->uri;
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
      $cache_tags = array_unique($cache_tags);

      // Add the links to the render array for the node.
      if (!empty($links)) {
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
            'links' => $links,
          ],
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
    }
  }

  /**
   * Adds the node cache tags for parent services pages to the render array.
   *
   * @param array $cache_tags
   *   The cache tags array.
   * @param int $node_id
   *   The node id of the current page.
   */
  protected function getParentCacheTags(array &$cache_tags, $node_id) {
    // Find all parent service pages up to 3 levels.
    $parents = $this->descendantManager->getParents($node_id, 3);

    foreach ($parents as $parent_level) {
      foreach ($parent_level as $parent) {
        if ($parent['type'] == 'service_page') {
          $cache_tags[] = 'node:' . $parent['id'];
        }
      }
    }
  }

}
