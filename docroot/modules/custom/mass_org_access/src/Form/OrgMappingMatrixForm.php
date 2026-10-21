<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editor for the org_page → Permission Group (State organization) matrix.
 *
 * Lists organization pages, each with a Select2 term picker. Save persists the
 * whole matrix to State so an admin can resume; Download CSV exports it in the
 * nodeid,termid format the Import tab consumes. Paged because the site has well
 * over a thousand organization pages.
 */
class OrgMappingMatrixForm extends FormBase {

  /**
   * State key holding the in-progress matrix: [nid => [tid, …]].
   */
  public const STATE_KEY = 'mass_org_access.matrix';

  /**
   * State flag: start fresh — unsaved nodes show empty, not their node value.
   */
  public const FRESH_KEY = 'mass_org_access.matrix_fresh';

  /**
   * Organization pages shown per page.
   */
  private const PER_PAGE = 50;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mass_org_access_mapping_matrix';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $total = (int) $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'org_page')
      ->count()
      ->execute();

    $items = $this->itemsPerPage();
    $query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'org_page')
      ->sort('title');
    if ($items !== 'all') {
      $query->pager((int) $items);
    }
    $nodes = $node_storage->loadMultiple($query->execute());
    $matrix = $this->state->get(self::STATE_KEY, []);

    $form['help'] = [
      '#weight' => -30,
      '#markup' => '<p>' . $this->t('Assign State organization terms to each organization page. Showing @per of @total organization pages — save before paging.', [
        '@per' => count($nodes),
        '@total' => $total,
      ]) . '</p><ul>'
      . '<li>' . $this->t('<strong>Save</strong> — stores the selections from the page(s) you have visited into the working matrix (kept until cleared), so you can resume later. Nothing is written to the org pages yet.') . '</li>'
      . '<li>' . $this->t('<strong>Apply to nodes</strong> — writes the whole saved matrix onto the org pages, overwriting their current Permission Groups (terms plus ancestors, published revision and any draft).') . '</li>'
      . '<li>' . $this->t('<strong>Download CSV</strong> — exports everything saved so far in the <code>nodeid,termid</code> format the Import mappings tab accepts.') . '</li>'
      . '<li>' . $this->t('<strong>Clear the saved matrix and load from nodes</strong> — discards the working matrix; the fields show each org page&#039;s current Permission Groups again. Org pages themselves are not changed.') . '</li>'
      . '<li>' . $this->t('<strong>Clear the saved matrix and start fresh</strong> — discards the working matrix and starts with all fields empty, ignoring the values on the org pages. Org pages themselves are not changed.') . '</li>'
      . '</ul>',
    ];

    $form['items_per_page'] = [
      '#type' => 'select',
      '#weight' => -20,
      '#title' => $this->t('Items per page'),
      '#options' => [
        '50' => '50',
        '100' => '100',
        '500' => '500',
        'all' => $this->t('All (@n)', ['@n' => $total]),
      ],
      '#default_value' => $items,
      '#attributes' => ['data-oog-items-per-page' => 'true'],
    ];
    $form['#attached']['library'][] = 'mass_org_access/matrix_items_per_page';
    $form['#attached']['library'][] = 'mass_org_access/matrix_sticky_actions';

    // A second, sticky copy of the action buttons above the matrix, so the
    // admin doesn't have to scroll past 50+ rows to save. The explicit
    // weight keeps it above the rows — the theme pushes weightless
    // "actions" wrappers to the bottom of the form otherwise.
    $form['actions_top'] = $this->buildActions('top') + ['#weight' => -10];

    // Resolve the default term IDs per node: a saved matrix value always wins;
    // otherwise show the node's current Permission Groups, unless "start fresh"
    // mode is on, in which case unsaved nodes show empty.
    $fresh = (bool) $this->state->get(self::FRESH_KEY, FALSE);
    $defaults = [];
    $label_ids = [];
    foreach ($nodes as $nid => $node) {
      $tids = array_key_exists((int) $nid, $matrix)
        ? array_map('intval', (array) $matrix[(int) $nid])
        : ($fresh ? [] : $this->currentTids($node));
      $defaults[$nid] = $tids;
      foreach ($tids as $tid) {
        $label_ids[$tid] = $tid;
      }
    }
    $labels = [];
    if ($label_ids) {
      foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($label_ids) as $tid => $term) {
        $labels[$tid] = $term->label();
      }
    }

    // Human-readable moderation state labels, so the content team can spot
    // unpublished/trashed org pages while mapping.
    $state_labels = [];
    if ($workflow = $this->entityTypeManager->getStorage('workflow')->load('editorial')) {
      foreach ($workflow->getTypePlugin()->getConfiguration()['states'] ?? [] as $state_id => $state) {
        $state_labels[$state_id] = $state['label'] ?? $state_id;
      }
    }

    $form['orgs'] = ['#tree' => TRUE];
    foreach ($nodes as $nid => $node) {
      $options = [];
      foreach ($defaults[$nid] as $tid) {
        if (isset($labels[$tid])) {
          $options[$tid] = $labels[$tid];
        }
      }
      $form['orgs'][$nid] = [
        '#type' => 'select2',
        '#title' => $node->label() . ' (' . $nid . ')'
        . $this->moderationStateSuffix($node, $node_storage, $state_labels),
        '#description' => $this->t('<a href=":url" target="_blank" rel="noopener">View org page</a>', [
          ':url' => Url::fromRoute('entity.node.edit_form', ['node' => $nid])->toString(),
        ]),
        '#multiple' => TRUE,
        '#autocomplete' => TRUE,
        '#target_type' => 'taxonomy_term',
        '#selection_handler' => 'default:taxonomy_term',
        '#selection_settings' => ['target_bundles' => ['user_organization' => 'user_organization']],
        '#options' => $options,
        '#default_value' => array_values($defaults[$nid]),
        '#select2' => ['placeholder' => $this->t('Add organizations…')],
      ];
    }

    if ($items !== 'all') {
      $form['pager'] = ['#type' => 'pager'];
    }

    $form['actions'] = $this->buildActions('bottom');

    return $form;
  }

  /**
   * Builds one copy of the action buttons.
   *
   * The form renders the set twice — a sticky copy above the matrix and a
   * regular one below. Top buttons get a "_top" #name suffix so Form API
   * resolves the triggering element unambiguously; both copies share the
   * same submit handlers.
   *
   * @param string $variant
   *   Either 'top' or 'bottom'.
   *
   * @return array
   *   The actions render array.
   */
  private function buildActions(string $variant): array {
    $top = $variant === 'top';
    $name = fn (string $key): string => $top ? $key . '_top' : $key;

    $actions = ['#type' => 'actions'];
    if ($top) {
      $actions['#attributes']['class'][] = 'oog-matrix-actions-top';
    }
    $actions['save'] = [
      '#type' => 'submit',
      '#name' => $name('save'),
      '#value' => $this->t('Save'),
      '#submit' => ['::saveSubmit'],
    ];
    $actions['apply'] = [
      '#type' => 'submit',
      '#name' => $name('apply'),
      '#value' => $this->t('Apply to nodes'),
      '#submit' => ['::applySubmit'],
    ];
    $actions['download'] = [
      '#type' => 'submit',
      '#name' => $name('download'),
      '#value' => $this->t('Download CSV'),
      '#submit' => ['::downloadSubmit'],
    ];
    $actions['clear_load'] = [
      '#type' => 'submit',
      '#name' => $name('clear_load'),
      '#value' => $this->t('Clear the saved matrix and load from nodes'),
      '#submit' => ['::clearLoadSubmit'],
      '#limit_validation_errors' => [],
    ];
    $actions['clear_fresh'] = [
      '#type' => 'submit',
      '#name' => $name('clear_fresh'),
      '#value' => $this->t('Clear the saved matrix and start fresh'),
      '#submit' => ['::clearFreshSubmit'],
      '#limit_validation_errors' => [],
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * Buttons use their own #submit handlers; this satisfies the interface.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Save handler: merges the current page into the State matrix.
   */
  public function saveSubmit(array &$form, FormStateInterface $form_state): void {
    $this->persist($form_state);
    $this->messenger()->addStatus($this->t('Mapping saved. You can resume later or download the CSV.'));
  }

  /**
   * Download handler: persists the page, then streams the CSV.
   */
  public function downloadSubmit(array &$form, FormStateInterface $form_state): void {
    $this->persist($form_state);
    $form_state->setRedirect('mass_org_access.mapping_matrix_csv');
  }

  /**
   * Clears the saved matrix so the form reloads each node's current value.
   */
  public function clearLoadSubmit(array &$form, FormStateInterface $form_state): void {
    $this->state->delete(self::STATE_KEY);
    $this->state->delete(self::FRESH_KEY);
    $this->messenger()->addStatus($this->t('Saved matrix cleared — fields now show each organization page current Permission Groups.'));
    $this->redirectToForm($form_state);
  }

  /**
   * Clears the saved matrix and starts with empty fields, ignoring node values.
   */
  public function clearFreshSubmit(array &$form, FormStateInterface $form_state): void {
    $this->state->delete(self::STATE_KEY);
    $this->state->set(self::FRESH_KEY, TRUE);
    $this->messenger()->addStatus($this->t('Saved matrix cleared — fields start empty. Build the mapping from scratch.'));
    $this->redirectToForm($form_state);
  }

  /**
   * Redirects to a fresh GET of the form, keeping the page size.
   *
   * A plain rebuild would keep the submitted Select2 values and mask the
   * cleared state; a redirect rebuilds the form from the updated State.
   */
  private function redirectToForm(FormStateInterface $form_state): void {
    $query = [];
    $items = $this->getRequest()->query->get('items');
    if ($items !== NULL) {
      $query['items'] = $items;
    }
    $form_state->setRedirect('mass_org_access.mapping_matrix', [], ['query' => $query]);
  }

  /**
   * Apply handler: persists the page, then writes the whole matrix to nodes.
   *
   * Reuses the import batch (force overwrite), so each org_page's Permission
   * Groups are set from the matrix — terms plus ancestors, on both the
   * published and any forward-draft revision.
   */
  public function applySubmit(array &$form, FormStateInterface $form_state): void {
    $this->persist($form_state);
    $matrix = array_filter($this->state->get(self::STATE_KEY, []));
    if (!$matrix) {
      $this->messenger()->addWarning($this->t('Nothing to apply — the saved matrix has no terms.'));
      return;
    }

    $operations = [];
    foreach ($matrix as $nid => $tids) {
      $operations[] = [
        [OrgMappingImportForm::class, 'batchApply'],
        [(int) $nid, array_map('intval', (array) $tids), TRUE, ''],
      ];
    }
    batch_set([
      'title' => $this->t('Applying organization mappings to nodes'),
      'init_message' => $this->t('Applying @count organization mapping(s)…', ['@count' => count($operations)]),
      'operations' => $operations,
      'finished' => [OrgMappingImportForm::class, 'batchFinished'],
    ]);
  }

  /**
   * Merges the current page's selections into the State matrix.
   */
  private function persist(FormStateInterface $form_state): void {
    $matrix = $this->state->get(self::STATE_KEY, []);
    foreach ((array) $form_state->getValue('orgs') as $nid => $items) {
      $tids = [];
      foreach ((array) $items as $item) {
        // Select2 entity autocomplete submits [['target_id' => tid], …].
        $tid = (int) (is_array($item) ? ($item['target_id'] ?? 0) : $item);
        if ($tid) {
          $tids[] = $tid;
        }
      }
      $matrix[(int) $nid] = $tids;
    }
    $this->state->set(self::STATE_KEY, $matrix);
  }

  /**
   * The validated "items per page" choice from the query string.
   *
   * @return string
   *   One of '50', '100', '500', or 'all'.
   */
  private function itemsPerPage(): string {
    $value = (string) $this->getRequest()->query->get('items', (string) self::PER_PAGE);
    return in_array($value, ['50', '100', '500', 'all'], TRUE)
      ? $value
      : (string) self::PER_PAGE;
  }

  /**
   * Moderation state row suffix: current revision plus any forward draft.
   *
   * Each revision shows its state, save date, and the user who saved it,
   * e.g. " — Current revision: Published (05/12/2026 - 14:33, dima);
   * Latest revision: Draft (06/01/2026 - 09:12, morcutt)". The
   * latest-revision part only appears when a forward (unpublished) draft
   * exists, so rows without drafts stay short.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The org_page (default revision).
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   Node storage, for the latest-revision lookup.
   * @param array $state_labels
   *   Workflow state labels keyed by state ID.
   *
   * @return string
   *   The suffix, or '' when the node has no moderation state.
   */
  private function moderationStateSuffix($node, $node_storage, array $state_labels): string {
    $current = $this->describeRevision($node, $state_labels);
    if ($current === '') {
      return '';
    }
    $suffix = ' — ' . $this->t('Current revision: @info', ['@info' => $current]);

    $latest_vid = $node_storage->getLatestRevisionId($node->id());
    if ($latest_vid && (int) $latest_vid !== (int) $node->getRevisionId()) {
      $draft = $node_storage->loadRevision($latest_vid);
      $draft_info = $draft ? $this->describeRevision($draft, $state_labels) : '';
      if ($draft_info !== '') {
        $suffix .= '; ' . $this->t('Latest revision: @info', ['@info' => $draft_info]);
      }
    }
    return $suffix;
  }

  /**
   * One revision as "State (date, user)".
   *
   * @param \Drupal\node\NodeInterface $revision
   *   The revision to describe.
   * @param array $state_labels
   *   Workflow state labels keyed by state ID.
   *
   * @return string
   *   E.g. "Published (05/12/2026 - 14:33, dima)", or '' when the
   *   revision carries no moderation state.
   */
  private function describeRevision($revision, array $state_labels): string {
    if (!$revision->hasField('moderation_state')) {
      return '';
    }
    $state_id = (string) $revision->get('moderation_state')->value;
    if ($state_id === '') {
      return '';
    }
    $state = $state_labels[$state_id] ?? $state_id;
    // US date format with a 12-hour clock.
    $date = $this->dateFormatter->format((int) $revision->getRevisionCreationTime(), 'custom', 'm/d/Y g:i A');
    $user = $revision->getRevisionUser()?->getDisplayName() ?? $this->t('unknown user');
    return "$state ($date, $user)";
  }

  /**
   * Current Permission Group term IDs on an org_page.
   *
   * @return int[]
   *   The term IDs.
   */
  private function currentTids($node): array {
    if (!$node->hasField('field_content_organization')) {
      return [];
    }
    return array_map(
      'intval',
      array_column($node->get('field_content_organization')->getValue(), 'target_id')
    );
  }

}
