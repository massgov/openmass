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
            if (isset($url['query'])) {
              $mediaUrl .= '?' . $url['query'];
            }
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
          else {
            // We cover the case when the file was used in revisions.
            $mediaQuery = $this->entityTypeManager->getStorage('media')->getQuery()
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

            $mediaQuery = $this->entityTypeManager->getStorage('media')->getQuery()
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
                if (isset($url['query'])) {
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
            if (isset($url['query'])) {
              $mediaUrl .= '?' . $url['query'];
            }
            $anchor->setAttribute('href', $mediaUrl);
            $changed = TRUE;
          }
          else {
            // We cover the case when the file was used in revisions.
            $mediaQuery = $this->entityTypeManager->getStorage('media')->getQuery()
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
                if (isset($url['query'])) {
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
          if (isset($url['query'])) {
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
          if (isset($url['query'])) {
            $mediaUrl .= '?' . $url['query'];
          }
          $link = $mediaUrl;
          $changed = TRUE;
        }
        else {
          // We cover the case when the file was used in revisions.
          $mediaQuery = $this->entityTypeManager->getStorage('media')->getQuery()
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
              if (isset($url['query'])) {
                $mediaUrl .= '?' . $url['query'];
              }
              $link = $mediaUrl;
              $changed = TRUE;
            }
          }
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
          if (isset($url['query'])) {
            $mediaUrl .= '?' . $url['query'];
          }
          $link = $mediaUrl;
          $changed = TRUE;
        }
        else {
          // We cover the case when the file was used in revisions.
          $mediaQuery = $this->entityTypeManager->getStorage('media')->getQuery()
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
              if (isset($url['query'])) {
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

  /**
   * Processes 'service-details/[something]' links in both text and link fields within an entity.
   *
   * This method scans through all fields of a given entity, identifying text fields and link fields
   * that may contain 'service-details/[something]' URLs. It then replaces these URLs with the appropriate
   * 'info-details/[something]' URLs, based on the redirect information. If any changes are made, the
   * entity is marked as changed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being processed.
   *
   * @return bool
   *   TRUE if the entity was changed, FALSE otherwise.
   */
  public function processServiceDetailsLink($entity): bool {
    $changed = FALSE;

    // Process text fields.
    foreach ($entity->getFields() as $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      if (in_array($fieldType, ['text_long', 'text_with_summary', 'string_long'])) {
        foreach ($field as $item) {
          $processed = $this->replaceServiceDetailsLinks($item->value);
          if ($processed['changed']) {
            $item->value = $processed['text'];
            $changed = TRUE;
          }
        }
      }
      // Process link fields.
      elseif ($fieldType === 'link') {
        foreach ($field as  $item) {
          if ($item->uri && strpos($item->uri, 'internal:/service-details/') === 0) {
            $url = substr($item->uri, strlen('internal:/'));
            $processed = $this->replaceServiceDetailsLinks($url);
            if ($processed['changed']) {
              $item->uri = 'internal:/' . $processed['text'];
              $changed = TRUE;
            }
          }
        }
      }
    }

    return $changed;
  }

  /**
   * Replaces 'service-details/[something]' URLs with 'info-details/[something]' in a given text.
   *
   * This helper method scans the provided text for any 'service-details/[something]' URLs. If such a URL
   * is found, it checks if a redirect exists for this URL that points to an 'info-details/[something]' URL.
   * If a matching redirect is found, the URL in the text is replaced, and the redirect is optionally deleted.
   *
   * @param string $text
   *   The text containing potential 'service-details/[something]' URLs.
   *
   * @return array
   *   An associative array containing:
   *   - 'changed' (bool): TRUE if any URLs were replaced, FALSE otherwise.
   *   - 'text' (string): The processed text with updated URLs.
   */
  private function replaceServiceDetailsLinks($text) {
    $changed = FALSE;

    // Match all 'service-details/[something]' patterns.
    if (preg_match_all('/service-details\/([a-zA-Z0-9-]+)/', $text, $matches)) {
      foreach ($matches[1] as $match) {
        // Load the redirect entity.
        $redirects = $this->entityTypeManager->getStorage('redirect')->loadByProperties(['redirect_source__path' => 'service-details/' . $match]);
        if ($redirect = reset($redirects)) {
          $target = $redirect->getRedirectUrl()->toString();
          if (strpos($target, '/info-details/') !== FALSE) {
            // Extract the path starting from '/info-details/'.
            $infoDetailsPath = parse_url($target, PHP_URL_PATH);

            // Replace the link in the text.
            $text = str_replace('service-details/' . $match, ltrim($infoDetailsPath, '/'), $text);
            $changed = TRUE;
            // Optionally delete the redirect.
            // $redirect->delete();
          }
        }
      }
    }

    return ['changed' => $changed, 'text' => $text];
  }

}
