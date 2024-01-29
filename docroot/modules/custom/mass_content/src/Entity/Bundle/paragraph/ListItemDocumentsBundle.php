<?php

declare(strict_types = 1);

namespace Drupal\mass_content\Entity\Bundle\paragraph;

final class ListItemDocumentsBundle extends ParagraphBundle {

  public function getManualDescription(): string {
    if (!$this->get('field_listitemdoc_desc_manual')->isEmpty()) {
      return $this->field_listitemdoc_desc_manual->value;
    }
    return '';
  }

}
