<?php

namespace Drupal\mass_content;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\mass_content\Entity\Bundle\node\InfoDetailsBundle;
use Drupal\mass_content\Entity\Bundle\node\OrgPageBundle;
use Drupal\mass_content\Entity\Bundle\node\ServicePageBundle;
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
    // No login links found and we have reached the max number of ancestors.
    if ($max_level <= 0) {
      return [];
    }

    $all_links = [];
    $bundle = $entity->bundle();

    if (in_array($bundle, ['service_page', 'org_page', 'binder', 'info_details', 'curated_list'])) {
      $login_links_fields_per_bundle = [
        'service_page' => 'field_log_in_links',
        'org_page' => 'field_application_login_links',
        'binder' => 'field_application_login_links',
        'info_details' => 'field_application_login_links',
        'curated_list' => 'field_application_login_links',
      ];
      $field = $login_links_fields_per_bundle[$bundle];

      /** @var \Drupal\Core\Field\FieldItemList $login_links */
      $login_links = $entity->$field ?? FALSE;

      // Collect links if they exist.
      if ($login_links && $login_links->count()) {
        foreach ($login_links as $login_link) {
          $all_links[] = [
            'link' => $login_link,
            'source' => $entity->id(),
          ];
        }
      }
    }

    // Continue with the parent entity if available.
    $refs = $entity->getPrimaryParent()->referencedEntities();
    $parent_entity = $refs[0] ?? FALSE;
    $entities_hierarchy[] = $entity;

    // Merge the current links with those from the parent entity.
    if ($parent_entity) {
      $parent_links = $this->getContextualLoginLinks($parent_entity, $entities_hierarchy, --$max_level);
      $all_links = array_merge($all_links, $parent_links);
    }

    return $all_links;
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

      foreach ($list_links as $link_data) {
        $link = $link_data['link'];
        // Source entity ID.
        $source = $link_data['source'];

        if ($uri = $link->uri) {
          $is_external = UrlHelper::isExternal($uri);
          $links[] = [
            'type' => $is_external ? 'external' : 'internal',
            'text' => $link->computed_title,
            'href' => Url::fromUri($uri),
            'source' => $source,
          ];
          if (!$is_external && strpos($uri, 'entity:node') !== FALSE) {
            $cache_tags[] = 'node:' . preg_replace('/\D/', '', $uri);
          }
        }
      }

      // Check the login option field to decide the behavior.
      if ($node->hasField('field_login_links_options')) {
        $login_option = $node->get('field_login_links_options')->value;
        switch ($login_option) {
          case "inherit_parent_page_login_options":
            if ($links) {
              foreach ($links as $key => $link) {
                if ($link['source'] == $node->id()) {
                  unset($links[$key]);
                }
              }
            }
            break;
          case "disable_login_options":
            return [];
          case "define_new_login_options":
            // Nothing to do here.
            break;
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
    $build['log_in_links'] = [];
    $login_links_data = $this->getLoginLinksWithCacheTags($node);

    if (!empty($login_links_data)) {
      $cache_tags = [];
      $links = $login_links_data['links'];
      $theme = "c-primary";
      $usage = "secondary";
      if ($node instanceof ServicePageBundle || $node instanceof OrgPageBundle || $node instanceof InfoDetailsBundle) {
        $theme = "c-white";
        $usage = "";
      }
      $cache_tags = array_merge($cache_tags, $login_links_data['cache_tags']);
      if (!empty($links)) {
        $build['log_in_links'] = [
          "text" => 'Log In to...',
          "ariaLabelText" => "Log in to one of Mass.gov's most frequently accessed services",
          'id' => 'contextual-login-links',
          "size" => "small",
          "usage" => $usage,
          "theme" => $theme,
          "menuId" => "contextual-login-links-menu",
          'class' => 'gtm-login-contextual',
          'items' => $links,
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
    }
  }

}
