<?php

namespace Drupal\mass_fields;

use Drupal\Component\Utility\Html;
use Drupal\media\Entity\Media;

/**
 * Service for handling URL replacements in text.
 */
class MassUrlReplacementService {

  /**
   * Processes text to replace specific URLs.
   *
   * @param string $text
   *   The original text.
   *
   * @return string
   *   The processed text.
   */
  public function processText($text) {
    $document = Html::load($text);
    $xpath = new \DOMXPath($document);

    // Selects all <a> elements with 'href' attribute
    // containing 'media/' or 'sites/default/files/documents/'
    $xpathQuery = "//a[contains(@href, 'media/') or contains(@href, 'sites/default/files/documents/')]";
    $anchors = $xpath->query($xpathQuery);

    foreach ($anchors as $anchor) {
      // Extract the 'href' attribute value
      $href = $anchor->getAttribute('href');

      // Check for 'media/[id]' or 'media/[id]/download' pattern and extract ID
      if (preg_match('/media\/([0-9]+)(\/download)?/', $href, $matches)) {
        // Extracted media ID
        $mediaId = $matches[1];

        // Check if '/download' part is present
        $downloadPart = $matches[2] ?? '';
        // Load the media entity by ID
        $mediaEntity = Media::load($mediaId);
        if ($mediaEntity) {
          // Replace the href with a media URL
          $mediaUrl = $mediaEntity->toUrl()->toString();

          // Construct the new URL, preserving '/download'
          // if it was part of the original URL
          $newUrl = $mediaUrl . $downloadPart;
          $anchor->setAttribute('href', $newUrl);
        }
      }
      // Logic for 'sites/default/files/documents/[dynamic path]'
      elseif (preg_match('/sites\/default\/files\/documents\/(.+)/', $href, $matches)) {
        $filePath = urldecode('public://documents/' . $matches[1]);
        $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $filePath]);
        $file = reset($files);

        if ($file) {
          // Check if there's a media entity referencing this file
          $media = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['field_upload_file' => $file->id()]);
          $mediaEntity = reset($media);

          if ($mediaEntity) {
            // Replace the href with a media URL.
            // Note: We always want to concat download string to the URL.
            $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
            $anchor->setAttribute('href', $mediaUrl);
          }
        }
      }
    }
    return HTML::serialize($document);
  }

}
