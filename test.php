<?php

use \Drupal\paragraphs\Entity\Paragraph;

$_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

$ids = \Drupal::entityQuery('paragraph')->accessCheck(FALSE)->condition('type','what_would_you_like_to_do')->execute();

$parapgrahs = \Drupal\paragraphs\Entity\Paragraph::loadMultiple($ids);

foreach ($parapgrahs as $parapgrah) {

  if ($parapgrah->id()) {
    $parent_field_name = $parapgrah->parent_field_name->value;

    if ($parent = $parapgrah->getParentEntity()) {
      if ($parent instanceof \Drupal\paragraphs\Entity\Paragraph && $parent->bundle() == 'org_section_long_form') {
        if ($parent->getParentEntity() instanceof \Drupal\node\Entity\Node) {
          $node = $parent->getParentEntity();
          $node_field_name = $parent->parent_field_name->value;

          $items = $node->get($node_field_name)->getValue();
          foreach ($items as $index => $item) {
            if ($item['target_id'] == $parent->id() && $item['target_revision_id'] == $parent->getRevisionId()) {
              $section_index = $index;
              break;
            }
          }

          $flexible_link_groups = [];
          if (!$parapgrah->get('field_wwyltd_top_s_links')->isEmpty()) {
            $top_links = $parapgrah->get('field_wwyltd_top_s_links')->getValue();

            $link_group_links = [];
            foreach ($top_links as $link) {
              // Create a new link_group_link paragraph.
              $link_group_link = Paragraph::create([
                'type' => 'link_group_link',
              ]);

              $link_group_link->set('field_link_group_link', $link);
              $link_group_link->save();
              $link_group_links[] = $link_group_link;
            }


            // Create a new flexible_link_group paragraph.
            $flexible_link_group = Paragraph::create([
              'type' => 'flexible_link_group',
            ]);

            $flexible_link_group->set('field_featured', 1);
            $flexible_link_group->set('field_display_type', 'buttons');
            // 2 = Buttons.
            $flexible_link_group->set('field_link_group', $link_group_links);
            $flexible_link_group->save();
            $flexible_link_groups[] = $flexible_link_group;
          }

          if (!$parapgrah->get('field_wwyltd_more_services')->isEmpty()) {
            $more_paragraphs = $parapgrah->get('field_wwyltd_more_services')->referencedEntities();

            foreach ($more_paragraphs as $more_paragraph) {
              if (!$more_paragraph->get('field_links_documents')->isEmpty()) {
                $link_group_links = $more_paragraph->get('field_links_documents')
                  ->referencedEntities();

                // Create a new flexible_link_group paragraph.
                $flexible_link_group = Paragraph::create([
                  'type' => 'flexible_link_group',
                ]);

                $flexible_link_group->set('field_featured', 0);
                $flexible_link_group->set('field_display_type', 'links');
                $flexible_link_group->set('field_group_expanded', 1);
                if (!$more_paragraph->get('field_section_title')->isEmpty()) {
                  $title = $more_paragraph->get('field_section_title')->value;
                  $flexible_link_group->set('field_flexible_link_group_title', $title);
                }
                $flexible_link_group->set('field_link_group', $link_group_links);
                $flexible_link_group->save();
                $flexible_link_groups[] = $flexible_link_group;
              }
            }
          }

          if ($flexible_link_groups) {
            $new_org_section_long_form_paragraph = Paragraph::create([
              'type' => 'org_section_long_form',
            ]);
            $new_org_section_long_form_paragraph->set('field_section_long_form_content', $flexible_link_groups);
            if (!$parapgrah->get('field_wwyltd_heading')->isEmpty()) {
              $heading = $parapgrah->get('field_wwyltd_heading')->value;
              $new_org_section_long_form_paragraph->set('field_section_long_form_heading', $heading);
            }
            $new_org_section_long_form_paragraph->set('field_hide_heading', 0);

            // Save the new paragraph.
            $new_org_section_long_form_paragraph->save();
            // Create a value array for the new section paragraph.
            $new_org_section_long_form_paragraph_value = [
              'target_id' => $new_org_section_long_form_paragraph->id(),
              'target_revision_id' => $new_org_section_long_form_paragraph->getRevisionId(),
            ];
            if (isset($section_index)) {
              $items[$section_index] = $new_org_section_long_form_paragraph_value;
            }
            else {
              $items[] = $new_org_section_long_form_paragraph_value;
            }

            $node->set('field_organization_sections', $items);
            // Save the node.
            // Save without updating the last modified date. This requires a core patch
            // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
            $node->setSyncing(TRUE);
            $node->save();
            $parapgrah->delete();
            dump($node->id());
            exit;
          }
//          $items = $node->get($parent_field_name)->getValue();
//          foreach ($items as $index => $item) {
//            if ($item['target_id'] == $parapgrah->id() && $item['target_revision_id'] == $parapgrah->getRevisionId()) {
//              $paragraph_index = $index;
//              break;
//            }
//          }
//
//          dump($parent->get($parent_field_name)->offsetGet($paragraph_index)->toArray());
          // offsetset to the same index with the new data and paragraph
        }
      }
    }
  }
}
