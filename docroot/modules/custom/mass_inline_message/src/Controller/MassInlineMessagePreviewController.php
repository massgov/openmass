<?php

namespace Drupal\mass_inline_message\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\mass_inline_message\MassInlineMessageRenderer;
use Drupal\mass_inline_message\MessageBoxBody;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders Message box previews for CKEditor using the same theme as the view.
 */
class MassInlineMessagePreviewController extends ControllerBase {

  public function __construct(
    protected MassInlineMessageRenderer $messageRenderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_inline_message.renderer'),
    );
  }

  /**
   * Returns Mayflower inline-message HTML for CKEditor widget preview.
   */
  public function preview(FilterFormat $filter_format, Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      $payload = [];
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $type = (string) ($payload['type'] ?? 'info');
    if (!in_array($type, ['info', 'warning'], TRUE)) {
      $type = 'info';
    }

    $body_raw = trim((string) ($payload['body'] ?? ''));
    $body_for_render = NULL;
    if ($body_raw !== '') {
      $filtered_body = check_markup(
        MessageBoxBody::normalize($body_raw),
        $filter_format->id()
      );
      if (MessageBoxBody::hasRenderableContent($filtered_body)) {
        $body_for_render = $filtered_body;
      }
    }

    return new JsonResponse([
      'html' => $this->messageRenderer->renderHtml($type, $title, $body_for_render, TRUE),
    ]);
  }

}
