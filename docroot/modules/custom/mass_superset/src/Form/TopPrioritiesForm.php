<?php

namespace Drupal\mass_superset\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Top Priorities form.
 *
 * @package Drupal\mass_superset\Form
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
  protected $dataservice;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxyInterface $account, EntityTypeManagerInterface $entity_type_manager, PathValidator $path, DateFormatter $date_formatter, Connection $dataservice) {
    $this->account = $account;
    // User fields aren't available in account we have to use entityTypeManager.
    $this->entityTypeManager = $entity_type_manager;
    $this->path = $path;
    $this->date_formatter = $date_formatter;
    $this->database = $dataservice;
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
      $container->get('superset.database')
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

    $form['#attached']['library'][] = 'mass_superset/mass-top-priorities';

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
    // Begins form construction.
    $form = [];

    // Begins table construction with surrounding container.
    $form['table_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="table-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    // Get the target id.
    $user = $this->entityTypeManager->getStorage('user')->load($this->account->id());
    $tid = $user->field_user_org->target_id;

    // Get the user's name.
    $user_name = $this->account->getAccountName();

    // If there's an organzation tied to the user, query for data.
    if ($tid) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      // Get the nid for using in the query.
      $org_nid = $term->field_state_organization->target_id;
      $node_storage = $this->entityTypeManager->getStorage('node')->load($org_nid);

      if (isset($node_storage)) {
        // Get the organization name for the header of the table.
        $name = $node_storage->title->value;
        $node_org_filter = $name . ' (' . $org_nid . ') - Organization';

        // Create url for prefix.
        $option = [
          'query' => [
            'node_org_filter' => $node_org_filter,
            'score' => '3',
            'order' => 'pageviews',
            'sort' => 'desc',
            'last_updated' => 'All',
          ],
        ];

        $url = Url::fromUri('internal:/admin/content', $option)->toString();

        $prefix = "<h2>Content that needs attention</h2><p>These are your organization's 10 highest-trafficked pages with the lowest scores. You can also sort or filter a <a href=\"" . $url . "\">full list of this content</a> on the \"All content\" page.</p><p>Click \"Snooze\" after you improve content. This will hide it from this list for the next 4 weeks while new data comes in (and, hopefully, the score is changing).</p>";

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
          'score' => [
            'data' => $this->t('Score'),
            'specifier' => 'score',
          ],
          'last_revised' => [
            'data' => $this->t('Last Revised'),
            'specifier' => 'last_revised',
          ],
          'snooze' => [
            'data' => $this->t('Snooze content for 4 weeks'),
            'specifier' => 'snooze',
          ],
        ];

        $form['table_wrapper']['results_table'] = [
          '#type' => 'table',
          '#header' => $header,
          '#prefix' => $prefix,
          '#attributes' => [
            'class' => ['snooze-table'],
          ],
        ];

        // Query the db for results.
        $results = $this->queryData($org_nid);

      }

      if (isset($results)) {
        if (count($results) > 10) {
          $form['table_wrapper']['results_table']['#attributes']['show_more'] = TRUE;
        }
        else {
          $form['table_wrapper']['results_table']['#attributes']['show_more'] = FALSE;
        }

        $i = 0;
        // Feed result data into rows and format results.
        foreach ($results as $result) {
          // Only run for first 10 results.
          // i increments twice per runthrough.
          if ($i < 20) {
            $i_snooze = $i + 1;

            if (round($result->score, 1) < 3) {
              $snooze_class = 'red-link';
            }
            else {
              $snooze_class = '';
            }
            $url = $this->path->getUrlIfValid($result->alias);
            $score_url = 'internal:/node/' . $result->nid . '/analytics';
            $analytics = Url::fromUri($score_url);
            $snooze_confirmation = "<div class=\"snooze-confirm-text\"><i>" . $result->title . "</i> has been snoozed, and will disappear from this table on page refresh. <a href=\"/nojs/undo_snooze/" . $result->nid . "\" class=\"use-ajax\">Undo</a></div><summary role=\"button\" id=\"" . $result->nid . "\" class=\"snooze-close\" onclick=\"snoozedClose(this)\"></summary>";

            // Setup id's for all our rows.
            $form['table_wrapper']['results_table'][$i]['#attributes']['id'] = $result->nid . '_row';
            $form['table_wrapper']['results_table'][$i_snooze]['#attributes']['id'] = $result->nid . '_row--snooze';

            // Add a hidden class for the snooze rows.
            $form['table_wrapper']['results_table'][$i_snooze]['#attributes']['class'] = 'row-hidden';

            $form['table_wrapper']['results_table'][$i]['page_views'] = [
              '#markup' => number_format($result->pageviews),
            ];

            $form['table_wrapper']['results_table'][$i]['title'] = [
              '#title' => $result->title,
              '#type' => 'link',
              '#url' => $url,
            ];

            $form['table_wrapper']['results_table'][$i]['content_type'] = [
              '#markup' => ucwords(str_replace("_", " ", $this->entityTypeManager->getStorage('node_type')->load($result->type)->label())),
            ];

            $form['table_wrapper']['results_table'][$i]['score'] = [
              '#title' => round($result->score, 1),
              '#type' => 'link',
              '#url' => $analytics,
              '#attributes' => [
                'class' => $snooze_class,
              ],
            ];

            $form['table_wrapper']['results_table'][$i]['last_revised'] = [
              '#markup' => $this->date_formatter->format($result->changed, 'short_date_only'),
            ];

            // Setup the snooze button.
            $form['table_wrapper']['results_table'][$i]['snooze'] = [
              '#id' => 'snooze_' . $result->nid,
              '#type' => 'button',
              '#value' => $this->t('Snooze'),
              '#name' => $result->nid,
              '#ajax' => [
                'callback' => [$this, 'snoozeTopPrioritiesTable'],
                'event' => 'click',
                'wrapper' => 'table-wrapper',
                'progress' => [
                  'type' => 'throbber',
                ],
              ],
            ];

            // Snooze confirmation row.
            $form['table_wrapper']['results_table'][$i_snooze]['snooze_confirmation'] = [
              '#id' => $result->nid,
              '#attributes' => [
                'class' => 'snooze-confirmation',
              ],
              '#markup' => $snooze_confirmation,
              '#wrapper_attributes' => ['colspan' => 6],
            ];

            $i = $i + 2;
          }
        }

        if (count($results) > 10) {
          // Add a link at the bottom of the table for refreshing the table.
          $form['table_wrapper']['results_table'][$i]['refresh_table'] = [
            '#id' => 'refresh-table',
            '#type' => 'button',
            '#value' => $this->t('See more content that needs attention'),
            '#name' => 'refresh-table',
            '#wrapper_attributes' => [
              'colspan' => 3,
              'class' => 'snooze-refresh refresh-hidden',
              'id' => 'refresh-wrapper',
            ],
            '#ajax' => [
              'callback' => [$this, 'reloadTopPrioritiesTable'],
              'event' => 'click',
              'wrapper' => 'table-wrapper',
              'progress' => [
                'type' => 'throbber',
              ],
            ],
          ];

          $colspan = 3;
        }
        else {
          $colspan = 6;
        }

        // Add a link at the bottom of the table for seeing snoozed content.
        $snooze_option = [
          'query' => [
            'last_updated' => '1',
            'node_org_filter' => $node_org_filter,
          ],
        ];
        $snooze_url = Url::fromUri('internal:/admin/content', $snooze_option)->toString();
        $snooze_list = "<div class=\"snoozed-list\"><a href=\"" . $snooze_url . "\">See snoozed content</div>";

        $form['table_wrapper']['results_table'][$i]['snooze_link'] = [
          '#markup' => $snooze_list,
          '#wrapper_attributes' => ['colspan' => $colspan],
        ];
      }
      else {
        if (isset($name)) {
          // If the query returns no results, don't show the table.
          $form['table_wrapper']['results_table'] = [
            '#markup' => '<h2>There is no content that needs attention for ' . $name . ' at this time.</h2>',
          ];
        }
        else {
          // If the query returns no results, don't show the table.
          $form['table_wrapper']['results_table'] = [
            '#markup' => '<h2>There is no content that needs attention at this time.</h2>',
          ];
        }
      }
    }
    else {
      // If there's no organization for the user, don't show the table.
      $form['table_wrapper']['results_table'] = [
        '#markup' => '<h2>' . $user_name . ' is currently not attached to any organization.</h2>',
      ];
    }

    return ($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function queryData(string $org_nid) {
    // Get the timestamp for 28 days ago to compare our snooze flag to.
    $date = new DrupalDateTime('28 days ago');
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $date_range = $date->getTimestamp();

    // Query the db for the 10 highest trafficked pages with lowest scores.
    $query = $this->database->select('node_field_data', 'n');
    $query->leftjoin('path_alias', 'pa', "pa.path = CONCAT('/node/', n.nid)");
    $query->leftjoin('mass_superset_data', 'm', 'n.nid = m.nid');
    $query->leftjoin('node__field_organizations', 'o', 'n.nid = o.entity_id');
    $query->leftjoin('snooze', 's', 's.entity_id = n.nid');
    $query->fields('n', ['nid', 'type', 'title', 'changed']);
    $query->fields('m', ['pageviews', 'score']);
    $query->fields('pa', ['alias']);
    $query->fields('o', ['field_organizations_target_id']);
    $query->fields('s', ['entity_id', 'last_updated']);
    $query->condition('o.field_organizations_target_id', $org_nid, '=');
    $query->condition('n.status', 1);
    $query->condition('m.score', 3, '<');
    $query->isNotNull('m.score');
    // If content isn't flagged snooze or the snooze is outdated.
    $or = new Condition('OR');
    $or->isNull('s.entity_id');
    $or->condition('s.last_updated', $date_range, '<=');
    $query->condition($or);
    $query->orderBy('m.pageviews', 'DESC');
    $query->range(0, 11);
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Custom AJAX callback to reload the page.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Render array.
   */
  public function reloadTopPrioritiesTable(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(
      new InvokeCommand(NULL, 'reloadPage')
    );

    return($response);
  }

  /**
   * Custom AJAX callback to snooze a row in the top priorities table.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Render array.
   */
  public function snoozeTopPrioritiesTable(array &$form, FormStateInterface $form_state) {
    // Get nid of the snooze button clicked.
    $triggeredElement = $form_state->getTriggeringElement();
    $nid = $triggeredElement['#name'];
    $more = $form['table_wrapper']['results_table']['#attributes']['show_more'];

    $response = new AjaxResponse();
    $response->addCommand(
      new InvokeCommand(NULL, 'snoozed', [$nid, $more])
    );

    // Add nid to the snooze table.
    $this->insertSnooze($nid);

    return($response);
  }

  /**
   * Insert row into the snooze database.
   *
   * @param string $nid
   *   Nid of the node we want to add/update.
   */
  public function insertSnooze(string $nid) {
    // Create array that will hold our fields for our row.
    $row = [];
    $row['entity_id'] = $nid;

    // Query to see if this node is already in the snooze table.
    $query = $this->database->select('snooze', 's');
    $query->fields('s', ['entity_id', 'last_updated']);
    $query->condition('s.entity_id', $row['entity_id'], '=');
    $results = $query->execute()->fetchAll();

    // Get the current timestamp to add to the row.
    $current_date = new DrupalDateTime();
    $current_date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $row['last_updated'] = $current_date->getTimestamp();

    // If the query returned a result we update that row, if not create new row.
    if (count($results) == 0) {
      $this->database->insert('snooze')->fields($row)->execute();
    }
    else {
      $this->database->update('snooze')
        ->fields($row)
        ->condition('entity_id', $row['entity_id'], '=')
        ->execute();
    }
  }

  /**
   * Remove row from the snooze database.
   *
   * @param string $nid
   *   Nid of the node we want to remove.
   */
  public function undoSnooze(string $nid) {
    // Query to see if this node is already in the snooze table.
    $query = $this->database->select('snooze', 's');
    $query->fields('s', ['entity_id', 'last_updated']);
    $query->condition('s.entity_id', $nid, '=');
    $results = $query->execute()->fetchAll();

    // If the query returned a result we remove that row.
    if (count($results) != 0) {
      $this->database->delete('snooze')
        ->condition('entity_id', $nid)
        ->execute();
    }

    $response = new AjaxResponse();
    $response->addCommand(
      new InvokeCommand(NULL, 'snoozedUndo', [$nid])
    );

    return($response);

  }

}
