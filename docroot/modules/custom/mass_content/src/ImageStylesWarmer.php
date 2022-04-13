<?php

namespace Drupal\mass_content;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\FileInterface;

/**
 * Defines an images styles warmer.
 */
class ImageStylesWarmer implements ImageStylesWarmerInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

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
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a ImageStylesWarmer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
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
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $file_storage, ImageFactory $image_factory, EntityTypeManagerInterface $image_style_storage) {
    $this->config = $config_factory->get('image_style_regenerate.settings');
    $this->file = $file_storage->getStorage('file');
    $this->image = $image_factory;
    $this->imageStyles = $image_style_storage->getStorage('image_style');
  }

  /**
   * {@inheritdoc}
   */
  public function warmUp(FileInterface $file, $initialImageStyles = []) {
    $initialImageStyles = ['action_banner_large', 'hero1600x400_fp'];
    $this->doWarmUp($file, $initialImageStyles);
  }

  /**
   * {@inheritdoc}
   */
  public function doWarmUp(FileInterface $file, array $image_styles) {
    if (empty($image_styles) || !$this->validateImage($file)) {
      return;
    }

    /* @var \Drupal\Core\Image\Image $image */
    /* @var \Drupal\image\Entity\ImageStyle $style */

    // Create image derivatives if they not already exists.
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
  public function initialWarmUp(FileInterface $file) {
    $initialImageStyles = $this->config->get('initial_image_styles');
    if (!empty($initialImageStyles)) {
      $this->doWarmUp($file, array_keys($initialImageStyles));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateImage(FileInterface $file) {
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
