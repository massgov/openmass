<?php

namespace Drupal\mass_content;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\mass_content_api\DescendantManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides contextual log in links based on the page being viewed.
 */
class LogInLinksBuilder {

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

    // Service and Organization pages will have their own log in links set
    // directly against their content entity. All other pages that should
    // have contextual log in links will be determine based on the results
    // of the computed log in links field.
    $manual_field_mapping = [
      'service_page' => 'field_log_in_links',
      'org_page' => 'field_application_login_links',
    ];
    $bundle = $node->bundle();
    if ((isset($manual_field_mapping[$bundle]) && $node->hasField($manual_field_mapping[$bundle]))
      || $node->hasField('computed_log_in_links')) {
      // Set the links for the two content types that should reference
      // themselves. Otherwise, get the links from the computed log in link
      // field.
      if (in_array($bundle, array_keys($manual_field_mapping))) {
        $list_links = $node->get($manual_field_mapping[$bundle]);
      }
      else {
        $list_links = $node->get('computed_log_in_links');
      }
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
