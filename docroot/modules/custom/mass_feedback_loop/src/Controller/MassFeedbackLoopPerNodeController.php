<?php

namespace Drupal\mass_feedback_loop\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;

/**
 * Class MassFeedbackLoopPerNodeController.
 *
 * @package Drupal\mass_feedback_loop\Controller
 */
class MassFeedbackLoopPerNodeController extends ControllerBase {

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * MassFeedbackLoopPerNodeController constructor.
   *
   * @param \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher $content_fetcher
   *   Feedback content fetcher service.
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
   * {@inheritdoc}
   */
  public function build(NodeInterface $node, Request $request) {
    $feedbackRequestOptions = [
      "node_id" => [$node->id()],
    ];
    $page = $request->query->get('page');
    if (!empty($page)) {
      $feedbackRequestOptions['page'] = $page;
    }
    $response = $this->contentFetcher->fetchFeedback($feedbackRequestOptions);

    $output = [
      '#type' => 'container',
      '#prefix' => '<div id="table-wrapper">',
      '#suffix' => '</div>',
    ];
    $fields = [
      'submit_date',
      'info_found',
      'text',
      'requested_response',
    ];
    $output['feedback_table'] = $this->contentFetcher->buildFeedbackTable($response['results'], [], $response['is_watching_content'], $fields);
    $output['pager'] = $this->contentFetcher->buildPager($response['total'], $response['per_page']);
    $output['feedback_table']['#attached']['library'][] = 'mass_feedback_loop/mass-feedback-author-interface';

    // Adds csv export to the table.
    if (isset($response['total']) && is_numeric($response['total']) && $response['total'] > 0) {
      // Create and attach the link to download CSV export.
      $feedback_api_params = [
        'node_id' => $node->id(),
      ];
      $feedback_api_csv_download_params = $feedback_api_params;
      foreach ($feedback_api_csv_download_params as $key => $value) {
        if (is_array($value)) {
          $feedback_api_csv_download_params[$key] = implode(",", $value);
        }
      }
      $csv_download_url = Url::fromRoute('mass_feedback_loop.mass_feedback_csv_download', [], ['query' => $feedback_api_csv_download_params]);
      $csv_download_uri = $csv_download_url->toString();
      $output['csv_export'] = [
              '#type' => 'markup',
              '#markup' => "
        <div class='csv-export-wrapper'>
            <a href='$csv_download_uri'>
                <span class='feed-icon'></span> Download CSV Export
            </a>
        </div>
      "
      ];
    }

    // Adds sorting information to drupalSettings.
    $output['feedback_table']['#attached']['drupalSettings']['massFeedbackLoop']['sortingVariants'] = MassFeedbackLoopContentFetcher::SORTING_VARIANTS;

    return $output;
  }

}
