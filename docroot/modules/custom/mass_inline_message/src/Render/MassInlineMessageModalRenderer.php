<?php

namespace Drupal\mass_inline_message\Render;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Render\MainContent\ModalRenderer;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\mass_inline_message\Ajax\OpenMassInlineMessageModalCommand;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main content renderer for the Message box Ajax modal.
 */
class MassInlineMessageModalRenderer extends ModalRenderer {

  /**
   * {@inheritdoc}
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match) {
    $response = new AjaxResponse();

    $content = $this->renderer->renderRoot($main_content);

    $main_content['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $response->setAttachments($main_content['#attached']);

    $title = $this->getTitleAsStringable($main_content, $request, $route_match);
    $options = $this->getDialogOptions($request);

    $response->addCommand(new OpenMassInlineMessageModalCommand($title, $content, $options));
    return $response;
  }

}
