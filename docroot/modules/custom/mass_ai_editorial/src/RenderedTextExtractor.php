<?php

namespace Drupal\mass_ai_editorial;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\mass_entity_usage\MassEntityUsageInterface;
use Drupal\node\NodeInterface;

/**
 * Renders nodes in a view mode and normalizes the result to canonical text.
 */
class RenderedTextExtractor {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly RouteProviderInterface $routeProvider,
    private readonly ?BreadcrumbBuilderInterface $breadcrumbBuilder = NULL,
    private readonly ?MassEntityUsageInterface $entityUsage = NULL,
  ) {}

  /**
   * Builds the flattened text representation for a node.
   */
  public function extract(NodeInterface $node, string $view_mode = 'ai_index'): string {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $build = $view_builder->view($node, $view_mode, $node->language()->getId());
    $html = (string) $this->renderer->renderInIsolation($build);
    $prefixes = array_filter([
      $this->buildBreadcrumbText($node),
      $this->buildIncomingLinksText($node),
    ]);
    $prefix = $prefixes ? implode("\n\n", $prefixes) . "\n\n" : '';

    return trim($this->cleanUtf8($prefix . $this->normalizeHtml($html)));
  }

  /**
   * Converts rendered HTML to stable text without presentation noise.
   */
  public function normalizeHtml(string $html): string {
    $html = preg_replace('@<(script|style|noscript|svg)[^>]*?>.*?</\\1>@si', ' ', $html) ?? $html;
    $html = $this->preserveLinks($html);
    $html = $this->preserveHeadings($html);
    $html = preg_replace('@<(br|/p|/div|/section|/article|/li|/h[1-6]|/tr)\b[^>]*>@i', "\n", $html) ?? $html;
    $text = Html::decodeEntities(strip_tags($html));
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/ *\n+ */', "\n", $text) ?? $text;
    $text = preg_replace('/^(?:Show more|Show less)$/mi', '', $text) ?? $text;
    $text = $this->removeFeedbackFormText($text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

    return trim($this->cleanUtf8($text));
  }

  /**
   * Rewrites anchors so their link targets survive strip_tags().
   */
  private function preserveLinks(string $html): string {
    return preg_replace_callback('@<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>@is', function (array $matches): string {
      $href = $this->normalizeHref(Html::decodeEntities($matches[2]));
      $label = trim($this->normalizeLinkLabel($matches[3]));

      if ($href === '' || $label === '') {
        return $label;
      }

      return $label . ' [href: ' . $href . ']';
    }, $html) ?? $html;
  }

  /**
   * Rewrites headings so their semantic level survives text flattening.
   */
  private function preserveHeadings(string $html): string {
    return preg_replace_callback('@<h([1-6])\b[^>]*>(.*?)</h\1>@is', function (array $matches): string {
      $level = (int) $matches[1];
      $text = trim($this->normalizeLinkLabel($matches[2]));
      if ($text === '') {
        return '';
      }

      return "\n\nHeading level " . $level . ': ' . $text . "\n";
    }, $html) ?? $html;
  }

  /**
   * Normalizes internal site URLs so the model does not compare hostnames.
   */
  private function normalizeHref(string $href): string {
    $href = trim($href);
    if ($href === '') {
      return '';
    }

    $url = str_starts_with($href, '//') ? 'https:' . $href : $href;
    $parts = parse_url($url);
    if (!is_array($parts)) {
      return $href;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($host, ['mass.gov', 'www.mass.gov', 'mass.local'], TRUE)) {
      return $href;
    }

    $path = (string) ($parts['path'] ?? '/');
    $normalized = $path !== '' ? $path : '/';
    if (!empty($parts['query'])) {
      $normalized .= '?' . $parts['query'];
    }
    if (!empty($parts['fragment'])) {
      $normalized .= '#' . $parts['fragment'];
    }

    return $normalized;
  }

  /**
   * Converts anchor contents to a compact plain-text label.
   */
  private function normalizeLinkLabel(string $html): string {
    $text = Html::decodeEntities(strip_tags($html));
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return trim($text);
  }

  /**
   * Removes Mass.gov page feedback form boilerplate from indexed text.
   */
  private function removeFeedbackFormText(string $text): string {
    $patterns = [
      '/\nHeading level [1-6]: Help Us Improve Mass\.gov\b.*$/is',
      '/\nHelp Us Improve Mass\.gov\b.*$/is',
      '/\nDid you find what you were looking for on this webpage\?.*$/is',
      '/\nPlease let us know how we can improve this page\..*$/is',
    ];

    foreach ($patterns as $pattern) {
      $text = preg_replace($pattern, '', $text, 1) ?? $text;
    }

    return $text;
  }

  /**
   * Builds a link-aware breadcrumb line for the indexed representation.
   */
  private function buildBreadcrumbText(NodeInterface $node): string {
    if (!$this->breadcrumbBuilder) {
      return '';
    }

    try {
      $route = $this->routeProvider->getRouteByName('entity.node.canonical');
      $route_match = new RouteMatch(
        'entity.node.canonical',
        $route,
        ['node' => $node],
        ['node' => (string) $node->id()]
      );
      if (!$this->breadcrumbBuilder->applies($route_match)) {
        return '';
      }

      $links = $this->breadcrumbBuilder->build($route_match)->getLinks();
      if (!$links) {
        return '';
      }

      $parts = [];
      foreach ($links as $link) {
        $text = trim((string) $link->getText());
        if ($text === '') {
          continue;
        }
        $url = $link->getUrl();
        $href = $url->isRouted() && $url->getRouteName() === '<none>' ? '' : $this->normalizeHref($url->toString());
        $parts[] = $href !== '' ? $text . ' [href: ' . $href . ']' : $text;
      }

      return $parts ? 'Breadcrumb: ' . implode(' > ', $parts) : '';
    }
    catch (\Throwable) {
      return '';
    }
  }

  /**
   * Builds a backlink summary from entity usage records.
   */
  private function buildIncomingLinksText(NodeInterface $node): string {
    if (!$this->entityUsage) {
      return '';
    }

    try {
      $sources = $this->entityUsage->listSourcesPage($node, 0, FALSE);
      if (!$sources) {
        return '';
      }

      $lines = [];
      foreach ($sources as $source) {
        if (($source['source_type'] ?? '') !== 'node') {
          continue;
        }
        $source_id = (int) ($source['source_id'] ?? 0);
        if (!$source_id || $source_id === (int) $node->id()) {
          continue;
        }

        $source_node = $this->entityTypeManager->getStorage('node')->load($source_id);
        if (!$source_node instanceof NodeInterface) {
          continue;
        }

        $url = $this->normalizeHref($source_node->toUrl()->toString());
        $details = [];
        if (!empty($source['field_name'])) {
          $details[] = 'field: ' . $source['field_name'];
        }
        if (!empty($source['method'])) {
          $details[] = 'method: ' . $source['method'];
        }
        if (!empty($source['count'])) {
          $details[] = 'count: ' . $source['count'];
        }

        $line = $source_node->label() . ' [href: ' . $url . ']';
        if ($details) {
          $line .= ' (' . implode(', ', $details) . ')';
        }
        $lines[] = $line;

        if (count($lines) >= 25) {
          break;
        }
      }

      return $lines ? "Incoming links to this page:\n" . implode("\n", $lines) : '';
    }
    catch (\Throwable) {
      return '';
    }
  }

  /**
   * Removes malformed UTF-8 byte sequences before text is stored or embedded.
   */
  private function cleanUtf8(string $text): string {
    if (Unicode::validateUtf8($text)) {
      return $text;
    }

    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    if ($clean !== FALSE && Unicode::validateUtf8($clean)) {
      return $clean;
    }

    return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
  }

}
