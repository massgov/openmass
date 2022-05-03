<?php
declare(strict_types=1);

namespace Drupal\mass_content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;

/**
 * Defines an images styles warmer.
 */
class ImageStylesWarmer {

  /**
   * The file entity storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $file;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $image;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyles;

  /**
   * Constructs a ImageStylesWarmer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $file_storage
   *   The file storage.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $image_style_storage
   *   The image style storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $file_storage, ImageFactory $image_factory, EntityTypeManagerInterface $image_style_storage) {
    $this->file = $file_storage->getStorage('file');
    $this->image = $image_factory;
    $this->imageStyles = $image_style_storage->getStorage('image_style');
  }

  /**
   * {@inheritdoc}
   */
  public function warmUp(FileInterface $file): void {
    $initialImageStyles = ['action_banner_large_focal_point', 'hero1600x400_fp'];
    $this->doWarmUp($file, $initialImageStyles);
  }

  /**
   * {@inheritdoc}
   */
  private function doWarmUp(FileInterface $file, array $image_styles): void {
    if (!$this->validateImage($file) || empty($image_styles)) {
      return;
    }

    /* @var \Drupal\Core\Image\Image $image */
    /* @var \Drupal\image\Entity\ImageStyle $style */

    // Create image derivatives if they do not already exist.
    $styles = $this->imageStyles->loadMultiple($image_styles);
    $image_uri = $file->getFileUri();
    foreach ($styles as $style) {
      $derivative_uri = $style->buildUri($image_uri);
      if (!file_exists($derivative_uri)) {
        $style->createDerivative($image_uri, $derivative_uri);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  private function validateImage(FileInterface $file): bool {
    if ($file->isPermanent()) {
      $image = $this->image->get($file->getFileUri());
      $extensions = implode(' ', $image->getToolkit()->getSupportedExtensions());
      if ($image->isValid() && empty(file_validate_extensions($file, $extensions))) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
