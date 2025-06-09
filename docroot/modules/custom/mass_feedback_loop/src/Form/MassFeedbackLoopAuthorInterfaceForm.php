<?php

namespace Drupal\mass_feedback_loop\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MassFeedbackLoopAuthorInterfaceForm.
 */
class MassFeedbackLoopAuthorInterfaceForm extends FormBase {

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassFeedbackLoopContentFetcher $content_fetcher, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, Connection $database) {
    $this->contentFetcher = $content_fetcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_feedback_loop.content_fetcher'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
        $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_feedback_loop_author_interface_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Checks for query params in case pager link was used.
    /** @var \Symfony\Component\HttpFoundation\ParameterBag $query */
    $query = $this->getRequest()->query;

    $params = $query->all();
    $feedback_api_params = $this->contentFetcher->formatQueryParams($params);

    // Begins form construction.
    $form = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-wrapper'],
      ],
    ];

    $form['help_text_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'form-help-wrapper',
      ],
    ];

    // Adds help text for using Feedback Manager.
    $form['help_text_wrapper']['help_text'] = [
      '#markup' => $this->t('<p>Find out how users are responding to your content. <a href="https://www.mass.gov/kb/feedback-manager">Learn how to use the Feedback Manager.</a></p><p>Also see: <a href="/admin/content/pages_with_negative_feedback">Pages with high negative feedback</a>.</p>'),
    ];

    $form['filter_by_org'] = [
      '#type' => 'select2',
      '#multiple' => TRUE,
      '#title' => $this->t('Organization'),
      '#options' => $this->getOrgNids(),
      '#attributes' => [
        'placeholder' => "Start typing Organizations to filter by ...",
      ],
      // @todo split on comma, load array.
      '#default_value' => isset($feedback_api_params['org_id']) ? $feedback_api_params['org_id'] : NULL,
    ];

    // Builds "Filter by author" input.
    $form['filter_by_author'] = [
      '#type' => 'select2',
      '#multiple' => TRUE,
      '#title' => $this->t('Author'),
      '#options' => $this->getAuthorUsernames(),
      // @todo split on comma, load array.
      '#default_value' => isset($feedback_api_params['author_id']) ? $feedback_api_params['author_id'] : NULL,
      '#attributes' => [
        'placeholder' => "Start typing Author usernames ...",
      ],
    ];

    // The API expects node_id as an array,
    // but drupal form's entity_autocomplete wants just node entity object,
    // loaded via single integer nid.
    if (isset($feedback_api_params['node_id']) && is_numeric($feedback_api_params['node_id'][0])) {
      $node_id_param = $feedback_api_params['node_id'][0];
    }
    // Builds "Filter by page" input.
    $form['filter_by_page'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Content'),
      '#target_type' => 'node',
      // Updates form input with default value, if available.
      '#default_value' => (isset($node_id_param)) ? $this->entityTypeManager->getStorage('node')->load($node_id_param) : NULL,
      // Uses custom selection handler to filter for user flagged content.
      // @see \Drupal\mass_feedback_loop\Plugin\EntityReferenceSelection\MassFeedbackLoopSelection
      '#selection_handler' => 'mass_feedback_loop_selection',
      '#attributes' => [
        'placeholder' => $this->t('Start typing Content title to filter by ...'),
      ],
    ];

    // Builds "Start date" input.
    $form['filter_by_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      // Updates form input with default value, if available.
      '#default_value' => isset($feedback_api_params['date_from']) ? $feedback_api_params['date_from'] : NULL,
    ];

    // Builds "End date" input.
    $form['filter_by_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      // Updates form input with default value, if available.
      '#default_value' => isset($feedback_api_params['date_to']) ? $feedback_api_params['date_to'] : NULL,
    ];

    // Fetches labels.
    $labels = $this->getLabelTids();
    // Builds "Filter by label" input.
    $form['filter_by_label'] = [
      '#type' => 'select2',
      '#multiple' => TRUE,
      '#title' => $this->t('Filter by page label'),
      '#options' => $labels,
      '#attributes' => [
        'placeholder' => "Start typing labels to filter by ...",
      ],
      // Updates form input with default value, if available.
      '#default_value' => isset($feedback_api_params['label_id']) ? $feedback_api_params['label_id'] : NULL,
    ];

    $searchHelpText = $this->t(
      'Enter a comma-separated list of words or phrases.'
    );
    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search feedback for specific text'),
      '#attributes' => [
        'placeholder' => 'term1, term that is a phrase, term3',
      ],
      '#default_value' => $this->defSearch($feedback_api_params),
      '#description' => $searchHelpText,
    ];

    // Builds "Sort by" input.
    $form['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => [
        '0' => $this->t('Date (Newest first)'),
        'false' => $this->t('Date (Oldest first)'),
      ],
      '#default_value' => isset($feedback_api_params['desc']) ? $feedback_api_params['desc'] : '0',
    ];

    // Builds 'Filter by "Did you find?" status' input.
    $form['filter_by_info_found'] = [
      '#type' => 'radios',
      '#title' => $this->t('Filter by "Did you find?" status'),
      '#options' => [
        'true' => $this->t('Yes'),
        'false' => $this->t('No'),
        '0' => $this->t('Show all'),
      ],
      '#default_value' => isset($feedback_api_params['info_found']) ? $feedback_api_params['info_found'] : 0,
    ];

    // Builds 'Watched pages only' input.
    $form['watch_content'] = [
      '#type' => 'checkboxes',
      '#options' => ['watch_content' => $this->t('Watched pages only')],
      '#title' => $this->t('Filter by watched pages only'),
      '#default_value' => !empty($feedback_api_params['watch_content']) ? ['watch_content'] : [],
    ];

    // Builds 'Flagged inappropriate' input.
    $form['flagged_inappropriate'] = [
      '#type' => 'checkboxes',
      '#options' => ['flagged_inappropriate' => $this->t('Show feedback flagged as low quality')],
      '#title' => $this->t('Filter by feedback quality'),
      '#default_value' => !empty($feedback_api_params['flagged_inappropriate']) ? ['flagged_inappropriate'] : [],
    ];

    // Hidden value used for tracking current page on pager in case of reload.
    $form['page'] = [
      '#type' => 'hidden',
      // Updates form input with default value, if available.
      '#value' => (!empty($feedback_api_params['page'])) ? $feedback_api_params['page'] : NULL,
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

    // Begins table construction with surrounding container.
    $form['table_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="table-wrapper">',
      '#suffix' => '</div>',
    ];
    // Attaches necessary JS library to run single-page app.
    $form['#attached']['library'][] = 'mass_feedback_loop/mass-feedback-author-interface';

    // Adds sorting information to drupalSettings.
    $form['#attached']['drupalSettings']['massFeedbackLoop']['sortingVariants'] = MassFeedbackLoopContentFetcher::SORTING_VARIANTS;

    // Early return if form has not yet been submitted.
    if (!\Drupal::request()->getQueryString()) {
      return $form;
    }

    // Fetches feedback.
    $response = $this->contentFetcher->fetchFeedback($feedback_api_params);
    // Builds table and pager.
    $form['table_wrapper']['feedback_table'] = $this->contentFetcher->buildFeedbackTable($response['results'], $response['is_watching_content']);
    $form['table_wrapper']['pager'] = $this->contentFetcher->buildPager($response['total'], $response['per_page']);

    if (isset($response['total']) && is_numeric($response['total']) && $response['total'] > 0) {
      // Create and attach the link to download CSV export.
      $feedback_api_csv_download_params = $this->contentFetcher->formatQueryParams($feedback_api_params);
      $csv_download_url = Url::fromRoute('mass_feedback_loop.mass_feedback_csv_download', [], ['query' => $feedback_api_csv_download_params])->toString();
      $form['csv_export'] = [
        '#type' => 'markup',
        // @codingStandardsIgnoreStart
        '#markup' => "<div class='csv-export-wrapper'>
          <a href='$csv_download_url'>
            <span class='feed-icon'></span> Download CSV Export
          </a>
        </div>",
      ];
      // @codingStandardsIgnoreEnd
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form never needs to be submitted.
    $op = $form_state->getValue('op');
    if ($op == "Reset") {
      $url = Url::fromRoute('mass_feedback_loop.mass_feedback_loop_author_interface_form', [], []);
      $form_state->setRedirectUrl($url);
    }
    else {
      $feedback_api_params = [];

      $filter_search_param = $form_state->getValue('search');
      if (!empty($filter_search_param)) {
        $feedback_api_params['search'] = $filter_search_param;
      }

      $filter_by_org_param = $form_state->getValue('filter_by_org');
      if (!empty($filter_by_org_param)) {
        $feedback_api_params['org_id'] = [implode(",", array_keys($filter_by_org_param))];
      }

      $filter_by_label_id = $form_state->getValue('filter_by_label');
      if (!empty($filter_by_label_id)) {
        $feedback_api_params['label_id'] = [implode(",", array_keys($filter_by_label_id))];
      }

      $filter_by_author_param = $form_state->getValue('filter_by_author');
      if (!empty($filter_by_author_param)) {
        $feedback_api_params['author_id'] = [implode(",", array_keys($filter_by_author_param))];
      }

      $filter_by_start_date_param = $form_state->getValue('filter_by_start_date');
      if (!empty($filter_by_start_date_param)) {
        $feedback_api_params['date_from'] = $filter_by_start_date_param;
      }

      $filter_by_end_date_param = $form_state->getValue('filter_by_end_date');
      if (!empty($filter_by_end_date_param)) {
        $feedback_api_params['date_to'] = $filter_by_end_date_param;
      }

      $filter_by_node_param = $form_state->getValue('filter_by_page');
      if (!empty($filter_by_node_param)) {
        $feedback_api_params['node_id'][0] = $filter_by_node_param;
      }

      $descending_flag_param = $form_state->getValue('sort_by');
      if (!empty($descending_flag_param)) {
        $feedback_api_params['desc'] = $descending_flag_param;
      }

      $filter_by_info_found_param = $form_state->getValue('filter_by_info_found');
      if (!empty($filter_by_info_found_param)) {
        $feedback_api_params['info_found'] = $filter_by_info_found_param;
      }

      $filter_by_watched_param = $form_state->getValue('watch_content');
      if (isset($filter_by_watched_param['watch_content'])) {
        $watch_value = $filter_by_watched_param['watch_content'];
      }
      if (!isset($watch_value) || $watch_value !== 'watch_content') {
        $feedback_api_params['watch_content'] = 0;
      }
      else {
        $feedback_api_params['watch_content'] = 1;
      }

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

      $url = Url::fromRoute('mass_feedback_loop.mass_feedback_loop_author_interface_form', [], ['query' => $feedback_api_params]);
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * Gets all label IDs that are used on nodes.
   *
   * @return array
   *   The non-QA label IDs.
   */
  protected function getLabelTids() {
    $response_array = [];
    // Only select labels that are assigned to nodes to decrease the number of
    // labels processed by selectize.
    $query = $this->database->select('node', 'n')
      ->fields('ttd', ['tid'])
      ->condition('ttd.vid', 'label')
      ->condition('ttd.name', '%_QA%', 'NOT LIKE');
    $query->join('taxonomy_index', 'ti', 'n.nid = ti.nid');
    $query->join('taxonomy_term_field_data', 'ttd', 'ti.tid = ttd.tid');
    $tids = $query->distinct()->execute()->fetchCol();

    /** @var \Drupal\taxonomy\Entity\Term[] $entities */
    $entities = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple($tids);
    foreach ($entities as $key => $entity) {
      $response_array[$key] = $entity->getName();
    }
    asort($response_array);

    return $response_array;
  }

  /**
   * Gets all published Organization node IDs from the system.
   *
   * @return array
   *   The non-QA Organization node IDs.
   */
  protected function getOrgNids() {
    $response_array = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'org_page')
      ->condition('status', 1)
      ->condition('title', '\_QA%', 'NOT LIKE')
      ->sort('title', 'ASC');

    $org_nids = $query->accessCheck(FALSE)->execute();
    /** @var \Drupal\node\Entity\Node[] $entities */
    $entities = $node_storage->loadMultiple($org_nids);
    foreach ($entities as $key => $entity) {
      $response_array[$key] = $entity->getTitle();
    }

    return $response_array;
  }

  /**
   * Gets all author usernames.
   *
   * @return array
   *   Array of author usernames keyed by uid.
   */
  protected function getAuthorUsernames() {
    $query = $this->database->select('users_field_data', 'ufd');
    $query->fields('ufd', ['uid', 'name']);
    $author_usernames = $query->execute()->fetchAllKeyed();
    ksort($author_usernames);
    unset($author_usernames["0"]);
    return $author_usernames;
  }

  /**
   * Helper function to transform feedback_api_params back to default_value's.
   *
   * @param array $feedback_api_params
   *   The URL query params to transform.
   *
   * @return string|null
   *   If 'search' params are present the string version|NULL
   */
  protected function defSearch(array $feedback_api_params) {
    if (
      array_key_exists('search', $feedback_api_params) &&
      is_array($feedback_api_params['search'])
    ) {
      $defSearch = implode(',', $feedback_api_params['search']);
    }
    elseif (
      array_key_exists('search', $feedback_api_params) &&
      !is_array($feedback_api_params['search'])) {
      $defSearch = $feedback_api_params['search'];
    }
    else {
      $defSearch = NULL;
    }

    return $defSearch;
  }

}
