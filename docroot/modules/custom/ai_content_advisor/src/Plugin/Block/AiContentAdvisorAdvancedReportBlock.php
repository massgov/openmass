<?php

namespace Drupal\ai_content_advisor\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\ai_content_advisor\AiContentAdvisorAnalyzer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the latest AI Content Advisor report for a node from context.
 *
 * @Block(
 *   id = "ai_content_advisor_advanced_report_block",
 *   admin_label = @Translation("AI Content Advisor Advanced Report"),
 *   category = @Translation("AI Content Advisor"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class AiContentAdvisorAdvancedReportBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The AI Content Advisor analyzer service.
   *
   * @var \Drupal\ai_content_advisor\AiContentAdvisorAnalyzer
   */
  protected $aiContentAdvisorAnalyzer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new AiContentAdvisorLatestReportBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_content_advisor\AiContentAdvisorAnalyzer $ai_content_advisor_analyzer
   *   The AI Content Advisor analyzer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AiContentAdvisorAnalyzer $ai_content_advisor_analyzer,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aiContentAdvisorAnalyzer = $ai_content_advisor_analyzer;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_content_advisor.service'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $node = $this->getContextValue('node');

    // If we don't have a node, return empty build.
    if (!$node instanceof NodeInterface) {
      return $build;
    }

    $build = $this->formBuilder->getForm('\Drupal\ai_content_advisor\Form\AnalyzeNodeForm');
    $build['#access'] = $this->currentUser->hasPermission('view ai content advisor reports');
    $build['#cache']['tags'] =  ['node:' . $node->id()];
    return $build;
  }

}
