<?php

declare(strict_types=1);

namespace Drupal\mass_content\Entity\Bundle\paragraph;

use Drupal\Core\Field\FieldItemListInterface;

final class ListItemDocumentsBundle extends ParagraphBundle {

  public function getManualDescription(): FieldItemListInterface {
    return $this->get('field_listitemdoc_desc_manual');
  }

}
