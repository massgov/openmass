<?php

namespace Drupal\mass_utility\EntityAlter;

use Drupal\image\Entity\ImageStyle as ImageStyleOriginal;

/**
 * Class ImageStyle.
 *
 * Overrides an ImageStyle class from the "image" module.
 */
class ImageStyle extends ImageStyleOriginal {

  /**
   * Check if an image file is an animated GIF.
   *
   * Taken from
   * https://www.php.net/manual/en/function.imagecreatefromgif.php#104473.
   *
   * @param string $filename
   *   The path to the image.
   *
   * @return bool
   *   Returns true if the image file is an animated gif, false otherwise.
   */
  private function isAnimatedGif(string $filename): bool {
    if (!($fh = @fopen($filename, 'rb'))) {
      return FALSE;
    }
    $count = 0;
    // An animated GIF contains multiple "frames", with each frame having a
    // Header made up of:
    // * a static 4-byte sequence (\x00\x21\xF9\x04)
    // * 4 variable bytes
    // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)
    // We read through the file til we reach the end of the file, or we've found
    // at least 2 frame headers
    while (!feof($fh) && $count < 2) {
      // Read 100kb at a time
      $chunk = fread($fh, 1024 * 100);
      $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
    }

    fclose($fh);
    return $count > 1;
  }

  /**
   * Override standard method.
   *
   * Do not create derivative URI for animated GIF images.
   */
  public function buildUri($uri) {
    $mime = \Drupal::service('file.mime_type.guesser')->guessMimeType($uri);
    // If it is not a GIF - do nothing, default behaviour.
    if ($mime != 'image/gif') {
      return parent::buildUri($uri);
    }

    // Do nothing of file does not exist.
    $path = \Drupal::service('file_system')->realpath($uri);
    if (!file_exists($path)) {
      return parent::buildUri($uri);
    }

    // Check if the GIF is animated.
    // If it is not animated a GIF - do nothing, default behaviour.
    if (!$this->isAnimatedGif($path)) {
      return parent::buildUri($uri);
    }

    // Do not apply image style if it is animated GIF.
    return $uri;
  }

}
