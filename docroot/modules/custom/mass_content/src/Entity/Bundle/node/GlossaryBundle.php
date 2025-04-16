<?php

namespace Drupal\mass_content\Entity\Bundle\node;

/**
 * A bundle class for node entities.
 */
class GlossaryBundle extends NodeBundle {

  public function getTerms() {
    $values = $this->get('field_terms')->getValue();
    $formatted_terms = array_map([$this, 'formatTerm'], $values);
    $merged = array_merge(...$formatted_terms);
    return $merged;
  }

  protected function formatTerm($term) {
    return [
      strtolower($term['key']) => [
        $this->uuid() => $term['value']
      ]
    ];
  }

  public function getInfo() {
    return [
      'name' => $this->getTitle(),
      'uuid' => $this->uuid(),
      'url' => $this->toUrl()->toString(),
    ];
  }

  public static function mergeGlossaries($glossaries) {
    $terms_by_glossary = array_map(fn($glossary) => $glossary->getTerms(), $glossaries);
    $combined_terms = array_merge_recursive(...$terms_by_glossary);

    return $combined_terms;
  }

}
