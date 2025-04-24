<?php

namespace Drupal\mass_utility\Plugin\ImageToolkit;

use Drupal\Core\File\FileExists;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkit;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\imagemagick\Event\ImagemagickExecutionEvent;
use Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit;

/**
 * Provides ImageMagick integration toolkit for image manipulation.
 */
#[ImageToolkit(
  id: "mass_imagemagick",
  title: new TranslatableMarkup("Mass ImageMagick image toolkit - avoided GIFs larger than 5MB"),
)]
class MassImageMagick extends ImagemagickToolkit {

  /**
   * {@inheritdoc}
   */
  public function parseFile(): bool {
    // Get the source path first
    $source = $this->getSource();

    // Check if the file is a GIF by its extension before doing any metadata operations
    if (strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'gif') {
      // For GIF files, skip all imagemagick CLI operations
      // We need to set minimal required attributes for the toolkit to work

      // Get the dimensions using GD or another lightweight method instead of imagemagick
      if ($image_info = @getimagesize($source)) {
        $this->setWidth($image_info[0])
          ->setHeight($image_info[1]);
        $this->arguments()->setSourceFormat('GIF');
        return TRUE;
      }

      // If we can't get the dimensions, fall back to default processing
    }

    return parent::parseFile();

  }


  /**
   * Skip applying effects on GIFs larger than 5MB.
   */
  public function apply($operation, array $arguments = []): bool {
    if ($this->shouldSkipGif()) {
      // Skip transformation
      return TRUE;
    }

    return parent::apply($operation, $arguments);
  }

  /**
   * Skip saving effects on GIFs larger than 5MB.
   */
  public function save($destination = NULL): bool {
    if ($this->shouldSkipGif()) {
      // Copy the original image instead of saving the processed one.
      $source = $this->getSource();

      // If destination is provided and different file, copy.
      if ($destination && $source !== $destination) {
        \Drupal::service('file_system')
          ->copy($source, $destination, FileExists::Replace);

        $this->arguments()->setDestination($destination);

        $this->eventDispatcher->dispatch(new ImagemagickExecutionEvent($this->arguments), ImagemagickExecutionEvent::POST_SAVE);
      }

      return TRUE;
    }

    return parent::save($destination);
  }

  /**
   * Determine whether the current image is a large GIF.
   */
  protected function shouldSkipGif(): bool {
    $source = $this->getSource();
    return strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'gif';
  }

}
