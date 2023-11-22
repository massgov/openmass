<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;

/**
 * A computed field class for page flipper links.
 */
class PageFlipperLink extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    $direction = $this->getSetting('direction');
    if (!$entity->isNew()) {
      $links = $this->calculateLinks($entity, $direction);
      if (!empty($links)) {
        $this->list[0] = $this->createItem(0, $links);
      }
    }
  }

  /**
   * Calculate the links associated with a binder.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to use to calculate links.
   * @param string $direction
   *   Whether the link is a previous or next link.
   *
   * @return array
   *   The links associated with a binder and the entity.
   */
  private function calculateLinks(EntityInterface $entity, $direction) {
    $links = &drupal_static(__FUNCTION__);
    if (isset($links[$entity->id()][$direction])) {
      // We already have this link.
      return $links[$entity->id()][$direction];
    }
    elseif (isset($links[$entity->id()]['binders'])) {
      // We've run the query once, let's not do it again.
      $binders = $links[$entity->id()]['binders'];
    }
    else {
      // We have no data let's query for binders.
      $query = \Drupal::entityQuery('node');
      $group = $query->orConditionGroup()
        ->condition('field_binder_pages.entity.field_page_page.uri', 'entity:node/' . $entity->id())
        ->condition('field_binder_pages.entity.field_page_group_page.uri', 'entity:node/' . $entity->id());

      $query->condition('type', 'binder')
        ->condition('status', 1)
        ->condition($group);
      $results = $query->accessCheck(FALSE)->execute();

      if (empty($results)) {
        return $links[$entity->id()] = [
          'binders' => $results,
          'next' => NULL,
          'previous' => NULL,
        ];
      }

      $binders = Node::loadMultiple($results);
      // We'll cache these and retrieve them later for the second link.
      $links[$entity->id()]['binders'] = $binders;
    }

    // Now let's build the link.
    $tags = [];
    $links[$entity->id()][$direction] = [];
    foreach ($binders as $binder) {
      $id = $binder->id();
      $uris = [];
      foreach ($binder->field_binder_pages->referencedEntities() as $paragraph) {
        $field = $paragraph->bundle() === 'page' ? 'field_page_page' : 'field_page_group_page';
        foreach ($paragraph->{$field} as $item) {
          if ($node = Helper::entityFromUrl($item->getUrl())) {
            if ($node->isPublished()) {
              $uris[$item->uri] = $item;
              $tags[] = "node:" . $node->id();
            }
          }
        }
      }
      $map = array_keys($uris);
      $currentKey = array_search('entity:node/' . $entity->id(), $map);
      if ($direction == 'previous' && $currentKey > 0) {
        $index = (int) $currentKey - 1;
      }
      elseif ($direction == 'next') {
        $index = (int) $currentKey + 1;
      }
      if (isset($index, $map[$index])) {
        $direction_link = $uris[$map[$index]];
        if (empty($direction_link->title)) {
          if ($direction_url = Helper::entityFromUrl($direction_link->getUrl())) {
            $title = $direction_url->label();
          }
        }
        else {
          $title = $direction_link->title;
        }
        $link = [
          'title' => $title,
          'uri' => $direction_link->uri,
          'options' => $direction_link->options,
          'cache_tags' => $tags,
        ];
        $links[$entity->id()][$direction] = $link;
      }
    }
    return $links[$entity->id()][$direction];
  }

}
