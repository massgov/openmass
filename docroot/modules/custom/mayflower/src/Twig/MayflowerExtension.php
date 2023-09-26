<?php

namespace Drupal\mayflower\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig Extension to connect Mayflower.
 *
 * This extension should be kept in sync with the functions used in Mayflower.
 */
class MayflowerExtension extends AbstractExtension {

  private $iconDirectory;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->iconDirectory = sprintf('%s/assets/images/icons',
      mayflower_get_path()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'mayflower';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('icon',
        [$this, 'displayIcon'],
        ['is_safe' => ['html']]
      ),
    ];
  }

  /**
   * Build the render array for a single icon.
   *
   * This will be converted to an SVG use statement later on.
   *
   * @see \Drupal\mayflower\Render\SvgProcessor::processAttachments()
   */
  public function displayIcon($name) {
    $path = $this->getIconPath($name);
    return [
      '#type' => 'inline_template',
      '#template' => '<svg-placeholder path="{{path}}">',
      '#context' => ['path' => $path],
      '#attached' => [
        'svg' => [$path],
      ],
    ];
  }

  /**
   * Return the filesystem path for an icon.
   *
   * This function can handle:
   *
   * Legacy Twig icons (@atoms/05-icons/svg-circle-chevron.twig).
   * Named icons (circle-chevron).
   * Filesystem paths (core/misc/icons/bebebe/ex.svg).
   *
   * @param string $name
   *   The name of the icon.
   *
   * @return string
   *   The path to the icon file.
   */
  private function getIconPath($name) {
    $parts = pathinfo($name) + ['dirname' => ''];

    // Temporary BC layer to convert twig SVG names to file ones.
    if ($parts['dirname'] === '@atoms/05-icons') {
      $newName = preg_replace('/^svg-/', '', $parts['filename']);
      trigger_error(sprintf('Deprecated icon path used: %s. This will be converted to %s for the time being, but you should replace the in-code reference so it does not break in the future.', $name, $newName), E_USER_DEPRECATED);
      $parts = pathinfo($newName);
    }
    // Pass a named icon through to the predefined icons directory.
    if (!isset($parts['extension']) && $parts['dirname'] === '.') {
      return sprintf('%s/%s.svg', $this->iconDirectory, $parts['filename']);
    }

    // Otherwise, $name is a file path.
    // Note: This can result in empty strings or non-SVG files being passed
    // on as icons. We don't want to do file_exists() checks here, since this
    // function is called a lot, so that is handled further down the line.
    // @see Drupal\mayflower\Render\SvgProcessor::getSvg().
    return $name;
  }

}
