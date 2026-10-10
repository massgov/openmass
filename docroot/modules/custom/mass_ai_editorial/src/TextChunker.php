<?php

namespace Drupal\mass_ai_editorial;

/**
 * Splits canonical rendered text into embedding-sized chunks.
 */
class TextChunker {

  private const DEFAULT_MAX_WORDS = 650;
  private const DEFAULT_OVERLAP_WORDS = 80;
  private const MAX_HEADING_WORDS = 14;
  private const MAX_HEADING_LENGTH = 160;

  /**
   * Chunks text with light overlap so related paragraphs keep context.
   *
   * @return array<int, array{delta:int, heading:string|null, text:string, hash:string, token_estimate:int}>
   *   Chunk metadata ready for persistence.
   */
  public function chunk(string $text, int $max_words = self::DEFAULT_MAX_WORDS, int $overlap_words = self::DEFAULT_OVERLAP_WORDS): array {
    $sections = $this->splitIntoSections($text);
    if (!$sections) {
      return [];
    }

    $chunks = [];
    $current = '';
    $current_heading = NULL;
    $current_words = 0;

    foreach ($sections as $section) {
      $section_words = $this->wordCount($section['text']);
      if ($section_words === 0) {
        continue;
      }

      if ($section_words > $max_words) {
        $this->appendChunk($chunks, $current, $current_heading);
        $current = '';
        $current_heading = NULL;
        $current_words = 0;

        foreach ($this->splitLongSection($section['text'], $section['heading'], $max_words, $overlap_words) as $section_chunk) {
          $this->appendChunk($chunks, $section_chunk['text'], $section_chunk['heading']);
        }
        continue;
      }

      if ($current_words > 0 && ($current_words + $section_words) > $max_words) {
        $previous_chunk = $current;
        $this->appendChunk($chunks, $current, $current_heading);

        $overlap = $this->tailWords($previous_chunk, $overlap_words);
        if ($overlap !== '' && ($this->wordCount($overlap) + $section_words) <= $max_words) {
          $current = $overlap . "\n\n" . $section['text'];
          $current_words = $this->wordCount($current);
        }
        else {
          $current = $section['text'];
          $current_words = $section_words;
        }
        $current_heading = $section['heading'];
        continue;
      }

      $current = $current === '' ? $section['text'] : $current . "\n\n" . $section['text'];
      if ($current_heading === NULL || $current_heading === '') {
        $current_heading = $section['heading'];
      }
      $current_words = $this->wordCount($current);
    }

    $this->appendChunk($chunks, $current, $current_heading);

    return $chunks;
  }

  /**
   * Splits rendered text into heading-led sections when possible.
   *
   * @return array<int, array{heading:string|null, text:string}>
   *   Section text and its detected heading.
   */
  private function splitIntoSections(string $text): array {
    $lines = preg_split('/\R/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    if (!$lines) {
      return [];
    }

    $sections = [];
    $current_lines = [];
    $current_heading = NULL;

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }

      if ($this->isHeadingLine($line)) {
        if ($current_lines) {
          $sections[] = [
            'heading' => $current_heading,
            'text' => implode("\n", $current_lines),
          ];
        }
        $current_lines = [$line];
        $current_heading = $this->headingLabel($line);
        continue;
      }

      $current_lines[] = $line;
    }

    if ($current_lines) {
      $sections[] = [
        'heading' => $current_heading,
        'text' => implode("\n", $current_lines),
      ];
    }

    return $sections;
  }

  /**
   * Identifies short standalone lines that are likely rendered headings.
   */
  private function isHeadingLine(string $line): bool {
    if (preg_match('/^Heading level [1-6]:\s+.+/i', $line)) {
      return TRUE;
    }

    if (str_word_count($line) > self::MAX_HEADING_WORDS || mb_strlen($line) > self::MAX_HEADING_LENGTH) {
      return FALSE;
    }

    if (str_contains($line, '[href:') || str_ends_with($line, '.')) {
      return FALSE;
    }

    if (preg_match('/^(Breadcrumb|Incoming links to this page):/i', $line)) {
      return TRUE;
    }

    return (bool) preg_match('/^[\p{Lu}\p{N}][^.!?]*$/u', $line);
  }

  /**
   * Keeps chunk metadata concise while preserving level markers in text.
   */
  private function headingLabel(string $line): string {
    if (preg_match('/^(Breadcrumb|Incoming links to this page):/i', $line)) {
      return '';
    }

    if (preg_match('/^Heading level [1-6]:\s+(.+)$/i', $line, $matches)) {
      return trim($matches[1]);
    }

    return $line;
  }

  /**
   * Splits an oversized section into overlapping word windows.
   *
   * @return array<int, array{heading:string|null, text:string}>
   *   Section-sized chunk text.
   */
  private function splitLongSection(string $text, ?string $heading, int $max_words, int $overlap_words): array {
    $words = $this->words($text);
    if (!$words) {
      return [];
    }

    $chunks = [];
    $offset = 0;
    $count = count($words);
    $step = max(1, $max_words - $overlap_words);

    while ($offset < $count) {
      if ($offset > 0 && ($count - $offset) <= $overlap_words) {
        break;
      }

      $slice = array_slice($words, $offset, $max_words);
      $chunk_text = trim(implode(' ', $slice));
      $chunks[] = [
        'heading' => $heading,
        'text' => $chunk_text,
      ];
      $offset += $step;
    }

    return $chunks;
  }

  private function appendChunk(array &$chunks, string $text, ?string $heading): void {
    $text = trim($text);
    if ($text === '') {
      return;
    }

    $chunks[] = [
      'delta' => count($chunks),
      'heading' => $heading ?: $this->guessHeading($text),
      'text' => $text,
      'hash' => hash('sha256', $text),
      'token_estimate' => (int) ceil($this->wordCount($text) * 1.35),
    ];
  }

  /**
   * Returns the last N words of a chunk for light boundary overlap.
   */
  private function tailWords(string $text, int $limit): string {
    if ($limit <= 0) {
      return '';
    }

    $words = $this->words($text);
    if (count($words) <= $limit) {
      return trim($text);
    }

    return implode(' ', array_slice($words, -$limit));
  }

  private function wordCount(string $text): int {
    return count($this->words($text));
  }

  /**
   * @return array<int, string>
   *   Words in the provided text.
   */
  private function words(string $text): array {
    return preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
