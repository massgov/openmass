<?php

namespace Drupal\mass_fields;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\Media;

/**
 * Service for handling URL replacements in text.
 */
class MassUrlReplacementService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new UrlReplacementService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Processes text to replace specific URLs.
   *
   * @param string $text
   *   The original text.
   *
   * @return array
   *   The processed text.
   */
  public function processText($text) {
    $document = Html::load($text);
    $xpath = new \DOMXPath($document);

    // Selects all <a> elements with 'href' attribute
    // containing 'media/' or 'sites/default/files/documents/'
    $xpathQuery = "//a[contains(@href, 'media/') or contains(@href, 'sites/default/files/documents/') or contains(@href, 'files/documents/') or (contains(@href, 'files/') and not(contains(@href, 'files/documents/')))]";
    $anchors = $xpath->query($xpathQuery);

    $changed = FALSE;
    foreach ($anchors as $anchor) {
      // Extract the 'href' attribute value
      $href = $anchor->getAttribute('href');

      // Check for 'media/[id]' or 'media/[id]/download' pattern and extract ID
      if (preg_match('/media\/([0-9]+)(\/download)?(\?.*)?$/', $href, $matches)) {
        // Extracted media ID
        $mediaId = $matches[1];

        // Check if '/download' part is present
        $downloadPart = $matches[2] ?? '';

        // Capture any existing query string
        $queryString = $matches[3] ?? '';

        // Load the media entity by ID
        $mediaEntity = $this->entityTypeManager->getStorage('media')->load($mediaId);
        if ($mediaEntity) {
          // Replace the href with a media URL
          $mediaUrl = $mediaEntity->toUrl()->toString();

          // Construct the new URL, preserving '/download'
          // if it was part of the original URL
          $newUrl = $mediaUrl . $downloadPart . $queryString;
          $anchor->setAttribute('href', $newUrl);
          $changed = TRUE;
        }
      }
      // Logic for 'sites/default/files/documents/[dynamic path]'
      elseif (preg_match('/sites\/default\/files\/documents\/(.+)/', $href, $matches)) {
        $filePath = urldecode('public://documents/' . $matches[1]);
        $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $filePath]);
        $file = reset($files);

        if ($file) {
          // Check if there's a media entity referencing this file
          $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['field_upload_file' => $file->id()]);
          $mediaEntity = reset($media);

          if ($mediaEntity) {
            // Replace the href with a media URL.
            // Note: We always want to concat download string to the URL.
            $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
        }
      }
      elseif (preg_match('/files\/documents\/(.+)/', $href, $matches)) {
        $filePath = urldecode('public://documents/' . $matches[1]);
        $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $filePath]);
        $file = reset($files);

        if ($file) {
          // Check if there's a media entity referencing this file
          $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['field_upload_file' => $file->id()]);
          $mediaEntity = reset($media);

          if ($mediaEntity) {
            // Replace the href with a media URL.
            // Note: We always want to concat download string to the URL.
            $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
        }
      }
      // Logic for 'files/[dynamic path]' excluding 'files/documents/'
      elseif (preg_match('/files\/(?!documents\/)(.+)/', $href, $matches)) {
        $filePath = urldecode('public://' . $matches[1]);
        $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $filePath]);
        $file = reset($files);

        if ($file) {
          // Check if there's a media entity referencing this file
          $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['field_upload_file' => $file->id()]);
          $mediaEntity = reset($media);

          if ($mediaEntity) {
            // Replace the href with a media URL.
            // Note: We always want to concat download string to the URL.
            $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
        }
      }
    }
    return [
      'changed' => $changed,
      'text' => HTML::serialize($document),
    ];
  }

}
