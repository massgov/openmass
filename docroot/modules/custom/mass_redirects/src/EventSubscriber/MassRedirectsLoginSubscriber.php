<?php

namespace Drupal\mass_redirects\EventSubscriber;

use Drupal\r4032login\EventSubscriber\R4032LoginSubscriber;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class MassRedirectsLoginSubscriber extends R4032LoginSubscriber {

  public function on403(ExceptionEvent $event): void {
    // Limit redirect to desired domains.
    $allowed = ['edit.mass.gov', 'mass.local'];
    if (in_array($event->getRequest()->getHost(), $allowed)) {
      parent::on403($event);
    }
  }

}
