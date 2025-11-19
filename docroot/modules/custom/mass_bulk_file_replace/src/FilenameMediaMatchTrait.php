<?php

namespace Drupal\mass_bulk_file_replace;

trait FilenameMediaMatchTrait {

  /**
   * Remove auto-suffix before the media token.
   */
  protected static function normalizeForId(string $filename): string {
    return preg_replace('/_\d+(?=_DO_NOT_CHANGE_THIS_MEDIA_ID_\d+)/i', '', $filename);
  }

  /**
   * Extract the Media ID from a filename, or NULL if none.
   */
  protected static function extractMediaId(string $filename): ?int {
    $normalized = static::normalizeForId($filename);
    if (preg_match('/DO_NOT_CHANGE_THIS_MEDIA_ID_(\d+)/i', $normalized, $m)) {
      return (int) $m[1];
    }
    return NULL;
  }

  /**
   * Get the filename as authors should see it (without token/suffix).
   */
  protected static function getDisplayFilename(string $filename): string {
    $normalized = static::normalizeForId($filename);
    return preg_replace('/_?DO_NOT_CHANGE_THIS_MEDIA_ID_\d+/i', '', $normalized);
  }

  /**
   * Determine whether uploaded vs existing filenames are a "safe" match.
   */
  protected static function isSafeFilenameMatch(string $uploadedFilename, string $existingFilename): bool {
    // Strip token & normalize to lowercase for comparison.
    $uploaded_norm = mb_strtolower(
      preg_replace('/_?DO_NOT_CHANGE_THIS_MEDIA_ID_\d+/i', '', static::normalizeForId($uploadedFilename))
    );
    $existing_norm = mb_strtolower($existingFilename);

    if ($uploaded_norm === $existing_norm) {
      return TRUE;
    }

    $uploaded_base = pathinfo($uploaded_norm, PATHINFO_FILENAME);
    $existing_base = pathinfo($existing_norm, PATHINFO_FILENAME);

    return $uploaded_base !== '' &&
      $existing_base !== '' &&
      str_starts_with($uploaded_base, $existing_base);
  }

}
