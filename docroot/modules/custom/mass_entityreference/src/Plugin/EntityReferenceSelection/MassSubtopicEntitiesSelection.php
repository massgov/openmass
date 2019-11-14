<?php

namespace Drupal\mass_entityreference\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "mass_subtopics",
 *   label = @Translation("Subtopic: Related Content"),
 *   group = "mass_subtopics",
 *   weight = 0
 * )
 */
class MassSubtopicEntitiesSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    if ($nid = $_COOKIE['Drupal_visitor_subtopic_nid']) {
      $query = parent::buildEntityQuery($match, $match_operator);
      $query->condition('field_action_parent.target_id', $nid);
      return $query;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

}
