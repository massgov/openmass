<?php

namespace Drupal\mass_feedback_loop\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "mass_feedback_loop_selection",
 *   label = @Translation("Mass Feedback Loop Entity Reference Selection"),
 *   group = "mass_feedback_loop_selection",
 *   weight = 0
 * )
 */
class MassFeedbackLoopSelection extends DefaultSelection {

  /**
   * Content types not accessible by constituents for providing feedback.
   */
  const FEEDBACK_INELIGIBLE_BUNDLES = [
    'contact_information',
    'person',
    'fee',
  ];

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, EntityRepositoryInterface $entity_repository, MassFeedbackLoopContentFetcher $content_fetcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);

    $this->contentFetcher = $content_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('mass_feedback_loop.content_fetcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    // Limits query results to nodes being watched by current user.
    // Results limited to nodes with 'watch_content' flag applied by user.
    // Excludes content types not accessible by constituents.
    $query->condition('type', self::FEEDBACK_INELIGIBLE_BUNDLES, 'NOT IN');
    // Sorts by title for better readability of results.
    $query->sort('title');
    return $query;
  }

}
