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
    // Uploaded filenames may include the DO_NOT_CHANGE token and an auto-suffix
    // before it; normalize those away to the author-facing form.
    $uploaded_norm = mb_strtolower(static::getDisplayFilename($uploadedFilename));
    $existing_processed = preg_replace('/_(\d+)(\.[a-zA-Z0-9]+)$/', '$2', $existingFilename);
    $existing_norm = mb_strtolower($existing_processed);

    // Exact match after normalization is always safe.
    if ($uploaded_norm === $existing_norm) {
      return TRUE;
    }

    // Otherwise, allow cases where the uploaded base name starts with
    // the existing base name (e.g., housing-proposal2-ally vs housing-proposal2).
    $uploaded_base = pathinfo($uploaded_norm, PATHINFO_FILENAME);
    $existing_base = pathinfo($existing_norm, PATHINFO_FILENAME);

    return $uploaded_base !== ''
      && $existing_base !== ''
      && str_starts_with($uploaded_base, $existing_base);
  }

}
