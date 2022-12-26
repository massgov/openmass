<?php

/**
 * @file
 * Implementations of hook_post_update_NAME() for Mass Content.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate data from field_person_phone to field_ref_phone.
 */
function mass_content_post_update_contact(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'person');
  $group = $query->orConditionGroup();

  $group->exists('field_person_phone');
  $group->exists('field_person_email');
  $group->exists('field_contact');
  $query->condition($group);

  if (empty($sandbox)) {
    // Get a list of all nodes with a phone number, an email, or a contact info reference.
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $nodes = Node::loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();
    // If a contact reference is set, set the contact type and move to next node.
    if ($node->field_contact->count() > 0) {
      $node->field_contact_source = 'contact';
    }
    else {
      $node->field_contact_source = 'node';
      // If the phone number is set, create a phone number paragraph.
      if ($node->field_person_phone->count() > 0) {
        // Create each paragraph object.
        $paragraph = Paragraph::create([
          'type' => 'phone_number',
        ]);
        $paragraph->field_phone->value = $node->field_person_phone->value;
        $paragraph->save();

        // Update the node.
        $node->field_ref_phone->target_id = $paragraph->id();
        $node->field_ref_phone->target_revision_id = $paragraph->getRevisionId();
        $node->field_person_phone = NULL;
      }
      // If the email is set, create an email paragraph from it.
      if ($node->field_person_email->count() > 0) {
        // Create each paragraph object.
        $paragraph = Paragraph::create([
          'type' => 'online_email',
        ]);
        $paragraph->field_email->value = $node->field_person_email->value;
        $paragraph->save();

        // Update the node.
        $node->field_ref_links->target_id = $paragraph->id();
        $node->field_ref_links->target_revision_id = $paragraph->getRevisionId();
        $node->field_person_email = NULL;
      }
    }

    // Save the node.
    $node->setNewRevision();
    $node->setRevisionUserId(1);
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionLogMessage('Programmatic update to move phone and email data into a new field structure.');
    $node->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Person nodes have had their contact information updated.');
  }
}

/**
 * Set a default value for field_list_type.
 */
function mass_content_post_update_curated_list_type(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'curated_list');
  $query->notExists('field_list_type');
  if (empty($sandbox)) {
    // Initialize other variables.
    $sandbox['current'] = 0;
    $sandbox['progress'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 50;

  $nids = $query->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $entities = Node::loadMultiple($nids);

  // Give each node's "List Type" field a value.
  foreach ($entities as $entity) {
    $sandbox['current'] = $entity->id();
    $entity->set('field_list_type', 'links');
    $entity->setNewRevision();
    $entity->setRevisionUserId(1);
    $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $entity->setRevisionLogMessage('Programmatic update to set a default curated list type for each instance.');
    $entity->save();

    // Update the counter.
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Curated list nodes have had a field_list_type value assigned.');
  }
}

/**
 * Add "Location Icons (Park)" terms and update node field data.
 */
function mass_content_post_update_location_icons(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
  // Load the vocabulary to get the the vocabulary ID.
  $vocabulary = Vocabulary::load('location_icon_park');
  if (!empty($vocabulary)) {
    $new_terms = [
      [
        'name' => 'Historic site',
        'sprite' => 'castle',
      ],
      [
        'name' => 'Dogs Allowed',
        'sprite' => 'dog',
      ],
    ];

    // Create the terms in the "Location Icons (Park)" vocabulary.
    foreach ($new_terms as $new_term) {
      $new_term_properties = [
        'name' => $new_term['name'],
        'vid' => 'location_icon_park',
      ];
      $existing_term = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term')
        ->loadByProperties($new_term_properties);
      $existing_term = reset($existing_term);
      if (empty($existing_term)) {
        $term = Term::create([
          'parent' => [],
          'name' => $new_term['name'],
          'field_sprite_name' => $new_term['sprite'],
          'vid' => 'location_icon_park',
        ])->save();
      }
    }

    $new_terms_names = array_column($new_terms, 'name');
    // Get all actions that have contact content.
    $node_storage = \Drupal::service('entity_type.manager')->getStorage('node');
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'location')
      ->exists('field_location_icons');

    if (empty($sandbox)) {
      // Initialize other variables.
      $sandbox['current'] = 0;
      $sandbox['progress'] = 0;
      $count = clone $query;
      $sandbox['max'] = $count->count()->execute();
    }

    $batch_size = 50;

    $nids = $query->condition('nid', $sandbox['current'], '>')
      ->sort('nid')
      ->range(0, $batch_size)
      ->execute();

    $nodes = $node_storage->loadMultiple($nids);
    foreach ($nodes as $node) {
      $sandbox['current'] = $node->id();
      if ($node->field_location_icons->isEmpty()) {
        continue;
      }

      // Get all the referenced terms from the old field. If any of those terms
      // are moving to the new "Location Icons (Park)" vocabulary, it will be
      // removed from the general "Location Icons" field and the value will
      // be reassigned to the park location icons reference field.
      $referenced_terms = $node->field_location_icons->referencedEntities();
      foreach ($referenced_terms as $term) {
        $term_name = $term->getName();
        if (!in_array($term_name, $new_terms_names)) {
          continue;
        }

        $new_term_properties = [
          'name' => $term_name,
          'vid' => 'location_icon_park',
        ];
        $new_term = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->loadByProperties($new_term_properties);
        $new_term = reset($new_term);
        if ($new_term) {
          // Append the term to the park location icons field.
          $node->field_location_icons_park->appendItem(['target_id' => $new_term->id()]);
          // Get the key of the current term, so it can be located for removal.
          $key = array_search($term->id(), array_column($node->field_location_icons->getValue(), 'target_id'));
          // Remove the term from the general location icons field.
          $node->field_location_icons->removeItem($key);
        }
      }
      // Save the node.
      $node->save();
      $sandbox['progress']++;
    }

    $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
    if ($sandbox['#finished'] >= 1) {

      // Delete the terms from the "Location Icons" vocabulary.
      foreach ($new_terms as $new_term) {
        $old_term_properties = [
          'name' => $new_term['name'],
          'vid' => 'location_icon',
        ];
        $old_terms = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->loadByProperties($old_term_properties);
        $old_term = reset($old_terms);
        if ($old_term) {
          $old_term->delete();
        }
      }
      return t('All location icon fields have been updated.');
    }
  }
}
