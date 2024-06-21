<?php

namespace Drupal\mass_feedback_loop\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service class for interacting with external Mass.gov API.
 */
class MassFeedbackLoopContentFetcher {
  use StringTranslationTrait;

  /**
   * Possible variants of "Sort by" behavior to be used in feedback table.
   *
   * Stored as a public constant. Also used in
   * \Drupal\mass_feedback_loop\Form\MassFeedbackLoopAuthorInterfaceForm.
   */
  const SORTING_VARIANTS = [
    0 => [
      'order_by' => 'submit_date',
      'desc' => TRUE,
    ],
    1 => [
      'order_by' => 'submit_date',
      'desc' => FALSE,
    ],
  ];

  /**
   * Static, non-sensitive configuration for making external API requests.
   */
  const EXTERNAL_API_CONFIG = [
    'api_endpoints' => [
      'feedback_endpoint' => 'feedback/',
      'label_lookup_endpoint' => 'labels/',
    ],
    'api_headers' => [
      'content_type_header' => 'application/json',
      'referer_header' => 'edit.mass.gov',
    ],
  ];

  /**
   * Current user's account.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Array of required read-only configuration for external API connection.
   *
   * @var array
   */
  protected $settings;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * A Guzzle HTTP client instance.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * A Pager Manager from the container.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Custom logger channel for mass_feedback_loop module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountProxy $current_user,
    Connection $database,
    Settings $settings,
    ConfigFactoryInterface $config_factory,
    ClientFactory $http_client_factory,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    PagerManagerInterface $pager_manager
  ) {
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->settings = $settings->get('mass_feedback_loop')['external_api_config'];
    $this->config = $config_factory->get('mass_feedback_loop.external_api_config');
    $this->httpClient = $http_client_factory->fromOptions([
      'headers' => [
        'Content-Type' => self::EXTERNAL_API_CONFIG['api_headers']['content_type_header'],
        'Referer' => self::EXTERNAL_API_CONFIG['api_headers']['referer_header'],
        'Authenticate' => $this->settings['authenticate_header'],
      ],
      'base_uri' => $this->settings['api_base_url'],
    ]);
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->pagerManager = $pager_manager;
  }

  /**
   * Fetches all flagged content based on a flag ID and a user account.
   *
   * @param string $flag_id
   *   ID of flag created using contrib Flag module.
   * @param \Drupal\Core\Session\AccountProxy|null $account
   *   User account.
   * @param string $title_order
   *   ORDER BY direction for title field in database query: 'ASC' or 'DESC'.
   *
   * @return array
   *   Array of NIDs for content flagged by user.
   */
  public function fetchFlaggedContent($flag_id = 'watch_content', AccountProxy $account = NULL, $title_order = 'ASC') {
    // Uses current user's account if none is provided.
    if (empty($account)) {
      $account = $this->currentUser;
    }
    // Gets NIDs of all content being watched by the user.
    $query = $this->database->query('SELECT f.entity_id FROM {flagging} AS f LEFT JOIN {node_field_data} AS n ON f.entity_id = n.nid WHERE f.flag_id = :flag_id AND f.uid = :uid ORDER BY n.title ' . ($title_order == 'ASC' ? 'ASC' : 'DESC'), [
      ':flag_id' => $flag_id,
      ':uid' => $account->id(),
    ]);
    return $query->fetchCol();
  }

  /**
   * Fetches feedback from Mass.gov API.
   *
   * @param array $feedback_api_params
   *   Parameters to be sent to the feedback API's fetch feedback endpoint.
   *   Possible keys: 'org_id', 'node_id', 'author_id', 'date_from',
   *   'date_to', 'info_found', 'sort_by', 'page'.
   *
   * @return array
   *   Decoded JSON data.
   */
  public function fetchFeedback(array $feedback_api_params = []) {
    $feedback_api_params['per_page'] = (int) $this->config->get('per_page');
    if (!isset($feedback_api_params['page'])) {
      $feedback_api_params['page'] = 1;
    }
    // The pager is 0 based, but the API is 1 based.
    else {
      $feedback_api_params['page'] += 1;
    }
    // @todo By default we actually want to show all node's latest feedback over the last two weeks.
    // See: https://jira.mass.gov/browse/DP-11729.
    // Until then if no specific 'org_id', 'author_id' or 'node_id' filters are provided, then
    // by default we fetch feedback for all the nodes that the current user is watching.
    if (!isset($feedback_api_params['node_id']) && !isset($feedback_api_params['org_id']) && !isset($feedback_api_params['author_id'])) {
      if (isset($feedback_api_params['watch_content']) && $feedback_api_params['watch_content'] == 1) {
        $feedback_api_params['node_id'] = $this->fetchFlaggedContent();
      }
    }
    elseif (isset($feedback_api_params['node_id']) && (isset($feedback_api_params['watch_content']) && $feedback_api_params['watch_content'] == 1)) {
      $flagged_nodes = $this->fetchFlaggedContent();
      // Check if any of the flagged nodes match the node id filter.
      foreach ($feedback_api_params['node_id'] as $node_id) {
        // Check if node id is in our array.
        if (!in_array($node_id, $flagged_nodes)) {
          // Since this id isn't watched, don't include it in search results.
          unset($node_id, $feedback_api_params['node_id']);
        }
      }
    }
    elseif ((isset($feedback_api_params['watch_content']) && $feedback_api_params['watch_content'] == 1)) {
      $feedback_api_params['node_id'] = $this->fetchFlaggedContent();
    }

    // Fetches feedback from external API.
    try {
      // We always order by submit date without exposing this parameter to users.
      // They can select however if they want DESC sort or not, because that part is exposed in the filter params.
      $feedback_api_params['order_by'] = 'submit_date';

      $params = $feedback_api_params;
      unset($params['watch_content']);

      $request = $this->httpClient->get(self::EXTERNAL_API_CONFIG['api_endpoints']['feedback_endpoint'], [
        'json' => $params,
      ]);

      if (isset($feedback_api_params['file_type']) && $feedback_api_params['file_type'] === 'csv') {
        return $request->getBody();
      }
      else {
        return Json::decode($request->getBody()) + [
          'per_page' => $feedback_api_params['per_page'],
                // User is watching content.
          'is_watching_content' => TRUE,
        ];
      }
    }
    catch (RequestException $e) {
      // @todo The API should not return an exception in the no results situation.
      // See: https://jira.mass.gov/browse/DP-11729.
      \Drupal::logger('mass_feedback_loop')->error('The Feedback API returned an exception when a request with the following params was sent. SERIALIZED PARAMS = @params', ['@params' => serialize($feedback_api_params)]);
      return [
        'results' => [],
        'total' => 0,
        'per_page' => $feedback_api_params['per_page'],
          // User is not watching content.
        'is_watching_content' => TRUE,
      ];
    }
  }

  /**
   * Helper function to format URL query parameters for the feeedback API.
   *
   * @param array $params
   *   Array of URL query parameters to format.
   *
   * @return array
   *   The processed query parameters.
   */
  public function formatQueryParams(array $params) {
    $feedback_api_params = [];
    foreach ($params as $key => $param) {
      if (in_array($key, [
        'org_id',
        'node_id',
        'label_id',
        'author_id',
        'watch_content',
        'flagged_inappropriate',
      ])) {
        if (in_array($key, ['watch_content', 'flagged_inappropriate']) || !empty($param)) {
          $feedback_api_params[$key] = $param;
          if (is_array($param) && strpos($param[0], ',') !== FALSE) {
            $feedback_api_params[$key] = explode(',', $param[0]);
          }
          elseif (!is_array($param) && strpos($param, ',') !== FALSE) {
            $feedback_api_params[$key] = explode(',', $param);
          }
        }
      }
      else {
        if (!empty($param)) {
          $feedback_api_params[$key] = $param;
        }
      }
    }

    return $feedback_api_params;
  }

  /**
   * Helper function to build feedback table.
   *
   * @param array $results
   *   Array of feedback data from external API.
   * @param bool $is_watching_content
   *   Boolean to check whether user is currently watching content.
   * @param array $limit_fields
   *   The fields to show or empty to show all fields.
   *
   * @return array
   *   Render array.
   */
  public function buildFeedbackTable(array $results, $is_watching_content = TRUE, array $limit_fields = []) {
    // Builds base table.
    $table = [
      '#type' => 'table',
      '#header' => [],
      // Links to Watched Content dashboard, if user is not watching content.
      '#empty' => ($is_watching_content) ? $this->t('No feedback available.') : Link::createFromRoute($this->t('You must be watching content to view related feedback.'), 'view.watched_content.page')->toRenderable(),
      '#responsive' => TRUE,
      '#attributes' => [
        'id' => 'feedback-table',
      ],
    ];
    if (empty($limit_fields) || in_array('submit_date', $limit_fields)) {
      $table['#header'][] = [
        'data' => [
          '#markup' => $this->t('Date') . '<span data-sort-by="submit_date" />',
        ],
      ];
    }
    if (empty($limit_fields) || in_array('info_found', $limit_fields)) {
      $table['#header'][] = [
        'data' => [
          '#markup' => $this->t('Did You Find?') . '<span data-sort-by="info_found" />',
        ],
      ];
    }
    if (empty($limit_fields) || in_array('source_page', $limit_fields)) {
      $table['#header'][] = [
        'data' => [
          '#markup' => $this->t('Source Page') . '<span data-sort-by="source_page" />',
        ],
        'class' => ['feedback-medium'],
      ];
    }
    if (empty($limit_fields) || in_array('text', $limit_fields)) {
      $table['#header'][] = [
        'data' => [
          '#markup' => $this->t('Feedback Text'),
        ],
        'class' => ['feedback-wide'],
      ];
    }

    // Builds table rows from feedback.
    if (!empty($results)) {
      foreach ($results as $index => $feedback) {
        $key = 'feedback_' . $index;
        if (!empty($feedback)) {
          $row = [];
          if (empty($limit_fields) || in_array('submit_date', $limit_fields)) {
            // Builds "Date".
            $date = new DrupalDateTime($feedback['submit_date']);
            $formatted_date = $date->format('n/j/Y');
            $row['submit_date'] = [
              '#markup' => $formatted_date,
            ];
          }

          if (empty($limit_fields) || in_array('info_found', $limit_fields)) {
            // Builds "Did You Find?".
            $info_found = (!empty($feedback['info_found']) && $feedback['info_found']) ? $this->t('Yes') : $this->t('No');
            $row['info_found'] = [
              '#markup' => $info_found,
            ];
          }

          if (empty($limit_fields) || in_array('source_page', $limit_fields)) {
            // Builds "Source Page".
            // Uses data stored in drupalSettings object on initial page load.
            // @see \Drupal\mass_feedback_loop\Form\MassFeedbackLoopAuthorInterfaceForm
            // Check if node exists.
            $node_check = $this->entityTypeManager->getStorage('node')
              ->load($feedback['node_id']);

            if (isset($node_check)) {
              $source_page = (!empty($feedback['node_id'])) ? $this->entityTypeManager->getStorage('node')
                ->load($feedback['node_id'])
                ->toLink()
                ->toString() : '';

              $row['source_page'] = [
                '#markup' => $source_page,
              ];
            }
            else {
              $row['source_page'] = [
                '#markup' => $node_check->title ?? '',
              ];
            }
          }
          if (empty($limit_fields) || in_array('text', $limit_fields)) {
            // Builds "Feedback Text".
            $feedback_text = (!empty($feedback['text'])) ? $feedback['text'] : '';
            $row['text'] = [
              '#markup' => '<span class="survey-text">' . Html::escape($feedback_text) . '</span>',
              '#wrapper_attributes' => ['class' => 'survey-response'],
            ];
          }

          $table[$key] = $row;

        }
      }
    }

    // Returns table render array with feedback data.
    return $table;
  }

  /**
   * Helper function to build pager for feedback table.
   *
   * @param int $total
   *   The total number of items to be paged.
   * @param int $limit
   *   The number of items the calling code will display per page.
   * @param array $parameters
   *   Optional parameters to use within pager links to preserve form values.
   *
   * @return array
   *   Render array.
   */
  public function buildPager($total, $limit, array $parameters = []) {
    // Initializes pager based on feedback response.
    $this->pagerManager->createPager($total, $limit);

    // Returns pager render array, with parameters if available.
    return [
      '#type' => 'pager',
      '#tags' => [
        $this->t('First'),
        $this->t('Previous'),
        NULL,
        $this->t('Next'),
        $this->t('Last'),
      ],
      '#parameters' => $parameters,
    ];
  }

}
