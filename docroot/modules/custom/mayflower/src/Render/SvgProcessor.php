<?php

namespace Drupal\mayflower\Render;

use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
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
          if ($svgNode = $this->getSvg($path)) {
            $hash = md5($path);
            $svgNode->setAttribute('id', $hash);
            $replacement = $this->getEmbed($hash);
            $inlined[] = $this->getSource($hash, $svgNode);
          }
          $content = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $content);
        }

        unset($attached['svg']);
      }
      $content = str_replace('<svg-sprite-placeholder>', $this->wrapInlinedSvgs($inlined), $content);
      $response->setContent($content);
      $response->setAttachments($attached);
      return $this->inner->processAttachments($response);
    } 
    elseif ($response instanceof ViewAjaxResponse) {
      $commands = &$response->getCommands();
      $attached = $response->getAttachments();

      foreach ($commands as &$command) {
        if ($command['command'] == 'insert') {
          preg_match_all("<svg-placeholder path=\"(.*\.svg)\">", $command['data'], $matches);

          if (!empty($matches[1])) {
            foreach (array_unique(array_filter($matches[1])) as $path) {
              $replacement = '';
              if ($svgNode = $this->getSvg($path)) {
                $hash = md5($path);
                $svgNode->setAttribute('id', $hash);
                $replacement = $this->getEmbed($hash);
              }
              $command['data'] = str_replace(sprintf('<svg-placeholder path="%s">', $path), $replacement, $command['data']);
            }
          }
        }
      }

      return $this->inner->processAttachments($response);
    } else {
      throw new \InvalidArgumentException('\Drupal\Core\Render\HtmlResponse instance expected.');
    }
  }

  /**
   * Return the HTML to reference an SVG.
   */
  private function getEmbed($hash) {
    return sprintf('<svg aria-hidden="true" focusable="false"><use xlink:href="#%s"></use></svg>', $hash);
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

}
