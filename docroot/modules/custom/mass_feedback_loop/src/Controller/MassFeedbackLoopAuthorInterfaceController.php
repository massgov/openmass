<?php

namespace Drupal\mass_feedback_loop\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MassFeedbackLoopAuthorInterfaceController.
 */
class MassFeedbackLoopAuthorInterfaceController extends ControllerBase {

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassFeedbackLoopContentFetcher $content_fetcher) {
    $this->contentFetcher = $content_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_feedback_loop.content_fetcher')
    );
  }

  /**
   * Callback for opening the modal form.
   *
   * @param string $action
   *   Action to perform: 'add' or 'remove'.
   * @param int $comment_id
   *   Comment ID number.
   * @param int $tag_id
   *   Optional tag ID (used when removing tags).
   * @param int $tag_unique_id
   *   Unique tag ID number (generated on a per-feedback basis).
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response object.
   */
  public function openModalForm($action, $comment_id, $tag_id, $tag_unique_id) {
    // $action parameters is required for AJAX response.
    $response = new AjaxResponse();

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder()->getForm('Drupal\mass_feedback_loop\Form\MassFeedbackLoopTagModalForm', $action, $comment_id, $tag_id, $tag_unique_id);

    // Add AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand(($action == 'add') ? $this->t('Add tag') : $this->t('Remove tag'), $modal_form));

    return $response;
  }

}
