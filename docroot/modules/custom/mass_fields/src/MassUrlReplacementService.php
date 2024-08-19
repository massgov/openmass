<?php

namespace Drupal\mass_fields;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
    $xpathQuery = "//a[contains(@href, 'files/') or contains(@href, 'media/') or contains(@href, 'sites/default/files/documents/') or contains(@href, 'files/documents/')]";
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
        $url = parse_url($matches[1]);
        $filePath = urldecode('public://documents/' . $url['path']);
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
            if ($url['query']) {
              $mediaUrl .= '?' . $url['query'];
            }
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
          else {
            // We cover the case when the file was used in revisions.
            $mediaQuery = \Drupal::entityQuery('media')
              ->condition('field_upload_file', $file->id())
              ->accessCheck(FALSE)
              ->allRevisions();
            $result = $mediaQuery->execute();
            if ($result) {
              $mediaId = reset($result);
              $mediaEntity = $this->entityTypeManager->getStorage('media')->load($mediaId);
              if ($mediaEntity) {
                // Replace the href with a media URL.
                // Note: We always want to concat download string to the URL.
                $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
                if ($url['query']) {
                  $mediaUrl .= '?' . $url['query'];
                }
                $anchor->setAttribute('href', $mediaUrl);
                $changed = TRUE;
              }
            }
          }
        }
      }
      elseif (preg_match('/files\/documents\/(.+)/', $href, $matches)) {
        $url = parse_url($matches[1]);
        $filePath = urldecode('public://documents/' . $url['path']);
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
            if ($url['query']) {
              $mediaUrl .= '?' . $url['query'];
            }
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
          else {
            // We cover the case when the file was used in revisions.
            $mediaQuery = \Drupal::entityQuery('media')
              ->condition('field_upload_file', $file->id())
              ->accessCheck(FALSE)
              ->allRevisions();
            $result = $mediaQuery->execute();
            if ($result) {
              $mediaId = reset($result);
              $mediaEntity = $this->entityTypeManager->getStorage('media')->load($mediaId);
              if ($mediaEntity) {
                // Replace the href with a media URL.
                // Note: We always want to concat download string to the URL.
                $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
                if ($url['query']) {
                  $mediaUrl .= '?' . $url['query'];
                }
                $anchor->setAttribute('href', $mediaUrl);
                $changed = TRUE;
              }
            }
          }
        }
      }
      // Logic for 'files/[dynamic path]' excluding 'files/documents/'
      elseif (preg_match('/files\/(?!documents\/)(.+)/', $href, $matches)) {
        $url = parse_url($matches[1]);
        $filePath = urldecode('public://' . $url['path']);
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
            if ($url['query']) {
              $mediaUrl .= '?' . $url['query'];
            }
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
          else {
            // We cover the case when the file was used in revisions.
            $mediaQuery = \Drupal::entityQuery('media')
              ->condition('field_upload_file', $file->id())
              ->accessCheck(FALSE)
              ->allRevisions();
            $result = $mediaQuery->execute();
            if ($result) {
              $mediaId = reset($result);
              $mediaEntity = $this->entityTypeManager->getStorage('media')->load($mediaId);
              if ($mediaEntity) {
                // Replace the href with a media URL.
                // Note: We always want to concat download string to the URL.
                $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
                if ($url['query']) {
                  $mediaUrl .= '?' . $url['query'];
                }
                $anchor->setAttribute('href', $mediaUrl);
                $changed = TRUE;
              }
            }
          }
        }
      }
    }
    return [
      'changed' => $changed,
      'text' => HTML::serialize($document),
    ];
  }

  /**
   * Processes text to replace specific URLs.
   *
   * @param string $link
   *   The original text.
   *
   * @return array
   *   The processed text.
   */
  public function processLink($link) {
    $changed = FALSE;

    // Check for 'media/[id]' or 'media/[id]/download' pattern and extract ID
    if (preg_match('/media\/([0-9]+)(\/download)?(\?.*)?$/', $link, $matches)) {
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
        $link = $newUrl;
        $changed = TRUE;
      }
    }
    // Logic for 'sites/default/files/documents/[dynamic path]'
    elseif (preg_match('/sites\/default\/files\/documents\/(.+)/', $link, $matches)) {
      $url = parse_url($matches[1]);
      $filePath = urldecode('public://documents/' . $url['path']);
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
          if ($url['query']) {
            $mediaUrl .= '?' . $url['query'];
          }
          $link = $mediaUrl;
          $changed = TRUE;
        }
      }
    }
    elseif (preg_match('/files\/documents\/(.+)/', $link, $matches)) {
      $url = parse_url($matches[1]);
      $filePath = urldecode('public://documents/' . $url['path']);
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
          if ($url['query']) {
            $mediaUrl .= '?' . $url['query'];
          }
          $link = $mediaUrl;
          $changed = TRUE;
        }
      }
    }
    // Logic for 'files/[dynamic path]' excluding 'files/documents/'
    elseif (preg_match('/files\/(?!documents\/)(.+)/', $link, $matches)) {
      $url = parse_url($matches[1]);
      $filePath = urldecode('public://' . $url['path']);
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
          if ($url['query']) {
            $mediaUrl .= '?' . $url['query'];
          }
          $link = $mediaUrl;
          $changed = TRUE;
        }
        else {
          // We cover the case when the file was used in revisions.
          $mediaQuery = \Drupal::entityQuery('media')
            ->condition('field_upload_file', $file->id())
            ->accessCheck(FALSE)
            ->allRevisions();
          $result = $mediaQuery->execute();
          if ($result) {
            $mediaId = reset($result);
            $mediaEntity = $this->entityTypeManager->getStorage('media')->load($mediaId);
            if ($mediaEntity) {
              // Replace the href with a media URL.
              // Note: We always want to concat download string to the URL.
              $mediaUrl = $mediaEntity->toUrl()->toString() . '/download';
              if ($url['query']) {
                $mediaUrl .= '?' . $url['query'];
              }
              $link = $mediaUrl;
              $changed = TRUE;
            }
          }
        }
      }
    }
    return [
      'changed' => $changed,
      'link' => $link,
    ];
  }

}
