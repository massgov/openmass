<?php

namespace Drupal\mass_bigquery\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Top Priorities form.
 *
 * @package Drupal\mass_bigquery\Form
 */
class TopPrioritiesForm extends FormBase {

  /**
   * Our user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $path;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxyInterface $account, EntityTypeManagerInterface $entity_type_manager, PathValidator $path, DateFormatter $date_formatter, Connection $database) {
    $this->account = $account;
    // User fields aren't available in account we have to use entityTypeManager.
    $this->entityTypeManager = $entity_type_manager;
    $this->path = $path;
    $this->dateFormatter = $date_formatter;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('path.validator'),
      $container->get('date.formatter'),
      $container->get('bigquery.database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'top_priorities_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = $this->formGenerator($form);

    $form['#attached']['library'][] = 'mass_bigquery/mass-top-priorities';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form never needs to be submitted.
  }

  /**
   * {@inheritdoc}
   */
  public function formGenerator(array $form) {
    $form = [];

    // Begins table construction with surrounding container.
    $form['table_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="table-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    $org_node = $this->getOrgNode();
    // If there's no organization for the user, don't show the table.
    if (empty($org_node)) {
      $user_name = $this->account->getAccountName();
      $form['table_wrapper']['results_table'] = [
        '#markup' => '<h2>' . $user_name . ' is currently not attached to any organization.</h2>',
      ];
      return ($form);
    }
    $org_nid = $org_node->id();

    $org_name = $org_node->title->value;

    $option = [
      'query' => [
        'node_org_filter' => $org_name . ' (' . $org_nid . ') - Organization',
        'order' => 'pageviews',
        'sort' => 'desc',
        'last_updated' => 'All',
      ],
    ];

    $url = Url::fromUri('internal:/admin/content/performance', $option)->toString();

    $prefix = '<h2>Content that needs attention</h2>';
    $prefix .= "<p>These are your organization's highest-trafficked pages with broken links or a relatively high number of visitors reporting that they didn't find what they wanted. The red numbers below indicate where there is an issue and can be clicked for more details. Broken link data is updated weekly so any fixed links will not be removed from this report right away. You can see more details by adjusting filters in our <a href=\"" . $url . "\">Content Performance</a> report.</p>";

    $header = [
      'page_views' => [
        'data' => $this->t('Page views (1 month)'),
        'specifier' => 'page_views',
      ],
      'title' => [
        'data' => $this->t('Title'),
        'specifier' => 'title',
      ],
      'content_type' => [
        'data' => 'Content Type',
        'specifier' => 'content_type',
      ],
      'nos_per_1000' => [
        'data' => $this->t('Nos per 1000'),
        'specifier' => 'nos_per_1000',
      ],
      'broken_links' => [
        'data' => $this->t('Broken Links'),
        'specifier' => 'broken_links',
      ],
      'last_revised' => [
        'data' => $this->t('Last Revised'),
        'specifier' => 'last_revised',
      ],
    ];

    $form['table_wrapper']['results_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => $prefix,
    ];

    // Query the db for results.
    $results = $this->queryData($org_nid);

    if (empty($results)) {
      $form['table_wrapper']['results_table'] = [
        '#markup' => '<h2>There is no content that needs attention for ' . $org_name . ' at this time.</h2>',
      ];
      return ($form);
    }

    // Feed result data into rows and format results.
    foreach ($results as $result) {
      $url = $this->path->getUrlIfValid($result->alias);
      $analytics_url = Url::fromUri('internal:/node/' . $result->nid . '/analytics-new');
      $feedback_url = Url::fromUri('internal:/node/' . $result->nid . '/feedback');

      $row = [];
      $row['#attributes']['id'] = $result->nid . '_row';

      $row['page_views'] = [
        '#markup' => number_format($result->pageviews),
      ];

      $row['title'] = [
        '#title' => $result->title,
        '#type' => 'link',
        '#url' => $url,
      ];

      $row['content_type'] = [
        '#markup' => ucwords(str_replace("_", " ", $this->entityTypeManager->getStorage('node_type')->load($result->type)->label())),
      ];

      $row['nos_per_1000'] = [
        '#title' => $result->nos_per_1000_cleaned ? round($result->nos_per_1000_cleaned, 1) : 'N/A',
        '#type' => 'link',
        '#url' => $feedback_url,
        '#attributes' => [
          'class' => $result->nos_per_1000_cleaned ? (round($result->nos_per_1000_cleaned, 1) >= 6 ? 'red-link' : '') : '',
        ],
      ];

      $row['broken_links'] = [
        '#title' => $result->broken_links,
        '#type' => 'link',
        '#url' => $analytics_url,
        '#attributes' => [
          // Any broken links are bad and red.
          'class' => !empty($result->broken_links) ? 'red-link' : '',
        ],
      ];

      $row['last_revised'] = [
        '#markup' => $this->dateFormatter->format($result->changed, 'short_date_only'),
      ];

      $form['table_wrapper']['results_table'][] = $row;
    }

    return ($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function queryData(string $org_nid) {
    // Filter to show pages with 1 or more broken links OR (no's per K>4
    // AND pageviews>20 AND count of negative submissions >=4)
    $query = $this->database->select('node_field_data', 'n');
    $query->join('path_alias', 'pa', "pa.path = CONCAT('/node/', n.nid)");
    $query->join('mass_bigquery_data', 'm', 'n.nid = m.nid');
    $query->join('node__field_organizations', 'o', 'n.nid = o.entity_id');
    $query->fields('n', ['nid', 'type', 'title', 'changed']);
    $query->fields('m', ['pageviews', 'nos_per_1000_cleaned', 'total_no', 'broken_links']);
    $query->fields('pa', ['alias']);
    // Exclude topic pages.
    $query->condition('n.type', 'topic_page', '!=');

    $query->condition('o.field_organizations_target_id', $org_nid, '=');
    $query->condition('n.status', 1);
    $nosPer1000 = $query->andConditionGroup()
      ->isNotNull('nos_per_1000_cleaned')
      ->isNotNull('total_no')
      ->isNotNull('pageviews')
      ->condition('nos_per_1000_cleaned', 6, '>=')
      ->condition('total_no', 8, '>=')
      ->condition('pageviews', 30, '>');
    $brokenLinks = $query->andConditionGroup()
      ->isNotNull('broken_links')
      ->condition('broken_links', 1, '>=');
    $nosPer1000OrBrokenLinks = $query->orConditionGroup()
      ->condition($nosPer1000)
      ->condition($brokenLinks);
    $query->condition($nosPer1000OrBrokenLinks);
    $query->orderBy('m.pageviews', 'DESC');
    $query->range(0, 12);
    $results = $query->execute()->fetchAll();

    return $results;
  }

  public function getOrgNode() {
    // Get the target id.
    $user = $this->entityTypeManager->getStorage('user')->load($this->account->id());
    $tid = $user->field_user_org->target_id;
    if (empty($tid)) {
      return;
    }
    // If there's an organzation tied to the user, query for data.
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    if (empty($term)) {
      return;
    }

    // Get the nid for using in the query.
    $org_nid = $term->field_state_organization->target_id;
    if (empty($org_nid)) {
      return;
    }
    return $this->entityTypeManager->getStorage('node')->load($org_nid);
  }

}
