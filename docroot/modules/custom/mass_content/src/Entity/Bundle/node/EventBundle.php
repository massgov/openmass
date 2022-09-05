<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * A bundle class for node entities.
 */
class EventBundle extends NodeBundle {

  /**
   * Get meeting type.
   */
  public function getMeetingType(): string {
    return $this->get('field_event_type_list')->getString();
  }

  /**
   * Is event a public meeting.
   */
  public function isPublicMeeting(): bool {
    return $this->getMeetingType() == 'public_meeting';
  }

  /**
   * Is event a public hearing.
   */
  public function isPublicHearing(): bool {
    return $this->getMeetingType() == 'public_hearing';

  }

  /**
   * Get address type.
   */
  public function getAddressType(): FieldItemListInterface {
    return $this->get('field_event_address_type');
  }

}
