<?php

namespace Drupal\mass_map;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\mayflower\Helper;

/**
 * Setup the static map on the location page.
 */
class MapHelper {

  /**
   * Google Map static map signing secret.
   *
   * @var string
   */
  protected $secret;

  /**
   * Google Maps API key (authorized for static and JS APIs)
   *
   * @var string
   */
  private $key;

  /**
   * Legacy Google Map static map signing secret.
   *
   * @var string
   */
  private $legacySecret;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings) {
    $this->key = $settings->get('google_maps_api_key');
    $this->secret = $settings->get('google_maps_signing_secret');
  }

  /**
   * Generate the URL for a static Google Map image.
   *
   * @param object $location
   *   An addressfield address.
   * @param array $options
   *   Any additional options to pass in the query string (eg: size)
   *
   * @return string
   *   The URL.
   */
  public function getStaticMapUrl($location, array $options = []) {
    // Fallback - allow use of the old `client` style auth if the key hasn't
    // been set up yet. This can be removed once all environments are updated
    // to have a key.
    $auth = ['key' => $this->key];
    $url = Url::fromUri('https://maps.googleapis.com/maps/api/staticmap', [
      'query' => $options + [
        'markers' => Helper::formatAddress($location),
        'channel' => 'massgov',
      ] + $auth,
    ]);
    // Fallback - we changed the name of the $setting used for this signing key,
    // but we want to allow the signing to keep working in case environments are
    // not updated.  This logic prefers signing using the new secret, allows the
    // use of the old secret, and falls back to a completely unsigned URL in
    // case the signing secret has not been set up at all.
    if ($this->secret) {
      return $this->signMapUrl((string) $url->toUriString(), $this->secret);
    }
    return $url->toUriString();
  }

  /**
   * Generate the URL to add the Google Maps Javascript API to the page.
   *
   * @param array $libraries
   *   A list of extra Google "libraries" to include.
   * @param string $callback
   *   The javascript callback to invoke when the API is ready.
   *
   * @return string
   *   The URL.
   */
  public function getJavascriptApiUrl(array $libraries, $callback) {
    // Fallback - allow use of the old `client` style auth if the key hasn't
    // been set up yet. This can be removed once all environments are updated
    // to have a key.
    $auth = ['key' => $this->key];
    // We lock API version to 3.36 to prevent bad 404 URLs on IE.
    // @todo Let go of API version locking after root cause of bad URLs is fixed
    // See: https://jira.mass.gov/browse/DP-15828
    // See: https://jira.mass.gov/browse/DP-15844
    $url = Url::fromUri('//maps.googleapis.com/maps/api/js', [
      'query' => [
        'libraries' => implode(',', $libraries),
        'callback' => $callback,
        'channel' => 'massgov',
        'v' => '3.54',
      ] + $auth,
    ]);
    return (string) $url->toUriString();
  }

  /**
   * Builds the URL to take the user to a maps.google.com page.
   *
   * @param object $location
   *   The addressfield location.
   *
   * @return string
   *   The URL.
   */
  public function getMapLinkUrl($location) {
    $url = Url::fromUri('https://maps.google.com', [
      'query' => [
        'q' => Helper::formatAddress($location),
      ],
    ]);
    return (string) $url->toUriString();
  }

  /**
   * Create the url with the key and signature for Google Static map.
   */
  private function signMapUrl($url, $secret) {
    $url = parse_url($url);
    $url_to_sign = $url['path'] . "?" . $url['query'];
    $decoded_key = $this->base64urldecode($secret);
    $signature = hash_hmac('sha1', $url_to_sign, $decoded_key, TRUE);
    $encoded_signature = $this->base64urlencode($signature);
    $original_url = $url['scheme'] . "://" . $url['host'] . $url['path'] . "?" . $url['query'];
    return $original_url . "&signature=" . $encoded_signature;
  }

  /**
   * Decode the key.
   */
  private function base64urlencode($data) {

    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * Encode the key.
   */
  private function base64urldecode($data) {

    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
  }

}
