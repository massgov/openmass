<?php

namespace Drupal\mass_fields;

use Drupal\Core\Field\FieldItemListInterface;

trait MassTranslationsTrait {

  public function getEnglishVersion(): ?FieldItemListInterface {
    $name = $this->getEnglishFieldName();
    return $this->hasField($name) ? $this->get($name) : NULL;
  }

  public function getEnglishFieldName(): string {
    return self::FIELD_NAME_ENGLISH_VERSION;
  }

}
