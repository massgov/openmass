<?php

namespace Drupal\mayflower\Render;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\HtmlResponseAttachmentsProcessor;
use Drupal\Core\Render\RendererInterface;
use Drupal\mayflower\Helper;
use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Response attachments processor to dump SVGs to a single block on the page.
 */
class SvgProcessor extends HtmlResponseAttachmentsProcessor {

  /**
   * The HTML response attachments processor service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  protected AttachmentsResponseProcessorInterface $htmlResponseAttachmentsProcessor;

  /**
   * Constructs a SvgProcessor object.
   */
  public function __construct(AttachmentsResponseProcessorInterface $html_response_attachments_processor, AssetResolverInterface $asset_resolver, ConfigFactoryInterface $config_factory, AssetCollectionRendererInterface $css_collection_renderer, AssetCollectionRendererInterface $js_collection_renderer, RequestStack $request_stack, RendererInterface $renderer, ModuleHandlerInterface $module_handler) {
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
    parent::__construct($asset_resolver, $config_factory, $css_collection_renderer, $js_collection_renderer, $request_stack, $renderer, $module_handler);
  }

  /**
   * Extract width and height from original SVG.
   *
   * @param \DOMElement $svgNode
   *   The original SVG node.
   *
   * @return array
   *   Array with width and height attributes.
   */
  protected function extractDimensions(\DOMElement $svgNode) {
    $dimensions = [];
    if ($svgNode->hasAttribute('width')) {
      $dimensions['width'] = $svgNode->getAttribute('width');
    }
    if ($svgNode->hasAttribute('height')) {
      $dimensions['height'] = $svgNode->getAttribute('height');
    }
    return $dimensions;
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    if ($response instanceof HtmlResponse) {

      try {
        $response = $this->renderPlaceholders($response);
      }
      catch (EnforcedResponseException $e) {
        return $e->getResponse();
      }

      $content = $response->getContent();
      $attached = $response->getAttachments();
      $inlined = [];

      // Store dimensions for each path
      $svgDimensions = [];

      // Look for svg-placeholder elements with dimensions AND bold parameter in the content
      preg_match_all('/<svg-placeholder\s+path="([^"]*)"(?:[^>]*width="([^"]*)")?(?:[^>]*height="([^"]*)")?(?:[^>]*class="([^"]*)")?[^>]*>/', $content, $placeholderMatches, PREG_SET_ORDER);

      foreach ($placeholderMatches as $match) {
        $path = $match[1];
        $width = isset($match[2]) && $match[2] !== '' ? $match[2] : '';
        $height = isset($match[3]) && $match[3] !== '' ? $match[3] : '';
        $class = isset($match[4]) && $match[4] !== '' ? $match[4] : '';

        // Add to SVG attachments if not already there
        if (!isset($attached['svg'])) {
          $attached['svg'] = [];
        }
        if (!in_array($path, $attached['svg'])) {
          $attached['svg'][] = $path;
        }

        // Store dimensions for this path
        $svgDimensions[$path] = [];
        if ($width) {
          $svgDimensions[$path]['width'] = $width;
        }
        if ($height) {
          $svgDimensions[$path]['height'] = $height;
        }
        if ($class) {
          $svgDimensions[$path]['class'] = $class;
        }
      }

      // Process SVG attachments
      if (isset($attached['svg'])) {
        foreach (array_unique(array_filter($attached['svg'])) as $path) {
          $replacement = '';
          if ($svgNode = Helper::getSvg($path)) {
            $hash = md5($path);

            // Use stored dimensions if available, otherwise extract from SVG
            if (isset($svgDimensions[$path]) && !empty($svgDimensions[$path])) {
              $dimensions = $svgDimensions[$path];
            }
            else {
              $dimensions = $this->extractDimensions($svgNode);
            }

            $svgNode->setAttribute('id', $hash);
            $replacement = Helper::getSvgEmbed($hash, $dimensions);
            $inlined[] = Helper::getSvgSource($hash, $svgNode);
          }

          // Replace all placeholders for this path (including ones with attributes)
          $content = preg_replace(
            sprintf('/<svg-placeholder\s+path="%s"[^>]*>/', preg_quote($path, '/')),
            $replacement,
            $content
          );
        }

        unset($attached['svg']);
      }

      $content = str_replace('<svg-sprite-placeholder>', Helper::wrapInlinedSvgs($inlined), $content);
      $response->setContent($content);
      $response->setAttachments($attached);
      return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    }
    elseif ($response instanceof ViewAjaxResponse || $response instanceof AjaxResponse) {
      // Handle AJAX responses
      return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    }
    else {
      throw new \InvalidArgumentException('\Drupal\Core\Render\HtmlResponse instance expected.');
    }
  }

}
