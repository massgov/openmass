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
 *
 * This allows us to do use the following render array:
 *
 * [
 *   '#markup' => '<svg-placeholder path="foo.svg">',
 *   '#attachments' => [
 *     'svg' => ['foo.svg']
 *   ]
 * ]
 *
 * On output, 'foo.svg' will be imported into the bottom of the page 1x, and the
 * placeholder will be swapped with an SVG use reference, embedding the SVG once
 * and using it everywhere.
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
   *
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $html_response_attachments_processor
   *   The HTML response attachments processor service.
   * @param \Drupal\Core\Asset\AssetResolverInterface $asset_resolver
   *   An asset resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $css_collection_renderer
   *   The CSS asset collection renderer.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $js_collection_renderer
   *   The JS asset collection renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(AttachmentsResponseProcessorInterface $html_response_attachments_processor, AssetResolverInterface $asset_resolver, ConfigFactoryInterface $config_factory, AssetCollectionRendererInterface $css_collection_renderer, AssetCollectionRendererInterface $js_collection_renderer, RequestStack $request_stack, RendererInterface $renderer, ModuleHandlerInterface $module_handler) {
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
    parent::__construct($asset_resolver, $config_factory, $css_collection_renderer, $js_collection_renderer, $request_stack, $renderer, $module_handler);
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    if ($response instanceof HtmlResponse) {

      // (Note this is copied verbatim from
      // \Drupal\Core\Render\HtmlResponseAttachmentsProcessor::processAttachments)
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
            $svgNode->setAttribute('id', $hash);
            $replacement = Helper::getSvgEmbed($hash);
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
                $svgNode->setAttribute('id', $hash);
                $replacement = Helper::getSvgEmbed($hash);
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
      return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    }
    else {
      throw new \InvalidArgumentException('\Drupal\Core\Render\HtmlResponse instance expected.');
    }
  }

}
