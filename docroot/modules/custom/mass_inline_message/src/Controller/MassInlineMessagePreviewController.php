<?php

namespace Drupal\mass_inline_message\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders Message box previews for CKEditor using the same theme as the view.
 */
class MassInlineMessagePreviewController extends ControllerBase {

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
      $body_for_render = mass_inline_message_normalize_body_html($body_raw);
    }

    return new JsonResponse([
      'html' => mass_inline_message_render_html($type, $title, $body_for_render, TRUE),
    ]);
  }

}
