<?php

namespace Drupal\mass_feedback_loop\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Driver\Exception\Exception;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Class MassFeedbackCsvDownloadController.
 *
 * @package Drupal\mass_feedback_loop\Controller
 */
class MassFeedbackCsvDownloadController extends ControllerBase {

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * MassFeedbackCsvDownloadController constructor.
   *
   * @param \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher $content_fetcher
   *   Feedback content fetcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack that controls the lifecycle of requests.
   */
  public function __construct(MassFeedbackLoopContentFetcher $content_fetcher, RequestStack $requestStack) {
    $this->contentFetcher = $content_fetcher;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('mass_feedback_loop.content_fetcher'),
        $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function download() {
    $query = \Drupal::request()->query;
    $params = $query->all();
    $feedback_api_params = $this->contentFetcher->formatQueryParams($params);

    // We ensure that the file_type parameter is set to csv.
    $feedback_api_params['file_type'] = 'csv';

    try {
      $fileContent = $this->contentFetcher->fetchFeedback($feedback_api_params);
      // The API fetch and our wrapper around it at this point returns a CSV file guzzle stream
      // or an array with no records in it, so we check on that and generate a response accordingly.
      if (is_array($fileContent) && isset($fileContent['total']) && $fileContent['total'] == 0) {
        $url = Url::fromRoute('mass_feedback_loop.mass_feedback_loop_author_interface_form', [], ['query' => $params]);
        $feedback_page_url = $url->toString();
        return [
          '#type' => 'markup',
          '#markup' => "No feedback data to export. <a href='$feedback_page_url'>Alter feedback search filters and try again</a>.",
        ];
      }
      else {
        $response = new Response($fileContent);

        $formatted_timestamp = \Drupal::service('date.formatter')->format(
            \Drupal::time()->getCurrentTime(), 'custom', 'Ymd_H\hi\ms\s'
        );
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT, "filtered-feedback-export-$formatted_timestamp.csv"
        );
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
      }

    }
    catch (Exception $e) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Something went wrong and the csv export of filtered feedback did not work.'),
      ];
    }

  }

}
