<?php

namespace Drupal\mass_tome\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\Routing\Routes;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\EventSubscriber\EntityPathSubscriber as EntityPathSubscriberBase;

/**
 * Overrides the class provided by Tome Static
 */
class EntityPathSubscriber extends EntityPathSubscriberBase {

  /**
   * Adds
   * - Skips media,terms,users.
   * - Skip legacy_redirects, fee,contact_information bundles, and unpublished
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   */
  public function collectPaths(CollectPathsEvent $event) {
    $langcodes = array_keys($this->languageManager->getLanguages());
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {

      if (in_array($entity_type->id(), ['media',  'taxonomy_term', 'user'])) {
        continue;
      }

      if (is_a($entity_type->getClass(), '\Drupal\Core\Entity\ContentEntityInterface', TRUE) && $entity_type->hasLinkTemplate('canonical')) {
        if ($entity_type->hasLinkTemplate('edit-form') && $entity_type->getLinkTemplate('edit-form') === $entity_type->getLinkTemplate('canonical')) {
          continue;
        }
        $storage = $this->entityTypeManager->getStorage($entity_type->id());
        $query = $storage->getQuery();
        $query->condition('type', ['legacy_redirects', 'fee', 'contact_information', 'decision_tree_branch', 'decision_tree_conclusion'], 'NOT IN')
          // ->condition('type', 'topic_page')
          ->condition('status', 1);
        if ($entity_type->isTranslatable() && $langcode_key = $entity_type->getKey('langcode')) {
          foreach ($langcodes as $langcode) {
            foreach ($query->condition($langcode_key, $langcode)->execute() as $entity_id) {
              $event->addPath(implode(':', [
                static::PLACEHOLDER_PREFIX,
                $entity_type->id(),
                $langcode,
                $entity_id,
              ]), [
                'language_processed' => 'language_processed',
                'langcode' => $langcode,
              ]);
            }
          }
        }
        else {
          foreach ($query->execute() as $entity_id) {
            $event->addPath(implode(':', [
              static::PLACEHOLDER_PREFIX,
              $entity_type->id(),
              $default_langcode,
              $entity_id,
            ]));
          }
        }
      }
    }
  }

}
