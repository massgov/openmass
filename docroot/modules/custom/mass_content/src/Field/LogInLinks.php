<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Component\Utility\UrlHelper;

/**
 * Generates the contextual log in links for a page's header.
 */
class LogInLinks extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * The maximum number of links to be returned for the log in link list.
   *
   * @var int
   */
  protected const MAX_NUM_LINKS = 8;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      /* @var \Drupal\mass_content_api\DescendantManagerInterface $descendantManager */
      $descendantManager = \Drupal::service('descendant_manager');
      $delta = 0;
      $limit = self::MAX_NUM_LINKS;
      $type = 'service_page';
      $field_name = 'field_log_in_links';
      $ancestors = $descendantManager->getParents($entity->id(), 3);

      // Get the parent nodes defined in descendant manager and filter out all
      // of the non-service page nodes. Load the Service page nodes found and
      // use them to generate the contextual links.
      if (!empty($ancestors)) {
        $services = [];
        foreach ($ancestors as $level_ancestors) {
          foreach ($level_ancestors as $level_ancestor) {
            if ($level_ancestor['type'] == $type) {
              $services[] = $level_ancestor['id'];
            }
          }
          // If a service has been found, then exit the loop and don't look
          // for additional ancestral services.
          if (!empty($services)) {
            break;
          }
        }
        // If services were found, load them and get their log in links.
        if (!empty($services)) {
          $service_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($services);
          $link_list = [];
          $link_title_list = [];
          foreach ($service_nodes as $service_node) {
            $service_node_link_count = 1;
            foreach ($service_node->get($field_name) as $link) {
              $uri = $link->uri;
              // Check for duplicate titles. If the current link is using a
              // title that has already been used, then skip this link entirely
              // and move on to the next link.
              $link_title = $link->title;
              if (in_array($link_title, $link_title_list)) {
                continue;
              }
              else {
                $link_title_list[] = $link_title;
              }
              $is_external = UrlHelper::isExternal($uri);
              $scheme = substr($uri, 0, strpos($uri, ':'));
              $uri = rtrim($uri, '/');
              if ($is_external) {
                // Remove trailing slashes.
                // Standardize the schema of a given URL.
                $preferred_url = TRUE;
                // Replace all http with https just in case there are links to
                // the same URL that have different schemes. This is just for
                // comparison and will not affect the selected URL, unless there
                // is a duplicate URL that uses https. Then prefer that over an
                // insecure URL.
                if ($scheme === 'http') {
                  $uri = str_replace('http', 'https', $uri);
                  $preferred_url = FALSE;
                }
                // Allow for URLs with and without www to be considered the same
                // for de-duping purposes.
                if (strpos($uri, '//www.') !== FALSE) {
                  $uri = str_replace('//www.', '//', $uri);
                }
                $in_list = !empty($link_list[$uri]);
                if (!$in_list || ($in_list && $preferred_url)) {
                  $link_list[$uri] = $link;
                }
                // If this is already in the list, then skip incrementing and
                // move on to the next element in the loop.
                if ($in_list) {
                  continue;
                }
              }
              // Handle de-duping of internal relative URLs by parsing the path
              // regardless of any querystrings or additional fragments.
              elseif ($scheme === 'internal') {
                $uri = str_replace('internal:', '', $uri);
                $parsed = parse_url($uri);
                if (!empty($link_list[$parsed['path']])) {
                  continue;
                }
                $link_list[$parsed['path']] = $link;
              }
              // If the link isn't external or a relative internal path, then
              // it is routed to an entity. Simply perform straightforward
              // checking to see if that entity is already referenced.
              else {
                if (!empty($link_list[$uri])) {
                  continue;
                }
                $link_list[$uri] = $link;
              }
              // Increment the overall number of links by one for the computed
              // field.
              $delta++;
              // Increment the number of links that have been loaded from this
              // service page. Impose a limit of 8 links per service page since
              // the field itself will not impose this limit.
              $service_node_link_count++;
              $break_loop = $service_node_link_count > self::MAX_NUM_LINKS;
              $hard_break_loop = $delta > $limit;
              if ($break_loop) {
                // Only break out of this loop if the maximum number of links
                // for this computed field hasn't yet been reached.
                if (!$hard_break_loop) {
                  break;
                }
              }
              if ($hard_break_loop) {
                return;
              }
            }
          }
          $this->list = array_values($link_list);
        }
      }
    }
  }

}
