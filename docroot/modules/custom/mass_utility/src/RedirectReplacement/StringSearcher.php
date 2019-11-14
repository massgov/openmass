<?php

namespace Drupal\mass_utility\RedirectReplacement;

use Drupal\Component\Utility\Html;

/**
 * Replaces instances of strings.
 */
class StringSearcher {

  private $counts = [];
  private $replacements = [];

  private $preferredBase = 'https://www.mass.gov';
  private $urlRegexp;
  private $replaceUrlsInHtmlText = FALSE;

  /**
   * Constructor.
   *
   * @param array $prefixes
   *   An array containing URL prefixes.
   * @param array $replacements
   *   An associative array of find/replace pairs.
   * @param bool $replaceUrlsInHtmlText
   *   A flag indicating whether to search or replace URLs in HTML `text` nodes.
   */
  public function __construct(array $prefixes = [], array $replacements = [], bool $replaceUrlsInHtmlText = FALSE) {
    $this->setPrefixes($prefixes);
    $this->setReplacements($replacements);
    $this->replaceUrlsInHtmlText = $replaceUrlsInHtmlText;
  }

  /**
   * Reset counts on clone.
   */
  public function __clone() {
    $this->counts = array_fill_keys(array_keys($this->replacements), 0);
  }

  /**
   * Returns counts for the # of times replacements were found.
   *
   * @return array
   *   An associative array of counts, keyed on source string.
   */
  public function getCounts(): array {
    return array_filter($this->counts);
  }

  /**
   * Set the replacement paths we will look for when doing string replacement.
   *
   * @param array $replacements
   *   An associative array of relative replacement paths, keyed on source.
   */
  public function setReplacements(array $replacements) {
    $this->replacements = $replacements;
    $this->counts = array_fill_keys(array_keys($this->replacements), 0);
  }

  /**
   * Set the prefixes we're going to use for matching.
   *
   * @param array $prefixes
   *   An array of prefixes (eg: ['https://www.mass.gov']).
   */
  public function setPrefixes(array $prefixes) {
    $baseRegexps = array_map(function ($prefix) {
      return preg_quote($prefix, '/');
    }, $prefixes);
    $base = '(?<base>(?:' . implode(')|(?:', $baseRegexps) . '))';
    $url = "(?<url>$base(?<rel>.*?))";

    // Match any URL, where the URL:
    // * Is at the start of a string, preceded by a space, or preceded by >.
    // * Matches one of our bases.
    // Matches capture the rest of the string, up to the next whitespace, open
    // HTML tag (<), or the end of the string, whichever comes first.
    $this->urlRegexp = "/(?<=^|[ <])$url(?=$|\s|<)/i";
  }

  /**
   * Search for URL occurences in a given plain text string.
   *
   * @param string $string
   *   The string to search in.
   *
   * @return array
   *   An array of discovered URLs.
   */
  public function searchText(string $string) {
    $collected = [];
    if (preg_match_all($this->urlRegexp, $string, $matches)) {
      $collected = array_merge($collected, $matches['rel']);
    }
    return array_map([$this, 'trim'], $collected);
  }

  /**
   * Replace URL occurrences in a given plain text string.
   *
   * @param string $string
   *   The string to replace in.
   *
   * @return string
   *   The replacement string.
   */
  public function replaceText(string $string) {
    return preg_replace_callback($this->urlRegexp, [$this, 'replaceRelative'], $string);
  }

  /**
   * Search for URL occurences in a given HTML string.
   *
   * @param string $string
   *   The string to search in.
   *
   * @return array
   *   An array of discovered URLs.
   */
  public function searchHtml(string $string) {
    $dom = self::unserialize($string);
    $xpath = new \DOMXPath($dom);
    $collected = [];

    foreach ($xpath->query('//a[@href]') as $node) {
      $href = $node->getAttribute('href');
      if (preg_match($this->urlRegexp, $href, $matches) && !empty($matches['rel'])) {
        $collected[] = $matches['rel'];
      }
    }
    if ($this->replaceUrlsInHtmlText) {
      foreach ($xpath->query('//text()') as $node) {
        $text = $node->wholeText;
        if (preg_match_all($this->urlRegexp, $text, $matches)) {
          $collected = array_merge($collected, $matches['rel']);
        }
      }
    }

    return array_map([$this, 'trim'], $collected);
  }

  /**
   * Replace URL occurrences in a given plain text string.
   *
   * @param string $string
   *   The string to replace in.
   *
   * @return string
   *   The replacement string.
   */
  public function replaceHtml(string $string) {
    $dom = self::unserialize($string);
    $xpath = new \DOMXPath($dom);
    $update = FALSE;

    foreach ($xpath->query('//a[@href]') as $node) {
      $href = $node->getAttribute('href');
      $fixed = preg_replace_callback($this->urlRegexp, [$this, 'replaceRelative'], $href);
      if ($href !== $fixed) {
        $update = TRUE;
        $node->setAttribute('href', $fixed);
      }
    }
    if ($this->replaceUrlsInHtmlText) {
      /** @var \DOMText $node */
      foreach ($xpath->query('//text()') as $node) {
        $text = $node->textContent;
        $fixed = preg_replace_callback($this->urlRegexp, [$this, 'replaceAbsolute'], $text);
        if ($fixed !== $text) {
          $update = TRUE;
          $node->textContent = $fixed;
        }
      }
    }

    return $update ? self::serialize($dom) : $string;
  }

  /**
   * Replaces a Drupal URI string.
   *
   * This method is the same as the string transformation, except that we
   * convert entity paths to the entity scheme, and relative paths to the
   * internal scheme.
   *
   * @param string $string
   *   The string to run the replacement on.
   *
   * @return string
   *   The formulated URI string.
   */
  public function replaceUri(string $string) {
    $replacement = $this->replaceText($string);
    if ($replacement !== $string) {
      if (preg_match('/^\/(node|media)\/(\d+)/', $replacement, $matches)) {
        return sprintf('entity:%s/%d', $matches[1], $matches[2]);
      }
      // All other internal links should have `internal:` prefixed.
      if (strpos($replacement, '/') === 0) {
        return sprintf('internal:%s', $replacement);
      }
    }

    return $replacement;
  }

  /**
   * Clean up a URL string by removing stuff that we don't care about.
   *
   * Removes fragments and _ga* query parameters from URLs.  We'll keep these
   * things when we replace the URL, but we need a cleaned up version in order
   * to match with a replacement string.
   *
   * @param string $url
   *   The URL string.
   *
   * @return string
   *   The trimmed URL string.
   */
  private function trim(string $url) {
    return preg_replace('/(#.*|\?_ga.*)(?=$|\s|<)/', '', $url);
  }

  /**
   * Replace URL strings with their relative equivalents, if possible.
   *
   * @internal
   */
  public function replaceRelative($matches) {
    $url = $matches['url'];
    // Extract the rel and trim it.
    $rel = $this->trim($matches['rel']);
    if (isset($this->replacements[$rel])) {
      $this->counts[$rel]++;
      $url = strtr($matches['rel'], $this->replacements);
    }
    return $url;
  }

  /**
   * Replace URL strings with their absolute equivalents, if possible.
   *
   * @internal
   */
  public function replaceAbsolute($matches) {
    $url = $matches['url'];
    // Extract and trim rel.
    $rel = $this->trim($matches['rel']);
    if (isset($this->replacements[$rel])) {
      $this->counts[$rel]++;
      // Return the new absolute path, which is the replacement path + whatever
      // is leftover from replacing the relative path.
      $url = $this->preferredBase . $this->replacements[$rel] . substr($matches['rel'], strlen($rel));
    }
    return $url;
  }

  /**
   * Convert a string to a DOM object.
   *
   * @param string $html
   *   The HTML content, in string form.
   *
   * @return \DOMDocument
   *   The HTML content, in DOMDocument form.
   */
  private static function unserialize(string $html) {
    // Perform pre-loading normalization on the string.
    $html = strtr($html, [
      // Normalize newlines (convert to \n) to avoid being converted to HTML
      // entities.
      "\r\n" => "\n",
      "\r" => "\n",
      // Prevent nonbreaking spaces from being swallowed by the parser. Convert
      // them to a temporary placeholder we can replace on the way out.
      '&nbsp;' => '_nbsp;',
      // Protect the HTML "lang" property from being converted to "xml:lang".
      // We convert it to a data property until it is converted back to string.
      'lang="' => 'data-temp-lang="',
    ]);
    return Html::load($html);
  }

  /**
   * Convert a DOM object to a string.
   *
   * @param \DOMDocument $dom
   *   The HTML content, in DOMDocument form.
   *
   * @return string
   *   The HTML content, in string form.
   */
  private function serialize(\DOMDocument $dom) {
    // Undo our normalizations from before.
    return strtr(Html::serialize($dom), [
      // Restore nonbreaking spaces.
      '_nbsp;' => '&nbsp;',
      // Restore lang attributes.
      'data-temp-lang="' => 'lang="',
    ]);
  }

}
