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
    $all_links = [];
    // Tracks if any parent disables login links.
    $disable_inheritance = FALSE;
    $first_defined_level = FALSE;

    while ($entity && $max_level > 0) {
      $bundle = $entity->bundle();
      // Track hierarchy for cache tags.
      $entities_hierarchy[] = $entity;

      // Process only specific bundles.
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

        // Check the login option field to decide the behavior.
        if ($entity->hasField('field_login_links_options')) {
          $login_option = $entity->get('field_login_links_options')->value;
        }
        elseif ($entity instanceof OrgPageBundle) {
          $login_option = "define_new_login_options";
        }
        if (isset($login_option)) {
          switch ($login_option) {
            case "inherit_parent_page_login_options":
              // Continue processing parents unless a parent disables inheritance.
              break;

            case "disable_login_options":
              // Stop processing and disable links for this and all descendants.
              $disable_inheritance = TRUE;
              break;
            case "define_new_login_options":
              // Collect links from this node and stop processing further.
              if (!$first_defined_level) {
                // Mark that we found the first define_new_login_options.
                $first_defined_level = TRUE;
                $all_links = [];
                if ($login_links && $login_links->count()) {
                  foreach ($login_links as $login_link) {
                    $all_links[] = [
                      'link' => $login_link,
                      'source' => $entity->id(),
                    ];
                  }
                }
              }
              break;
          }
        }

        if ($disable_inheritance) {
          return [];
        }
      }

      // Move to the parent node.
      $refs = $entity->getPrimaryParent()->referencedEntities();
      $entity = $refs[0] ?? NULL;
      // Reduce max levels to prevent infinite loops.
      $max_level--;
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
      if ($node instanceof ServicePageBundle || $node instanceof OrgPageBundle) {
        $theme = "c-white";
        $usage = "";
      }

      $cache_tags = array_merge($cache_tags, $login_links_data['cache_tags']);
      if (isset($build['#cache']['tags'])) {
        $build['#cache']['tags'] = array_merge($cache_tags, $build['#cache']['tags']);
      }
      else {
        $build['#cache']['tags'] = $cache_tags;
      }

      if (!empty($links)) {
        $build['log_in_links'] = [
          "text" => 'Log In to...',
          "ariaLabelText" => "Log in to one of Mass.gov's most frequently accessed services",
          'id' => 'gtm-loginto',
          "usage" => $usage,
          "theme" => $theme,
          "menuId" => "contextual-login-links-menu",
          'class' => 'gtm-login-contextual',
          'items' => $links,
        ];
      }
    }
  }

}
