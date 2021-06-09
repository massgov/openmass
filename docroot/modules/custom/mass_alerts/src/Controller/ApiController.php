<?php

namespace Drupal\mass_alerts\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;

/**
 * Defines a route controller for entity query.
 */
class ApiController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a ApiController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(DateFormatterInterface $date_formatter, Renderer $renderer) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
    );
  }

  /**
   *
   */
  public function handleSiteRequest(Request $request) {

    $results = [];
    $nodeStorage = $this->entityTypeManager()->getStorage('node');

    // Load sitewide.
    $query = $nodeStorage->getQuery();
    $query->condition('field_alert_display.value', 'site_wide');
    $query->condition('type', 'alert');
    $query->condition('status', 1);

    $sitewide = $query->execute();
    // Last item.
    $sitewide = reset($sitewide);

    if (!empty($sitewide)) {
      $node = $nodeStorage->load($sitewide);
      $changed_date = $node->getChangedTime();
      $id = $node->uuid() . '__' . $changed_date;
      $prefix = NULL;
      $severity = $node->get('field_alert_severity')->getString();

      if ($severity == 'informational_notice') {
        $prefix = $this->t('Informational Alert');
      }

      $emergencyAlerts = [
        'id' => $id,
        'buttonAlert' => [
          'hideText' => $this->t('Hide'),
          'showText' => $this->t('Show'),
          'text' => $this->t('Alerts'),
        ],
        'emergencyHeader' => [
          'title' => $node->label(),
        ],
      ];

      if ($prefix) {
        $emergencyAlerts['emergencyHeader']['prefix'] = $prefix;
      }

      $results = $emergencyAlerts;
      $results['emergencyAlerts'] = $emergencyAlerts;
      $results['emergencyAlerts']['alerts'] = [];

      $alerts = [];
      $alert_items = $node->get('field_alert')->referencedEntities();
      foreach ($alert_items as $item) {
        $item_id = $item->uuid() . '__' . $changed_date;
        $timestamp = $item->get('field_emergency_alert_timestamp')->getString();
        $unix_timestamp = strtotime($timestamp);
        $timestamp = $this->dateFormatter->format($unix_timestamp, 'custom', 'M. jS, Y, h:i a');

        $uri = $item->get('field_emergency_alert_link')->getString();
        $url = Url::fromUri($uri)->toString();
        $link_type = $item->get('field_emergency_alert_link_type')->getString();

        $link = [
          'chevron' => TRUE,
          'href' => $url,
          'text' => 'Read more',
          'type' => $link_type,
        ];

        $alerts[$unix_timestamp] = [
          'id' => $item_id,
          'link' => $link,
          'message' => $item->get('field_emergency_alert_message')->getString(),
          'timeStamp' => $timestamp,
        ];
      }

      ksort($alerts);
      $results['emergencyAlerts']['alerts'] = array_values($alerts);
    }

    $build = [
      '#theme' => 'mass_alerts_sitewide',
      '#emergencyAlerts' => $results['emergencyAlerts'],
      '#cache' => ['max-age' => 0],
    ];

    $output = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($output);
    return $response;
  }

  /**
   *
   */
  public function handlePageRequest($nid, Request $request) {

    $results = [
      'headerAlerts' => [],
    ];

    $nodeStorage = $this->entityTypeManager()->getStorage('node');

    $currentPage = $nodeStorage->load($nid);

    if ($currentPage) {
      $organizations = $currentPage->get('field_organizations')->getValue();
      $org_ids = [];

      foreach ($organizations as $org) {
        $org_ids[] = $org['target_id'];
      }

      $query = $nodeStorage->getQuery();
      $query->condition('type', 'alert');
      $query->condition('status', 1);

      $orContition = $query->orConditionGroup();
      $orContition->condition('field_target_page.target_id', $nid);
      $orContition->condition('field_target_organization.target_id', $org_ids, 'IN');

      $query->condition($orContition);

      $nids = $query->execute();

      $nodes = $nodeStorage->loadMultiple(array_values($nids));

      $alerts = [];

      foreach ($nodes as $node) {

        $changed_date = $node->getChangedTime();
        $id = $node->uuid() . '__' . $changed_date;

        $items = $node->get('field_alert')->referencedEntities();
        $item = reset($items);

        $link_type = $item->get('field_emergency_alert_link_type')->getString();

        if ($link_type == '1') {
          $uri = $item->get('field_emergency_alert_link')->getString();
          if ($uri) {
            $url = Url::fromUri($uri)->toString();
          }
        }
        else {
          $url = $node->toUrl()->toString();
          $url .= '#' . $item->id();
        }

        $prefix = NULL;
        $severity = $node->get('field_alert_severity')->getString();

        if ($severity == 'informational_notice') {
          $prefix = 'Notice';
        }

        $alert = [
          'id' => $id,
          'text' => $item->get('field_emergency_alert_message')->getString(),
          'href' => ($link_type == '2') ? '' : $url,
          'info' => '',
        ];

        if ($alert) {
          $alert['prefix'] = $prefix;
        }

        $alerts[$changed_date] = $alert;
      }

      krsort($alerts);
      $results['headerAlerts'] = array_values($alerts);
    }

    $build = [
      '#theme' => 'mass_alerts_page',
      '#headerAlerts' => $results['headerAlerts'],
      '#cache' => ['max-age' => 0],
    ];

    $output = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($output);
    return $response;
  }

}
