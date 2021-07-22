<?php

namespace Drupal\mass_alerts\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\mayflower\Helper;

/**
 * Defines a route controller for entity query.
 */
class AlertsController extends ControllerBase implements ContainerInjectionInterface {

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
   * Returns the sitewide alerts rendered.
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

      $emergencyAlerts = [
        'id' => $id,
        'emergencyHeader' => [
          'hideText' => $this->t('Hide'),
          'showText' => $this->t('Show'),
        ],
      ];

      $results = [];

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

        // For Test this could be empty.
        if ($uri) {
          $url = Url::fromUri($uri)->toString(TRUE)->getGeneratedUrl();
        }
        else {
          $url = '#';
        }

        $message = $item->get('field_emergency_alert_message')->getString();

        $link = [
          'chevron' => TRUE,
          'href' => $url,
          'text' => $message,
        ];

        $alerts[$unix_timestamp] = [
          'id' => $item_id,
          'timeStamp' => $timestamp,
          'link' => $link,
        ];
      }

      ksort($alerts);
      $results['emergencyAlerts']['alerts'] = array_values($alerts);
    }

    $count = count($results['emergencyAlerts']['alerts']);
    $results['emergencyAlerts']['emergencyHeader']['title'] = $count . ' ' . $node->label();

    $build = [
      '#theme' => 'mass_alerts_sitewide',
      '#emergencyAlerts' => $results['emergencyAlerts'],
      '#cache' => [
        'tags' => [
          MASS_ALERTS_TAG_GLOBAL,
          MASS_ALERTS_TAG_SITEWIDE . ':list'
        ]
      ],
    ];

    $output = $this->renderer->renderRoot($build);
    $this->attachSvg($output);

    $response = new CacheableResponse($output);

    if ($node) {
      $response->addCacheableDependency($node);
    }

    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    $response->setMaxAge(60);
    return $response;
  }

  /**
   * Returns the specific page alerts rendered.
   */
  public function handlePageRequest($nid, Request $request) {
    $org_ids = [];

    $results = [
      'headerAlerts' => [],
      'headerTitle' => [
        'icon' => 'warning',
        'text' => 'Notice & Alerts',
        'hideText' => 'Hide',
        'showText' => 'Expand',
      ]
    ];

    $nodeStorage = $this->entityTypeManager()->getStorage('node');

    $currentPage = $nodeStorage->load($nid);
    $nodes = [];

    if ($currentPage && !in_array($currentPage->getType(), ['alert', 'campaign_landing'])) {
      $org_ids = [];

      if ($currentPage->hasField('field_organizations')) {
        $organizations = $currentPage->get('field_organizations')->getValue();
        foreach ($organizations as $org) {
          $org_ids[] = $org['target_id'];
        }
      }

      $query = $nodeStorage->getQuery();
      $query->condition('type', 'alert');
      $query->condition('status', 1);

      $orCondition = $query->orConditionGroup();
      $orCondition->condition('field_target_page.target_id', $nid);

      if (!empty($org_ids)) {
        $orCondition->condition('field_target_organization.target_id', $org_ids, 'IN');
      }

      $query->condition($orCondition);

      $nids = $query->execute();

      $nodes = $nodeStorage->loadMultiple(array_values($nids));

      $alerts = [];

      foreach ($nodes as $node) {

        $changed_date = $node->getChangedTime();
        $id = $node->uuid() . '__' . $changed_date;
        $label = $node->label();

        $items = $node->get('field_alert')->referencedEntities();
        $severity = $node->get('field_alert_severity')->getString();
        $hide_message = $node->get('field_alert_hide_message')->getString();

        $item = reset($items);
        $timestamp = $item->get('field_emergency_alert_timestamp')->getString();
        $unix_timestamp = strtotime($timestamp);
        $timestamp = $this->dateFormatter->format($unix_timestamp, 'custom', 'M. jS, Y, h:i a');

        if ($severity == 'informational_notice') {
          $icon = 'input-warning';
        }
        else {
          $icon = 'input-error';
        }

        $alert = [
          'id' => $id,
          'accordionLabel' => $this->t('Expand @label', ['@label' => $label]),
          'icon' => $icon,
          'title' => $label,
          'suffix' => $timestamp,
        ];

        if ($hide_message == '1') {
          $alert_title = $node->get('field_alert_title_link')->getString();

          if ($alert_title == 'link') {
            $uri = $node->get('field_alert_title_link_target')->getString();
            if ($uri) {
              $alert['link'] = Url::fromUri($uri)->toString(TRUE)->getGeneratedUrl();
            }
          }
        }
        else {
          $alert['accordion'] = TRUE;
          $alert['isExpanded'] = FALSE;

          $link_type = $item->get('field_emergency_alert_link_type')->getString();
          $url = FALSE;

          if ($link_type == '1') {
            $uri = $item->get('field_emergency_alert_link')->getString();
            if ($uri) {
              $url = Url::fromUri($uri)->toString(TRUE)->getGeneratedUrl();
            }
          }
          elseif ($link_type == '0') {
            $url = $node->toUrl()->toString(TRUE)->getGeneratedUrl();
            $url .= '#' . $item->id();
          }

          $content = $item->get('field_emergency_alert_message')->getString();;

          if ($url) {
            $alert['decorativeLink'] = [
              'href' => $url,
              'text' => $content,
              'info' => $this->t('Learn more @label', ['@label' => $label]),
              'property' => '',
            ];
          }
          else {
            $alert['richText'] = [
              'rteElements' => [
                [
                  'path' => '@atoms/11-text/paragraph.twig',
                  'data' => [
                    'paragraph' => ['text' => $content]
                  ]
                ]
              ]
            ];
          }
        }

        $alerts[$unix_timestamp] = $alert;
      }

      krsort($alerts);
      $results['headerAlerts'] = array_values($alerts);
    }

    $tags[] = MASS_ALERTS_TAG_GLOBAL;
    $tags = Cache::buildTags(MASS_ALERTS_TAG_ORG, $org_ids);
    $tags[] = MASS_ALERTS_TAG_PAGE . ":$nid";
    $build = [
      '#theme' => 'mass_alerts_page',
      '#headerAlerts' => $results['headerAlerts'],
      '#headerTitle' => $results['headerTitle'],
      '#cache' => [
        'max-age' => Cache::PERMANENT,
        'tags' => $tags,
      ],
    ];

    $output = $this->renderer->renderRoot($build);
    $this->attachSvg($output);

    $response = new CacheableResponse($output);
    foreach ($nodes as $node) {
      $response->addCacheableDependency($node);
    }
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $response;
  }

  /**
   * Attach svg to rendered content.
   */
  private function attachSvg(&$content) {

    $svgs = Helper::findSvg($content);
    $inlined = [];

    if ($svgs) {
      foreach ($svgs as $path) {
        $replacement = '';
        if ($svgNode = Helper::getSvg($path)) {
          $hash = md5($path);
          $svgNode->setAttribute('id', $hash);
          $replacement = Helper::getSvgEmbed($hash);
          $inlined[] = Helper::getSvgSource($hash, $svgNode);
        }
        $content = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $content);
      }
    }

    if (!empty($inlined)) {
      $content .= Helper::wrapInlinedSvgs($inlined);
    }
  }

}
