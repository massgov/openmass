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

      if (isset($attached['svg'])) {

        foreach (array_unique(array_filter($attached['svg'])) as $path) {
          // Use an empty replacement to avoid <svg-placeholder> showing up
          // when the icon path is valid.
          $replacement = '';
          if ($svgNode = Helper::getSvg($path)) {
            $hash = md5($path);
            
            // Extract dimensions from original SVG
            $dimensions = $this->extractDimensions($svgNode);
            
            $svgNode->setAttribute('id', $hash);
            $replacement = Helper::getSvgEmbed($hash, $dimensions);
            $inlined[] = Helper::getSvgSource($hash, $svgNode);
          }
          $content = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $content);
        }

        unset($attached['svg']);
      }
      $content = str_replace('<svg-sprite-placeholder>', Helper::wrapInlinedSvgs($inlined), $content);
      $response->setContent($content);
      $response->setAttachments($attached);
      return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    }
    elseif ($response instanceof ViewAjaxResponse) {
      $commands = &$response->getCommands();

      foreach ($commands as &$command) {
        if ($command['command'] == 'insert') {

          $svgs = Helper::findSvg($command['data']);
          $inlined = [];

          if ($svgs) {
            foreach ($svgs as $path) {
              $replacement = '';
              if ($svgNode = Helper::getSvg($path)) {
                $hash = md5($path);
                
                // Extract dimensions from original SVG
                $dimensions = $this->extractDimensions($svgNode);
                
                $svgNode->setAttribute('id', $hash);
                $replacement = Helper::getSvgEmbed($hash, $dimensions);
                $inlined[] = Helper::getSvgSource($hash, $svgNode);
              }
              $command['data'] = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $command['data']);
            }
          }
        }
      }
      return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    }
    elseif ($response instanceof AjaxResponse) {
      $commands = &$response->getCommands();
      foreach ($commands as &$command) {
        if (isset($command['data'])) {
          $svgs = Helper::findSvg($command['data']);
          $inlined = [];

          if ($svgs) {
            foreach ($svgs as $path) {
              $replacement = '';
              if ($svgNode = Helper::getSvg($path)) {
                $hash = md5($path);
                
                // Extract dimensions from original SVG
                $dimensions = $this->extractDimensions($svgNode);
                
                $svgNode->setAttribute('id', $hash);
                $replacement = Helper::getSvgEmbed($hash, $dimensions);
                $inlined[] = Helper::getSvgSource($hash, $svgNode);
              }
              $command['data'] = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $command['data']);
            }
          }

          // Add inlined SVGs as a sprite or placeholder.
          $command['data'] .= Helper::wrapInlinedSvgs($inlined);
        }
      }
      return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    }
    else {
      throw new \InvalidArgumentException('\Drupal\Core\Render\HtmlResponse instance expected.');
    }
  }

}
