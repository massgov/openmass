<?php

declare(strict_types=1);

namespace Drupal\mass_friendly_redirects\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Loads/validates allowed prefixes from the dedicated vocabulary.
 */
final class PrefixManager {
  use StringTranslationTrait;

  public const VOCABULARY = 'friendly_url_prefixes';

  public function __construct(
    private EntityTypeManagerInterface $etm,
  ) {}

  public function getVocabularyMachineName(): string {
    return self::VOCABULARY;
  }

  /**
   * Get prefix options.
   */
  public function getPrefixOptions(): array {
    $storage = $this->etm->getStorage('taxonomy_term');
    $tids = $storage->getQuery()
      ->condition('vid', self::VOCABULARY)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (!$tids) {
      return [];
    }

    /** @var TermInterface[] $terms */
    $terms = $storage->loadMultiple($tids);
    $opts = [];
    foreach ($terms as $term) {
      // We assume validation at term form enforces correct format.
      $label = (string) $term->label();
      $opts[(string) $term->id()] = $label;
    }
    asort($opts);
    return $opts;
  }

  public function isAllowedPrefix(string $candidate): bool {
    return in_array($candidate, array_values($this->getPrefixOptions()), TRUE);
  }

}
