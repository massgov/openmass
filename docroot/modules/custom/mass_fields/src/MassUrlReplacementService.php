<?php

namespace Drupal\mass_fields;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
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
      $base_host = \Drupal::request()->getSchemeAndHttpHost();
      if (UrlHelper::isExternal($href)) {
        if (!UrlHelper::externalIsLocal($href, $base_host)) {
          continue;
        }
      }

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
          $processed = $this->replaceServiceDetailsLinks($item->value, TRUE);
          if ($processed['changed']) {
            $item->value = $processed['text'];
            $changed = TRUE;
          }
        }
      }
      // Process link fields.
      elseif ($fieldType === 'link') {
        foreach ($field as $item) {
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
   * Replaces 'service-details/[something]' URLs with 'info-details/[something]' in a given text or link.
   *
   * Adds necessary anchor attributes when processing text fields.
   *
   * @param string $text
   *   The text containing potential 'service-details/[something]' URLs.
   * @param bool $isTextField
   *   Whether the text is from a text field (TRUE) or a link field (FALSE).
   *
   * @return array
   *   An associative array containing:
   *   - 'changed' (bool): TRUE if any URLs were replaced, FALSE otherwise.
   *   - 'text' (string): The processed text or link with updated URLs.
   */
  private function replaceServiceDetailsLinks($text, $isTextField = FALSE): array {
    $changed = FALSE;

    // Only process text fields if $isTextField is TRUE
    if ($isTextField) {
      // Parse the text using DOM
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);

      // Find all anchor tags that start with 'service-details' or 'node' href
      foreach ($xpath->query("//a[starts-with(@href, 'service-details/') or starts-with(@href, '/service-details/')]") as $element) {
        // Extract the href attribute
        $href = $element->getAttribute('href');

        // Extract the node ID or path fragment from the href
        if (preg_match('/service-details\/([a-zA-Z0-9-]+)/', $href, $matches)) {
          $serviceDetailFragment = $matches[1];

          // Load the redirect entity and resolve to the new URL
          $redirects = $this->entityTypeManager->getStorage('redirect')->loadByProperties(['redirect_source__path' => 'service-details/' . $serviceDetailFragment]);
          if ($redirect = reset($redirects)) {
            $target = $redirect->getRedirectUrl()->toString();
            if (strpos($target, '/info-details/') !== FALSE) {
              // Extract the alias from the target URL
              $alias = parse_url($target, PHP_URL_PATH);
              // Convert the alias to the internal path (e.g., from '/info-details/...' to '/node/{nid}').
              $internal_path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);

              if (preg_match('/^\/node\/(\d+)$/', $internal_path, $nid_matches)) {
                $nid = $nid_matches[1];
                if ($node = $this->entityTypeManager->getStorage('node')->load($nid)) {
                  // Set data-entity attributes on the <a> tag
                  $element->setAttribute('data-entity-uuid', $node->uuid());
                  $element->setAttribute('data-entity-substitution', 'canonical');
                  $element->setAttribute('data-entity-type', 'node');

                  // Update the href with the new alias
                  $href_url = parse_url($href);
                  $anchor = empty($href_url['fragment']) ? '' : '#' . $href_url['fragment'];
                  $query = empty($href_url['query']) ? '' : '?' . $href_url['query'];
                  $element->setAttribute('href', $alias . $query . $anchor);

                  // Mark as changed
                  $changed = TRUE;
                }
              }
            }
          }
        }
      }

      // Serialize the DOM back to HTML
      $text = Html::serialize($dom);
    }
    else {
      // Process link fields as URLs, no need to handle <a> tags here.
      if (preg_match_all('/service-details\/([a-zA-Z0-9-]+)/', $text, $matches)) {
        foreach ($matches[1] as $match) {
          // Load the redirect entity.
          $redirects = $this->entityTypeManager->getStorage('redirect')->loadByProperties(['redirect_source__path' => 'service-details/' . $match]);
          if ($redirect = reset($redirects)) {
            $target = $redirect->getRedirectUrl()->toString();
            if (strpos($target, '/info-details/') !== FALSE) {
              // Extract the path starting from '/info-details/'.
              $infoDetailsPath = parse_url($target, PHP_URL_PATH);
              // Replace the URL in the link field
              $text = str_replace('service-details/' . $match, ltrim($infoDetailsPath, '/'), $text);
              $changed = TRUE;
            }
          }
        }
      }
    }

    return ['changed' => $changed, 'text' => $text];
  }

}
