<?php

namespace Drupal\mayflower\Render;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\mayflower\Helper;
use Drupal\views\Ajax\ViewAjaxResponse;

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
class SvgProcessor implements AttachmentsResponseProcessorInterface {

  /**
   * The decorated attachment processor.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  private $inner;

  /**
   * Constructor.
   */
  public function __construct(AttachmentsResponseProcessorInterface $inner) {
    $this->inner = $inner;
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    if ($response instanceof HtmlResponse) {
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
      return $this->inner->processAttachments($response);
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
      return $this->inner->processAttachments($response);
    }
    elseif ($response instanceof AjaxResponse) {
      return $this->inner->processAttachments($response);
    }
    else {
      throw new \InvalidArgumentException('\Drupal\Core\Render\HtmlResponse instance expected.');
    }
  }

}
