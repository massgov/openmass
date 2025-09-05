<?php

namespace Drupal\emoji_validation\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exception subscriber for emoji validation errors.
 *
 * This handles both form validation errors and entity-level validation errors
 * to ensure user-friendly error messages instead of critical errors.
 */
class EmojiValidationExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new EmojiValidationExceptionSubscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', 50];
    return $events;
  }

  /**
   * Handles emoji validation exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();

    // Check if this is our emoji validation exception
    if ($exception instanceof EntityStorageException &&
        strpos($exception->getMessage(), 'emoji icons') !== FALSE) {

      // The error message is already set by the entity_presave hook
      // Just redirect back to the same page
      $request = $event->getRequest();
      $response = new RedirectResponse($request->getUri());
      $event->setResponse($response);
    }
  }

}
