<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Custom view field to show number of Manual lists links.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cl_manual_lists_links")
 */
class ManualListLinksField extends FieldPluginBase {

  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->currentDisplay = $view->current_display;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // First check whether the field should be hidden if
    // the value(hide_alter_empty = TRUE) /the rewrite is
    // empty (hide_alter_empty = FALSE).
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;
    $n_link = 0;
    if ($node->hasField('field_curatedlist_list_section') && !$node->get('field_curatedlist_list_section')->isEmpty()) {
      $lists = $node->get('field_curatedlist_list_section')->getValue();
      foreach ($lists as $list) {
        $list_p = Paragraph::load($list['target_id']);
        if ($list_p instanceof Paragraph && $list_p->bundle() === 'list_static') {
          if ($list_p->hasField('field_liststatic_items') && !$list_p->get('field_liststatic_items')->isEmpty()) {
            $items = $list_p->get('field_liststatic_items')->getValue();
            foreach ($items as $item) {
              $item_p = Paragraph::load($item['target_id']);
              if ($item_p instanceof Paragraph) {
                if ($item_p->bundle() === 'list_item_link') {
                  $n_link += 1;
                }
              }
            }
          }
        }
      }
    }

    return $n_link;
  }

}
