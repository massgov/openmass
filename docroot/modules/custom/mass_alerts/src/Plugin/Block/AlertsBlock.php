<?php

namespace Drupal\mass_alerts\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a mayflower Block for @organisms/by-template/ajax-pattern.
 */
#[Block(
  id: 'mass_alerts_block',
  admin_label: new TranslatableMarkup('Alerts block'),
  category: new TranslatableMarkup('Mass.gov'),
)]
class AlertsBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  protected CurrentRouteMatch $routeMatch;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['alerts_block_type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => [
        'sitewide' => $this->t('Sitewide'),
        'page' => $this->t('Page'),
      ],
      '#title' => $this->t('Alerts Type'),
      '#description' => $this->t('The type of alerts to display on this block.'),
      '#default_value' => isset($config['alerts_block_type']) ? $config['alerts_block_type'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['alerts_block_type'] = $values['alerts_block_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = $this->getConfiguration();
    $path = FALSE;

    if ($config['alerts_block_type'] == 'sitewide') {
      $path = Url::fromRoute('mass_alerts.site_alerts')->toString();
    }
    else {
      $node = $this->routeMatch->getParameter('node');
      if ($node instanceof NodeInterface) {
        $nid = $node->id();
        $path = Url::fromRoute('mass_alerts.page_alerts', ['nid' => $nid])->toString();
      }
    }

    $param_node = \Drupal::routeMatch()->getParameter('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = \is_string($param_node) && intval($param_node) ? Node::load($param_node) : $param_node;
    $type = $node && is_a($node, 'Drupal\node\Entity\Node') ? $node->getType() : '';

    // Hide the block if current node is or belongs to excluded organizations.
    $config = \Drupal::config('mass_alerts.settings');
    $excluded_org_ids = array_map('intval', (array) ($config->get('excluded_org_ids') ?? []));
    $org_ids = [];
    if ($node instanceof NodeInterface) {
      if ($node->bundle() === 'org_page') {
        $org_ids[] = (int) $node->id();
      }
      if ($node->hasField('field_organizations')) {
        foreach ($node->get('field_organizations')->getValue() as $org) {
          $org_ids[] = (int) $org['target_id'];
        }
      }
    }

    if (!empty(array_intersect($org_ids, $excluded_org_ids))) {
      return [
        '#markup' => '',
        '#path' => $path,
        '#cache' => [
          'contexts' => ['url'],
          'tags' => ['config:mass_alerts.settings'],
        ],
      ];
    }

    return [
      '#theme' => 'mass_alerts_block',
      '#path' => $path,
      '#wait' => strpos($path, 'sitewide') === FALSE,
      '#type' => $type,
      '#cache' => [
        'contexts' => ['url'],
        'tags' => ['config:mass_alerts.settings'],
      ],
    ];
  }

}
