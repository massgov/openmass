<?php

declare(strict_types=1);

namespace Drupal\mass_media;

use Drupal\video_embed_field\Plugin\video_embed_field\Provider\Vimeo;
use Drupal\video_embed_field\Plugin\video_embed_field\Provider\YouTube;

/**
 * Resolves supported remote video URLs without fetching provider metadata.
 */
final class RemoteVideoUrl {

  /**
   * Returns provider, player ID, and embed URL, or NULL for unsupported URLs.
   */
  public static function parse(string $url): ?array {
    $url = trim($url);
    $url = preg_replace('@^(https?://(?:www\.)?youtube\.com)/watch/([\w-]+)(?:\?.*)?$@', '$1/watch?v=$2', $url);
    if ($id = YouTube::getIdFromInput($url)) {
      return ['provider' => 'youtube', 'id' => $id, 'src' => 'https://www.youtube.com/embed/' . $id];
    }
    if ($id = Vimeo::getIdFromInput($url)) {
      $src = 'https://player.vimeo.com/video/' . $id;
      parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
      $hash = $query['h'] ?? NULL;
      if (preg_match('@/' . $id . '/([a-zA-Z0-9]+)$@', parse_url($url, PHP_URL_PATH), $matches)) {
        $hash = $matches[1];
      }
      if (is_string($hash) && preg_match('/^[a-zA-Z0-9]+$/', $hash)) {
        $src .= '?h=' . $hash;
        $id = $src;
      }
      return ['provider' => 'vimeo', 'id' => $id, 'src' => $src];
    }
    return NULL;
  }

}
