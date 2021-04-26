<?php

namespace Drupal\mass_feedback_loop\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityManagerInterface;
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
    'legacy_redirects',
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, MassFeedbackLoopContentFetcher $content_fetcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user);

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
