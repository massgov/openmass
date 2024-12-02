<?php

namespace Drupal\mass_alerts\Controller;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mayflower\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for entity query.
 */
class AlertsController extends ControllerBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  const DURATION_PAGE = 900;
  const DURATION_SITE = 60;

  private DateFormatterInterface $dateFormatter;

  private Renderer $renderer;

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
    $query->condition('type', 'sitewide_alert');
    $query->condition('status', 1);

    $sitewide = $query->accessCheck(FALSE)->execute();
    // Last item.
    $sitewide = reset($sitewide);
    $results = ['emergencyAlerts' => []];

    if (!empty($sitewide)) {
      $node = $nodeStorage->load($sitewide);
      $changed_date = $node->getChangedTime();
      $id = $node->uuid() . '__' . $changed_date;

      $emergencyAlerts = [
        'id' => $id,
      ];

      $results = [];

      $results['emergencyAlerts'] = $emergencyAlerts;
      $results['emergencyAlerts']['alerts'] = [];

      $alerts = [];
      $alert_items = $node->get('field_sitewide_alert')->referencedEntities();
      foreach ($alert_items as $item) {
        $unix_timestamp = '';
        $timestamp = '';
        $item_id = $item->uuid() . '__' . $changed_date;
        if ($item->get('field_sitewide_alert_timestamp')->getString()) {
          $timestamp_string = $item->get('field_sitewide_alert_timestamp')->getString();
          $unix_timestamp = strtotime($timestamp_string);
          $timestamp = Helper::getDate($timestamp_string)->format('M. j, Y, h:i a');
        }

        $link_type = $item->get('field_sitewide_alert_link_type')->getString();
        $url = FALSE;

        if ($link_type == '1') {
          $uri = $item->get('field_sitewide_alert_link')->getString();
          if ($uri) {
            $url = Url::fromUri($uri)->toString(TRUE)->getGeneratedUrl();
          }
        }
        elseif ($link_type == '0') {
          $url = $node->toUrl()->toString(TRUE)->getGeneratedUrl();
          $url .= '#' . $item->id();
        }

        $message = $item->get('field_sitewide_alert_message')->getString();

        if ($url) {
          $link = [
            'chevron' => TRUE,
            'href' => $url,
            'text' => $message,
          ];
        }
        else {
          $link = ['text' => $message];
        }

        $alerts[$unix_timestamp] = [
          'id' => $item_id,
          'timeStamp' => $timestamp,
          'link' => $link,
        ];
      }

      ksort($alerts);
      $results['emergencyAlerts']['alerts'] = array_values($alerts);

      $severity = $node->get('field_sitewide_alert_severity')->getString();
      // The header prefix defaults to "Alerts" in the
      // emergency-header.twig molecule mayflower component.
      if ($severity == 'informational_notice') {
        $results['emergencyAlerts']['emergencyHeader']['prefix'] = "Notices";
      }
      $results['emergencyAlerts']['emergencyHeader']['title'] = $node->label();
    }

    $build = [
      '#theme' => 'mass_alerts_sitewide',
      '#emergencyAlerts' => $results['emergencyAlerts'],
      '#cache' => [
        'max-age' => self::DURATION_SITE,
        'tags' => [
          MASS_ALERTS_TAG_GLOBAL,
          MASS_ALERTS_TAG_SITEWIDE . ':list',
        ],
      ],
    ];

    $output = $this->renderer->renderRoot($build);
    $this->attachSvg($output);

    $response = new CacheableResponse($output);

    if (!empty($node)) {
      $response->addCacheableDependency($node);
    }

    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    $response->setMaxAge(self::DURATION_SITE);
    $response->headers->addCacheControlDirective('public');
    $this->addRevalidateHeaders($response);
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
        'text' => $this->t('Notices & Alerts'),
        'hideText' => $this->t('Hide'),
        'showText' => $this->t('Expand'),
      ],
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

      if ($currentPage->getType() == 'org_page') {
        $org_ids[] = $currentPage->id();
      }

      $query = $nodeStorage->getQuery();
      $query->condition('type', 'alert');
      $query->condition('status', 1);

      $orCondition = $query->orConditionGroup();

      $specific_page = $query
        ->andConditionGroup()
        ->condition('field_target_page.target_id', $nid)
        ->condition('field_alert_display.value', 'specific_target_pages');

      $orCondition->condition($specific_page);

      if (!empty($org_ids)) {
        $org_pages = $query
          ->andConditionGroup()
          ->condition('field_target_organization.target_id', $org_ids, 'IN')
          ->condition('field_alert_display.value', 'by_organization');

        $orCondition->condition($org_pages);
      }

      $query->condition($orCondition);

      $nids = $query->accessCheck(FALSE)->execute();

      $nodes = $nodeStorage->loadMultiple(array_values($nids));

      $alerts = [];

      foreach ($nodes as $node) {

        $changed_date = $node->getChangedTime();
        $id = $node->uuid() . '__' . $changed_date;
        $label = $node->label();
        $severity = $node->get('field_alert_severity')->getString();
        $hide_message = $node->get('field_alert_hide_message')->getString();

        if ($severity == 'informational_notice') {
          $icon = 'alert';
          $iconLabel = 'notice';
        }
        else {
          $icon = 'input-error';
          $iconLabel = 'alert';
        }

        $alert = [
          'id' => $id,
          'icon' => $icon,
          'iconLabel' => $iconLabel,
          'level' => 3,
          'title' => $label,
        ];

        $timestamp = $node->get('field_alert_node_timestamp')->getString();

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

          $items = $node->get('field_alert')->referencedEntities();

          // If by some reason there is not content, ignore this alert.
          if (empty($items)) {
            continue;
          }

          $alert['accordion'] = TRUE;
          $alert['isExpanded'] = FALSE;
          $messages = [];

          foreach ($items as $item) {

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

            $content = $item->get('field_emergency_alert_message')->getString();

            if ($url) {
              $messages[] = [
                'decorativeLink' => [
                  'href' => $url,
                  'text' => $content,
                  'info' => $this->t('Learn more @label', ['@label' => $label]),
                  'property' => '',
                ],
              ];
            }
            else {
              $messages[] = [
                'richText' => [
                  'rteElements' => [
                    [
                      'path' => '@atoms/11-text/paragraph.twig',
                      'data' => [
                        'paragraph' => ['text' => $content],
                      ],
                    ],
                  ],
                ],
              ];
            }
          }

          if (count($messages) == 1) {
            $alert = array_merge($alert, $messages[0]);
          }
          else {
            $alert['content'] = $messages;
          }
        }

        $unix_timestamp = strtotime($timestamp);
        if ($unix_timestamp) {
          $timestamp = Helper::getDate($timestamp)->format('M. j, Y, h:i a');
        }
        else {
          // Could be empty old alerts.
          $timestamp = '';
        }

        $alert['suffix'] = $this->t('Updated @timestamp', ['@timestamp' => $timestamp]);
        $alerts[$unix_timestamp . '-' . $node->uuid()] = $alert;
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
        'max-age' => self::DURATION_PAGE,
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
    $response->headers->addCacheControlDirective('public');
    $response->setMaxAge(self::DURATION_PAGE);
    $this->addRevalidateHeaders($response);
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

  /**
   * Add to the response the standard HTTP revalidate headers.
   *
   * @param \Drupal\Core\Cache\CacheableResponse $response
   *   A Symfony response.
   *
   * @throws \Exception
   */
  public function addRevalidateHeaders(CacheableResponse $response) {
    $response->setLastModified(new \DateTime(gmdate(DateTimePlus::RFC7231, \Drupal::time()->getRequestTime())));
    $response->setEtag(\Drupal::time()->getRequestTime());
  }

}
