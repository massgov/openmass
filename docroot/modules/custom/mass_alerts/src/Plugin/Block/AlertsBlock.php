<?php

namespace Drupal\mass_alerts\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

/**
 * Provides a mayflower Block for @organisms/by-template/ajax-pattern.
 *
 * @Block(
 *   id = "mass_alerts_block",
 *   admin_label = @Translation("Alerts block"),
 *   category = @Translation("Mass.gov"),
 * )
 */
class AlertsBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * CompanyBlock constructor.
   */
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

    if ($config['alerts_block_type'] == 'sitewide') {
      $path = Url::fromRoute('mass_alerts.site_alerts')->toString();
    } else {
      $node = $this->routeMatch->getParameter('node');
      if ($node instanceof NodeInterface) {
        $nid = $node->id();
        $path = Url::fromRoute('mass_alerts.page_alerts', ['nid' => $nid])->toString();
      }
    }

    return [
      '#theme' => 'mass_alerts_block',
      '#path' => $path,
      '#cache' => [
        'contexts' => ['url']
      ]
    ];
  }

}



