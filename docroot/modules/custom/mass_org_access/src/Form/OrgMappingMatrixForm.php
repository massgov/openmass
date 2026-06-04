<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('state'),
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
      '#markup' => '<p>' . $this->t('Assign State organization terms to each organization page. <strong>Save</strong> stores the page(s) you have visited; <strong>Apply to nodes</strong> writes the saved matrix onto the org pages; <strong>Download CSV</strong> exports everything saved so far. Showing @per of @total organization pages — save before paging.', [
        '@per' => count($nodes),
        '@total' => $total,
      ]) . '</p>',
    ];

    $form['items_per_page'] = [
      '#type' => 'select',
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
        '#title' => $node->label() . ' (' . $nid . ')',
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

    $form['actions']['#type'] = 'actions';
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::saveSubmit'],
    ];
    $form['actions']['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to nodes'),
      '#submit' => ['::applySubmit'],
    ];
    $form['actions']['download'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download CSV'),
      '#submit' => ['::downloadSubmit'],
    ];
    $form['actions']['clear_load'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear the saved matrix and load from nodes'),
      '#submit' => ['::clearLoadSubmit'],
      '#limit_validation_errors' => [],
    ];
    $form['actions']['clear_fresh'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear the saved matrix and start fresh'),
      '#submit' => ['::clearFreshSubmit'],
      '#limit_validation_errors' => [],
    ];

    return $form;
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
