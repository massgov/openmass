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
        $this->displayIcon(...),
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
  public function displayIcon($name, $width = '', $height = '', $class = '', $bold = true) {
    $path = $this->getIconPath($name);
    
    // Build the template with dimensions and class
    $template = '<svg-placeholder path="{{path}}"';
    $context = ['path' => $path];
    
    if ($width) {
      $template .= ' width="{{width}}"';
      $context['width'] = $width;
    }
    if ($height) {
      $template .= ' height="{{height}}"';
      $context['height'] = $height;
    }
    if ($class) {
      $template .= ' class="{{class}}"';
      $context['class'] = $class;
    }
    
    $template .= '>';
    
    return [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => $context,
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
   * @param bool $bold
   *   Whether to use the bold version of the icon.
   *
   * @return string
   *   The path to the icon file.
   */
  private function getIconPath($name, $bold = true) {
    $parts = pathinfo($name) + ['dirname' => ''];

    // Temporary BC layer to convert twig SVG names to file ones.
    if ($parts['dirname'] === '@atoms/05-icons') {
      $newName = preg_replace('/^svg-/', '', $parts['filename']);
      @trigger_error(sprintf('Deprecated icon path used: %s. This will be converted to %s for the time being, but you should replace the in-code reference so it does not break in the future.', $name, $newName), E_USER_DEPRECATED);
      $parts = pathinfo($newName);
    }
    
    // Pass a named icon through to the predefined icons directory.
    if (!isset($parts['extension']) && $parts['dirname'] === '.') {
      $iconName = $parts['filename'];
      
      if ($bold) {
        // Try bold version first
        $boldPath = sprintf('%s/bold/%s--bold.svg', $this->iconDirectory, $iconName);
        if (file_exists($boldPath)) {
          return sprintf('%s/bold/%s--bold.svg', $this->iconDirectory, $iconName);
        }
        
        // Fallback to regular version
        $regularPath = sprintf('%s/%s.svg', $this->iconDirectory, $iconName);
        if (file_exists($regularPath)) {
          return $regularPath;
        }
      } else {
        // Use regular version
        return sprintf('%s/%s.svg', $this->iconDirectory, $iconName);
      }
    }

    // Otherwise, $name is a file path.
    return $name;
  }

}
