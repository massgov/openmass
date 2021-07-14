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
          'title' => $node->label(),
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
          'text' => $message . ' ' . $timestamp,
        ];

        $alerts[$unix_timestamp] = [
          'id' => $item_id,
          'link' => $link,
        ];
      }

      ksort($alerts);
      $results['emergencyAlerts']['alerts'] = array_values($alerts);
    }

    $results['emergencyAlerts']['emergencyHeader']['alerts'] = count($results['emergencyAlerts']['alerts']);

    $build = [
      '#theme' => 'mass_alerts_sitewide',
      '#emergencyAlerts' => $results['emergencyAlerts'],
      '#cache' => [
        'tags' => [
          MASS_ALERTS_TAG_SITEWIDE . ':list'
        ]
      ],
    ];

    $output = $this->renderer->renderRoot($build);

    preg_match_all("<svg-placeholder path=\"(.*\.svg)\">", $output, $matches);
    if (!empty($matches[1])) {
      foreach (array_unique(array_filter($matches[1])) as $path) {
        $replacement = '';
        if ($svgNode = $this->getSvg($path)) {
          $hash = md5($path);
          $svgNode->setAttribute('id', $hash);
          $replacement = $this->getEmbed($hash);
          $inlined[] = $this->getSource($hash, $svgNode);
        }
        $output = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $output);
      }
    }

    $output .= $this->wrapInlinedSvgs($inlined);
    $response = new CacheableResponse($output);

    if ($node) {
      $response->addCacheableDependency($node);
    }

    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    $response->setMaxAge(60);
    return $response;
  }

  /**
   * Return the HTML to SVG source.
   *
   * An icon unit is wrapped with <symbol> to add structure and semantics
   * to it, which promotes accessibility.
   *
   * <title> and <desc> tags can be added within the <symbol> for
   * accessibility, but in our case, the svg icons are decorative,
   * and they are not necessary.
   * Ones used for linked images are handled their accessibility
   * treatment with their parent <a>.
   *
   * The viewBox can be defined on the <symbol>, so you don't need to use it
   * in the markup (easier and less error prone).
   * Symbols don't display as you define them, so no need for a <defs> block.
   */
  private function getSource($hash, \DOMElement $sourceNode) {
    $symbol = $sourceNode->ownerDocument->createElementNS($sourceNode->namespaceURI, 'symbol');

    // Copy attributes from <svg> to <symbol>.
    /** @var \DOMAttr $attribute */
    foreach ($sourceNode->attributes as $attribute) {
      $symbol->setAttribute($attribute->name, $attribute->value);
    }

    // Set an explicit ID.
    $symbol->setAttribute('id', $hash);

    // Copy all child nodes from the SVG to the symbol.
    // This has to be a double loop due to an issue with DOMNodeList.
    // @see http://php.net/manual/en/domnode.appendchild.php#121829
    foreach ($sourceNode->childNodes as $node) {
      $children[] = $node;
    }

    foreach ($children as $child) {
      $symbol->appendChild($child);
    }

    return $sourceNode->ownerDocument->saveXML($symbol);
  }

  /**
   * Load a single SVG as a DOMElement.
   *
   * @return \DOMElement|null
   *   The SVG's DOMElement, or null if the SVG file was not found.
   */
  private function getSvg($path) {
    // Make sure the file exists before trying to fetch it and parse it as an
    // XML document.
    if (!file_exists($path)) {
      trigger_error(sprintf('Not a valid file: "%s"', $path), E_USER_DEPRECATED);
      return;
    }
    // For security reasons, we don't want to allow anything but an .svg file
    // to be included this way.
    if (!pathinfo($path, PATHINFO_EXTENSION) === 'svg') {
      trigger_error(sprintf('Invalid SVG file: "%s"', $path), E_USER_WARNING);
      return;
    }
    if ($svg = file_get_contents($path)) {
      $doc = new \DOMDocument('1.0', 'UTF-8');
      if ($doc->loadXML($svg)) {
        return $doc->firstChild;
      }
      // No need to error_log here. \DomDocument will log for us.
    }

  }

  /**
   * Wrap an array of SVG strings with a div that hides them from display.
   */
  private function wrapInlinedSvgs(array $inlineSvgs) {
    if ($inlineSvgs) {
      // All icons can be wrapped in one <svg>.
      return sprintf('<svg xmlns="http://www.w3.org/2000/svg" style="display: none">%s</svg>', implode('', $inlineSvgs));
    }
    return '';
  }

  /**
   * Return the HTML to reference an SVG.
   */
  private function getEmbed($hash) {
    return sprintf('<svg aria-hidden="true" focusable="false"><use xlink:href="#%s"></use></svg>', $hash);
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

    if ($currentPage) {
      $organizations = $currentPage->get('field_organizations')->getValue();
      $org_ids = [];

      foreach ($organizations as $org) {
        $org_ids[] = $org['target_id'];
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

        $items = $node->get('field_alert')->referencedEntities();
        $item = reset($items);
        $link_type = $item->get('field_emergency_alert_link_type')->getString();

        if ($link_type == '1') {
          $uri = $item->get('field_emergency_alert_link')->getString();
          if ($uri) {
            $url = Url::fromUri($uri)->toString(TRUE)->getGeneratedUrl();
          }
        }
        else {
          $url = $node->toUrl()->toString(TRUE)->getGeneratedUrl();
          $url .= '#' . $item->id();
        }

        $label = $node->label();
        $severity = $node->get('field_alert_severity')->getString();
        $content = $item->get('field_emergency_alert_message')->getString();;
        $timestamp = $item->get('field_emergency_alert_timestamp')->getString();
        $unix_timestamp = strtotime($timestamp);
        $timestamp = $this->dateFormatter->format($unix_timestamp, 'custom', 'M. jS, Y, h:i a');

        if ($severity == 'informational_notice') {
          $icon = 'input-warning';
        } else {
          $icon = 'input-error';
        }

        $alert = [
          'id' => $id,
          'accordion' => true,
          'isExpanded' => false,
          'accordionLabel' => $this->t('Expand @label', ['@label' => $label]),
          'icon' => $icon,
          'title' => $label,
          'suffix' => $timestamp,
          'richText' => [
            'rteElements' => [
              [
                'path' => '@atoms/11-text/paragraph.twig',
                'data' => [
                  'paragraph' => ['text' => $content]
                ]
              ]
            ]
          ],
          'decorativeLink' => [
            'href' => '',
            'text' => $this->t('Learn more'),
            'info' => $this->t('Learn more @label', ['@label' => $label]),
            'property' => '',
          ]
        ];

        $alerts[$unix_timestamp] = $alert;
      }

      krsort($alerts);
      $results['headerAlerts'] = array_values($alerts);
    }

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

    preg_match_all("<svg-placeholder path=\"(.*\.svg)\">", $output, $matches);
    if (!empty($matches[1])) {
      foreach (array_unique(array_filter($matches[1])) as $path) {
        $replacement = '';
        if ($svgNode = $this->getSvg($path)) {
          $hash = md5($path);
          $svgNode->setAttribute('id', $hash);
          $replacement = $this->getEmbed($hash);
          $inlined[] = $this->getSource($hash, $svgNode);
        }
        $output = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $output);
      }
    }

    $output .= $this->wrapInlinedSvgs($inlined);

    $response = new CacheableResponse($output);
    foreach ($nodes as $node) {
      $response->addCacheableDependency($node);
    }
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $response;
  }

}
