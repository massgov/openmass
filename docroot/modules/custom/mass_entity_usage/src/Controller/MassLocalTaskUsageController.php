<?php

namespace Drupal\mass_entity_usage\Controller;

use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;

/**
 * Controller for our pages.
 */
class MassLocalTaskUsageController extends ListUsageController {

  /**
   * Lists the usage of a given entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return array
   *   The page build to be rendered.
   */
  public function listUsageLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return $this->listUsagePageSubQuery($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function listUsagePageSubQuery($entity_type, $entity_id) {
    $build = [];
    // Link needed for the caption.
    $help_url = Url::fromUri('https://massgovdigital.gitbook.io/knowledge-base/content-improvement-tools/pages-linking-here');
    $help_text = Link::fromTextAndUrl('Learn how to use Linking Pages.', $help_url)->toString();
    // Table headers.
    $header = [
      $this->t('Entity'),
      $this->t('Content Type'),
      $this->t('Field name'),
      $this->t('Status'),
    ];
    // Result table.
    $build['results'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#caption' => $this->t('The list below shows pages that include a link to this page in structured and rich text fields. @help_text', ['@help_text' => $help_text]),
      '#empty' => $this->t('No pages link here.'),
    ];

    $this->loadEntity($entity_type, $entity_id);

    $total = count($this->prepareRows($this->entityUsage->listSources($this->entity)));
    if (!$total) {
      return $build;
    }

    $pager = $this->pagerManager->createPager($total, $this->itemsPerPage);
    $page = $pager->getCurrentPage();
    $page_rows = $this->getSubQueryRows($page, $this->itemsPerPage);

    $build['results']['#prefix'] = $this->t($total . ' total records.');
    $build['results']['#rows'] = $page_rows;

    $build['pager'] = [
      '#type' => 'pager',
      '#route_name' => '<current>',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRows($usages) {

    $rows = [];
    foreach ($usages as $source_type => $ids) {
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
        $default_key = count($records) - 1;

        $link = $this->getSourceEntityLink($source_entity);
        // If the label is empty it means this usage shouldn't be shown
        // on the UI, just skip this row. Also, only show Default sources.
        if (empty($link)) {
          continue;
        }

        // If the source is a paragraph, get the parent node.
        if ($source_entity->getEntityTypeId() == 'paragraph') {
          /** @var \Drupal\paragraphs\ParagraphInterface $source_entity */
          $source_entity = Helper::getParentNode($source_entity);
        }

        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }

        if (method_exists($link, 'getText')) {
          $text = explode('>', $link->getText())[0];
          $link->setText($text);
        }

        // Get the moderation state label of the parent node.
        $state_label = '';
        if ($source_entity instanceof Node) {
          $content_moderation_state = ContentModerationState::loadFromModeratedEntity($source_entity);

          if (!$content_moderation_state) {
            continue;
          }

          $state_name = $content_moderation_state->get('moderation_state')->value;
          $workflow = $content_moderation_state->get('workflow')->entity;
          $state_label = $workflow->get('type_settings')['states'][$state_name]['label'];
        }
        // Get a field label.
        $field_label = isset($field_definitions[$records[$default_key]['field_name']]) ?
          $field_definitions[$records[$default_key]['field_name']]->getLabel() : $this->t('Unknown');

        // Set the row values.
        $rows[] = [
          $link,
          $source_entity->type->entity->label(),
          $field_label,
          $state_label,
        ];
      }
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleLocalTask(RouteMatchInterface $route_match) {
    return $this->t('Pages linking here');
  }

  /**
   * Checks access based on whether the user can view the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccessLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return parent::checkAccess($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * Retrieves entity from route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object as determined from the passed-in route match.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_entity_usage_entity_type_id');
    return $route_match->getParameter($parameter_name);
  }

}
