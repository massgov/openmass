<?php

declare(strict_types=1);

namespace Drupal\mass_content\Entity\Bundle\paragraph;

use Drupal\mayflower\Helper;

final class AddressBundle extends ParagraphBundle {

  public function getDirectionsUrl(): string {
    if (!$this->get('field_contact_directions_link')->isEmpty()) {
      return $this->field_contact_directions_link->uri;
    }
    else {
      return 'https://maps.google.com/?q=' . urlencode(Helper::formatAddress($this->field_address_address));
    }
  }

}
