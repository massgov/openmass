<?php

namespace Drupal\mass_feedback_loop\Controller;

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

}
