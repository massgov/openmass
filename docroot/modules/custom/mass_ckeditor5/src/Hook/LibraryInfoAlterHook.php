<?php

declare(strict_types=1);

namespace Drupal\mass_ckeditor5\Hook;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\hux\Attribute\Hook;

/**
 * Hook implementations for library_info_alter.
 */
class LibraryInfoAlterHook {

  /**
   * The module name.
   */
  private const MODULE_NAME = 'mass_ckeditor5';

  /**
   * Constructs a LibraryInfoAlterHook object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   */
  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Implements hook_library_info_alter().
   *
   * Registers CKEditor 5 stylesheets from module's ckeditor5-stylesheets config.
   *
   * @param array $libraries
   *   The library definitions.
   * @param string $extension
   *   The extension name.
   *
   * @see https://www.drupal.org/docs/core-modules-and-themes/core-modules/ckeditor-5-module/how-to-style-custom-content-in-ckeditor-5#s-registering-ckeditor-5-stylesheets-from-a-module
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension !== 'ckeditor5') {
      return;
    }

    $module_path = $this->moduleExtensionList->getPath(self::MODULE_NAME);
    $info = $this->moduleExtensionList->getExtensionInfo(self::MODULE_NAME);

    if (!isset($info['ckeditor5-stylesheets']) || $info['ckeditor5-stylesheets'] === FALSE) {
      return;
    }

    $css = $info['ckeditor5-stylesheets'];
    $processed_css = [];

    foreach ($css as $key => $url) {
      // CSS URL is external or relative to Drupal root.
      if (UrlHelper::isExternal($url) || $url[0] === '/') {
        $processed_css[$key] = $url;
      }
      // CSS URL is relative to module.
      else {
        $processed_css[$key] = '/' . $module_path . '/' . $url;
      }
    }

    $libraries['internal.drupal.ckeditor5.stylesheets'] = [
      'css' => [
        'theme' => array_merge(
          $libraries['internal.drupal.ckeditor5.stylesheets']['css']['theme'] ?? [],
          array_fill_keys(array_values($processed_css), [])
        ),
      ],
    ];
  }

}
