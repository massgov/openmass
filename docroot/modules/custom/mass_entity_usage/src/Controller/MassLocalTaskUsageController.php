<?php

namespace Drupal\mass_entity_usage\Controller;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\Controller\LocalTaskUsageController;
use Drupal\entity_usage\EntityUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;

/**
 * Controller for our pages.
 */
class MassLocalTaskUsageController extends LocalTaskUsageController {

  /**
   * {@inheritdoc}
   */
  public function listUsageLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return $this->listUsagePage($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function listUsagePage($entity_type, $entity_id) {
    $this->loadEntity($entity_type, $entity_id);
    $total = $this->getRowsCount();
    if (!$total) {
      return [
        '#markup' => $this->t('No pages link here.'),
      ];
    }

    $header = [
      $this->t('Entity'),
      $this->t('ID'),
      $this->t('Content Type'),
      $this->t('Field name'),
      $this->t('Status'),
    ];

    $pager = $this->pagerManager->createPager($total, $this->itemsPerPage);
    $page = $pager->getCurrentPage();
    $page_rows = $this->getPageRows($page, $this->itemsPerPage);

    $build[] = [
      '#theme' => 'table',
      '#rows' => $page_rows,
      '#header' => $header,
      '#prefix' => $this->t($total . ' total records.'),
    ];

    $build[] = [
      '#type' => 'pager',
      '#route_name' => '<current>',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPageRows($page, $num_per_page) {
    $offset = $page * $num_per_page;
    $entity_types = $this->entityTypeManager->getDefinitions();
    $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    $all_usages = $this->entityUsage->listSources($this->entity, TRUE, $offset);
    foreach ($all_usages as $source_type => $ids) {
      $type_storage = $this->entityTypeManager->getStorage($source_type);
      foreach ($ids as $source_id => $records) {
        // We will show a single row per source entity. If the target is not
        // referenced on its default revision on the default language, we will
        // just show indicate that in a specific column.
        $source_entity = $type_storage->load($source_id);
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($source_type, $source_entity->bundle());
        if ($source_entity instanceof RevisionableInterface) {
          $default_revision_id = $source_entity->getRevisionId();
          $default_langcode = $source_entity->language()->getId();
          $used_in_default = FALSE;
          $default_key = 0;
          foreach ($records as $key => $record) {
            if ($record['source_vid'] == $default_revision_id && $record['source_langcode'] == $default_langcode) {
              $default_key = $key;
              $used_in_default = TRUE;
              break;
            }
          }
        }
        $link = $this->getSourceEntityLink($source_entity);
        // If the label is empty it means this usage shouldn't be shown
        // on the UI, just skip this row. Also, only show Default sources.
        if (empty($link) || !$used_in_default) {
          continue;
        }
        $published = $this->getSourceEntityStatus($source_entity);
        $field_label = isset($field_definitions[$records[$default_key]['field_name']]) ? $field_definitions[$records[$default_key]['field_name']]->getLabel() : $this->t('Unknown');
        $rows[] = [
          $link,
          $source_entity->id(),
          $source_entity->type->entity->label(),
          $field_label,
          $published,
        ];
        // Capture the title text in an array for sorting.
        switch (gettype($link)) {
          case 'string':
            $row_link_text[] = trim($link);
            break;

          case 'object':
            if ($link instanceof Link) {
              $row_link_text[] = trim($link->getText());
            }
            elseif ($link instanceof TranslatableMarkup) {
              $row_link_text[] = trim($link->__toString());
            }
            break;
        }
      }
    }
    // Sort the array by title text.
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleLocalTask(RouteMatchInterface $route_match) {
    return $this->t('Pages linking here');
  }

}
