<?php

namespace Drupal\mass_fields;

use Drupal\Core\Field\FieldItemListInterface;

trait MassTranslationsTrait {

  public function getEnglishVersion(): FieldItemListInterface {
    return $this->get($this->getEnglishFieldName());
  }

  public function getEnglishFieldName(): string {
    return self::FIELD_NAME_ENGLISH_VERSION;
  }

  public function supportsEnglishFieldName(): bool {
    return $this->hasField($this->getEnglishFieldName());
  }

}
