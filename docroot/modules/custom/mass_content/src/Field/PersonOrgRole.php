<?php

namespace Drupal\mass_content\Field;

/**
 * Organization and roles a person is a member of.
 */
class PersonOrgRole extends QueryGeneratedEntityReferenceListUpdated {

  /**
   * {@inheritdoc}
   */
  protected function query() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $query = \Drupal::entityQuery('node');
    $group = $query->orConditionGroup()
      ->condition('field_organization_sections.entity.field_section_long_form_content.entity.field_board_members.entity.field_board_members.entity.field_person', $entity->id())
      ->condition('field_organization_sections.entity.field_section_long_form_content.entity.field_featured_board_members.entity.field_person', $entity->id());
    $query->condition('type', 'org_page');

    $query->condition($group)
      ->condition('status', 1);

    return $query;
  }

}
