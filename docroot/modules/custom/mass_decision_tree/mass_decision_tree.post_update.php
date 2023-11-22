<?php

/**
 * @file
 * Implementations of hook_post_update_NAME() for Mass Content.
 */

use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Migrate data from field_answers to field_multiple_answers.
 */
function mass_decision_tree_post_update_answers(&$sandbox) {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
  $query->condition('type', 'decision_tree_branch');
  $group = $query->orConditionGroup();
  $group->exists('field_answers');
  $query->condition($group);

  if (empty($sandbox)) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $query;
    $sandbox['max'] = $count->count()->execute();
  }

  $batch_size = 5;

  $nids = $query->accessCheck(FALSE)->condition('nid', $sandbox['current'], '>')
    ->sort('nid')
    ->range(0, $batch_size)
    ->execute();

  $nodes = Node::loadMultiple($nids);

  foreach ($nodes as $node) {
    $sandbox['current'] = $node->id();
    if (Helper::isFieldPopulated($node, 'field_answers')) {

      $answers = Helper::getReferencedEntitiesFromField($node, 'field_answers');
      $answer = $answers[0];
      $choices = ['true', 'false'];

      foreach ($choices as $choice) {
        $id = '';
        $referenced_path = Helper::getReferencedEntitiesFromField($answer, "field_{$choice}_answer_path");
        if (!empty($referenced_path)) {
          $id = $referenced_path[0]->id();
        }
        $text = Helper::fieldValue($answer, "field_{$choice}_answer_text");
        $explainer = Helper::fieldValue($answer, "field_{$choice}_answer_explainer");
        $paragraph = Paragraph::create([
          'type' => 'multiple_answers',
        ]);
        $paragraph->field_answer_text->value = $text;
        $paragraph->field_answer_explainer->value = $explainer;
        $paragraph->field_answer_path->target_id = $id;
        $paragraph->save();
        $answer->delete();
        $node->field_multiple_answers[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
      }

      $node->field_answers = NULL;
      $node->setNewRevision();
      $node->setRevisionUserId(1);
      $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node->setRevisionLogMessage('Programmatic update to move content from field_answers to field_multiple_answers.');
      $node->save();

      // Rerunning this to make sure it gets indexed in descendant_storage.
      /** @var \Drupal\mass_content_api\DescendantManagerInterface $descendant_manager */
      $descendant_manager = \Drupal::getContainer()->get('descendant_manager');
      $descendant_manager->index($node);
    }
    // Update the counter.
    $sandbox['progress']++;
  }
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] >= 1) {
    return t('All Decision tree branch nodes have had their answers updated.');
  }
}
