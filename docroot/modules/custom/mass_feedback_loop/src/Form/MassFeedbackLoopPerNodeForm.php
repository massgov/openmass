<?php

namespace Drupal\mass_feedback_loop\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MassFeedbackLoopPerNodeController.
 *
 * @package Drupal\mass_feedback_loop\Form
 */
class MassFeedbackLoopPerNodeForm extends FormBase {

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * MassFeedbackLoopPerNodeForm constructor.
   *
   * @param \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher $content_fetcher
   *   Feedback content fetcher service.
   */
  public function __construct(MassFeedbackLoopContentFetcher $content_fetcher) {
    $this->contentFetcher = $content_fetcher;
    $this->request = $this->getRequest();
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
  public function getFormId() {
    return 'mass_feedback_loop_per_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $negative_feedback_url = Url::fromRoute('view.pages_with_high_negative_feedback.page_2')->toString();
    $feedback_manager_url = Url::fromRoute('mass_feedback_loop.mass_feedback_loop_author_interface_form')->toString();
    $message = 'Feedback submitted by constituents for this content<p>Also see: <a href="@negative_feedback_url">Pages with high negative feedback</a> and <a href="@feedback_manager_url">Feedback Manager</a> where you can view, filter and sort feedback submissions.</p>';
    $markup = Markup::create($this->t($message, [
      '@negative_feedback_url' => $negative_feedback_url,
      '@feedback_manager_url' => $feedback_manager_url,
    ]));

    return [
      '#markup' => $markup,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $params = $this->getRequest()->query->all();
    $feedback_api_params = $this->contentFetcher->formatQueryParams($params);
    $feedback_api_params['node_id'] = [];
    $feedback_api_params['node_id'][] = $node->id();
    if ($node->hasField('field_migrated_node_id') && !empty($node->get('field_migrated_node_id')->value)) {
      $feedback_api_params['node_id'][] = $node->get('field_migrated_node_id')->value;
    }

    $form = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-wrapper'],
      ],
    ];
    $form['#attached']['library'][] = 'mass_feedback_loop/mass-feedback-author-interface';

    $form['page'] = [
      '#type' => 'hidden',
      // Updates form input with default value, if available.
      '#value' => (!empty($feedback_api_params['page'])) ? $feedback_api_params['page'] : NULL,
    ];

    // Builds 'Flagged inappropriate' input.
    $form['flagged_inappropriate'] = [
      '#type' => 'checkboxes',
      '#options' => ['flagged_inappropriate' => $this->t('Show feedback flagged as low quality')],
      '#title' => $this->t('Filter by feedback quality'),
      '#default_value' => !empty($feedback_api_params['flagged_inappropriate']) ? ['flagged_inappropriate'] : [],
    ];

    $form['form_btn_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'form-btn-wrapper',
      ],
    ];
    $form['form_btn_wrapper']['filter_action'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#button_type' => 'primary',
    ];
    $form['form_btn_wrapper']['reset_action'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#button_type' => 'primary',
    ];

    $fields = [
      'submit_date',
      'info_found',
      'text',
      'requested_response',
    ];

    $form['table_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="table-wrapper">',
      '#suffix' => '</div>',
    ];
    // Fetches feedback.
    $response = $this->contentFetcher->fetchFeedback($feedback_api_params);
    // Builds table and pager.
    $form['table_wrapper']['feedback_table'] = $this->contentFetcher->buildFeedbackTable($response['results'], $response['is_watching_content'], $fields);
    $form['table_wrapper']['pager'] = $this->contentFetcher->buildPager($response['total'], $response['per_page']);

    if (isset($response['total']) && is_numeric($response['total']) && $response['total'] > 0) {
      $csv_download_url = Url::fromRoute('mass_feedback_loop.mass_feedback_csv_download', [], ['query' => $feedback_api_params])->toString();
      $form['csv_export'] = [
        '#type' => 'markup',
        // @codingStandardsIgnoreStart
        '#markup' => "<div class='csv-export-wrapper'>
          <a href='$csv_download_url'>
            <span class='feed-icon'></span> Download CSV Export
          </a>
        </div>",
      ];
    }

    // Adds sorting information to drupalSettings.
    $form['feedback_table']['#attached']['drupalSettings']['massFeedbackLoop']['sortingVariants'] = MassFeedbackLoopContentFetcher::SORTING_VARIANTS;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->getBuildInfo()['args'][0];
    $op = $form_state->getValue('op');
    if ($op == "Reset") {
      $url = Url::fromRoute('mass_feedback_loop.per_node_feedback_form', ['node' => $node->id()], []);
      $form_state->setRedirectUrl($url);
    }
    else {
      $feedback_api_params = [];

      $filter_by_flagged_inappropriate = $form_state->getValue('flagged_inappropriate');
      if (isset($filter_by_flagged_inappropriate['flagged_inappropriate'])) {
        $flagged_inappropriate_value = $filter_by_flagged_inappropriate['flagged_inappropriate'];
      }
      if (!isset($flagged_inappropriate_value) || $flagged_inappropriate_value !== 'flagged_inappropriate') {
        $feedback_api_params['flagged_inappropriate'] = 0;
      }
      else {
        $feedback_api_params['flagged_inappropriate'] = 1;
      }

      $url = Url::fromRoute('mass_feedback_loop.per_node_feedback_form', ['node' => $node->id()], ['query' => $feedback_api_params]);
      $form_state->setRedirectUrl($url);
    }
  }

}
