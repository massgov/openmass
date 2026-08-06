<?php

namespace Drupal\mass_ai_editorial;

/**
 * Splits canonical rendered text into embedding-sized chunks.
 */
class TextChunker {

  private const DEFAULT_MAX_WORDS = 650;
  private const DEFAULT_OVERLAP_WORDS = 80;

  /**
   * Chunks text with light overlap so related paragraphs keep context.
   *
   * @return array<int, array{delta:int, heading:string|null, text:string, hash:string, token_estimate:int}>
   *   Chunk metadata ready for persistence.
   */
  public function chunk(string $text, int $max_words = self::DEFAULT_MAX_WORDS, int $overlap_words = self::DEFAULT_OVERLAP_WORDS): array {
    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
      return [];
    }

    $chunks = [];
    $offset = 0;
    $delta = 0;
    $count = count($words);
    $step = max(1, $max_words - $overlap_words);

    while ($offset < $count) {
      $slice = array_slice($words, $offset, $max_words);
      $chunk_text = trim(implode(' ', $slice));
      $chunks[] = [
        'delta' => $delta,
        'heading' => $this->guessHeading($chunk_text),
        'text' => $chunk_text,
        'hash' => hash('sha256', $chunk_text),
        'token_estimate' => (int) ceil(count($slice) * 1.35),
      ];

      $delta++;
      $offset += $step;
    }

    return $chunks;
  }

  /**
   * Uses the first short line-like phrase as lightweight chunk context.
   */
  private function guessHeading(string $text): ?string {
    $first_sentence = strtok($text, ".\n");
    if ($first_sentence === FALSE) {
      return NULL;
    }

    $first_sentence = trim($first_sentence);
    if ($first_sentence === '' || str_word_count($first_sentence) > 14) {
      return NULL;
    }

    return mb_substr($first_sentence, 0, 512);
  }

}

