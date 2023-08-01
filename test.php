<?php

$ids = \Drupal::entityQuery('paragraph')->condition('type','what_would_you_like_to_do')->execute();

$parapgrahs = \Drupal\paragraphs\Entity\Paragraph::loadMultiple($ids);

foreach ($parapgrahs as $parapgrah) {

  if ($parapgrah->id() == '2643406') {
    $parent_field_name = $parapgrah->parent_field_name->value;
    if (!$parapgrah->get('field_wwyltd_top_s_links')->isEmpty()) {
      $top_links = $parapgrah->get('field_wwyltd_top_s_links')->getValue();
      //    dump($top_links);
    }
    if ($parent = $parapgrah->getParentEntity()) {
      if ($parent instanceof \Drupal\paragraphs\Entity\Paragraph && $parent->bundle() == 'org_section_long_form') {
        if ($parent->getParentEntity() instanceof \Drupal\node\Entity\Node) {
          $items = $parent->get($parent_field_name)->getValue();
          foreach ($items as $index => $item) {
            if ($item['target_id'] == $parapgrah->id() && $item['target_revision_id'] == $parapgrah->getRevisionId()) {
              $paragraph_index = $index;
              break;
            }
          }

          dump($parent->get($parent_field_name)->offsetGet($paragraph_index)->toArray());
          // offsetset to the same index with the new data and paragraph
        }
      }
    }
  }
}
