<?php

namespace Drupal\mass_content\Entity\Bundle\node;

/**
 * A bundle class for node entities.
 */
class EventBundle extends NodeBundle {

  public function getMeetingType() {
    return $this->get('field_event_type_list')->getString();
  }

  public function isPublicMeeting() {
    return $this->getMeetingType() == 'public_meeting';
  }

  public function isPublicHearing() {
    return $this->getMeetingType() == 'public_hearing';

  }

  public function getAddressType() {
    return $this->get('field_event_address_type');
  }

}
