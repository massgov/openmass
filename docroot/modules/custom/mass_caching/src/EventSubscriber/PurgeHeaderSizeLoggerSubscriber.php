<?php

namespace Drupal\mass_caching\EventSubscriber;

use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Logs oversized Acquia purge tag headers.
 */
final class PurgeHeaderSizeLoggerSubscriber implements EventSubscriberInterface {

  /**
   * The purge tags header name.
   */
  private const HEADER_NAME = 'X-Acquia-Purge-Tags';

  /**
   * Logs when the header line reaches or exceeds this many bytes.
   */
  private const HEADER_SIZE_THRESHOLD = 8192;

  /**
   * The logger channel.
   */
  private LoggerInterface $logger;

  /**
   * Constructs a PurgeHeaderSizeLoggerSubscriber.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Logs content context when the purge header reaches the size threshold.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    if (!$response->headers->has(self::HEADER_NAME)) {
      return;
    }

    $header_value = (string) $response->headers->get(self::HEADER_NAME, '');
    $header_line_size = strlen(self::HEADER_NAME . ': ' . $header_value);
    if ($header_line_size < self::HEADER_SIZE_THRESHOLD) {
      return;
    }

    $request = $event->getRequest();
    $route_name = (string) $request->attributes->get('_route', '');
    $path = $request->getPathInfo();

    $content_type = '';
    $entity_type = '';
    $entity_id = '';

    $node = $request->attributes->get('node');
    if (is_numeric($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load((int) $node);
    }

    if ($node instanceof NodeInterface) {
      $content_type = $node->bundle();
      $entity_type = 'node';
      $entity_id = (string) $node->id();
    }

    $this->logger->warning(
      'Oversized Acquia purge header detected: {bytes} bytes for {header}. route={route} path={path} entity_type={entity_type} content_type={content_type} entity_id={entity_id}',
      [
        'bytes' => $header_line_size,
        'header' => self::HEADER_NAME,
        'route' => $route_name ?: 'n/a',
        'path' => $path ?: 'n/a',
        'entity_type' => $entity_type ?: 'n/a',
        'content_type' => $content_type ?: 'n/a',
        'entity_id' => $entity_id ?: 'n/a',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run after acquia_purge has added the final hashed header.
      KernelEvents::RESPONSE => ['onKernelResponse', -1100],
    ];
  }

}
