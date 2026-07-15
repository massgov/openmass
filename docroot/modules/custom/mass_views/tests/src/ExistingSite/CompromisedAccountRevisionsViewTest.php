<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\views\Views;
use Drupal\views\ViewExecutable;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests compromised account revision rollback views and actions (DP-47148).
 *
 * @group existing-site
 * @group mass_views
 */
class CompromisedAccountRevisionsViewTest extends MassExistingSiteBase {

  private const NODE_VIEW_PATH = '/admin/content/compromised-account-revisions';

  private const MEDIA_VIEW_PATH = '/admin/content/compromised-account-media-revisions';

  private const NODE_EXPOSED_FORM_ID = 'views-exposed-form-compromised-account-revisions-page-1';

  protected User $admin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->admin = $this->createUser();
    $this->admin->addRole('administrator');
    $this->admin->activate();
    $this->admin->save();
    $this->drupalLogin($this->admin);
  }

  /**
   * Non-administrators cannot access the rollback views.
   */
  public function testNonAdminCannotAccessRollbackViews(): void {
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->activate();
    $editor->save();
    $this->drupalLogin($editor);

    $this->drupalGet(self::NODE_VIEW_PATH);
    $this->assertContains($this->getSession()->getStatusCode(), [403, 404]);

    $this->drupalGet(self::MEDIA_VIEW_PATH);
    $this->assertContains($this->getSession()->getStatusCode(), [403, 404]);
  }

  /**
   * Initial page load should show input-required guidance, not form errors.
   */
  public function testNodePageLoadWithoutFiltersShowsInputRequiredMessage(): void {
    $this->drupalGet(self::NODE_VIEW_PATH);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', '.messages--error');
    $this->assertSession()->pageTextContains('Enter the compromised revision author and/or date range, then click Filter to see results.');
  }

  /**
   * Date filters use Better Exposed Filters date picker widgets.
   */
  public function testNodeDateFiltersUseDatePickerWidgets(): void {
    $this->drupalGet(self::NODE_VIEW_PATH);
    $this->assertSession()->fieldExists('changed_from');
    $this->assertSession()->fieldExists('changed_to');
    $this->assertSession()->elementExists('css', 'input[name="changed_from"][type="date"]');
    $this->assertSession()->elementExists('css', 'input[name="changed_to"][type="date"]');
  }

  /**
   * Filtered node results link the title to the content page.
   */
  public function testFilteredNodeViewLinksTitleToContent(): void {
    $title = 'DP-47148 linked title test ' . $this->randomMachineName(8);
    $node = $this->createNodeWithRevisions($title);

    $this->drupalGet(self::NODE_VIEW_PATH);
    $this->submitForm(
      ['revision_uid' => $this->adminAutocompleteValue()],
      'Filter',
      self::NODE_EXPOSED_FORM_ID,
    );

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//table//a[contains(@href, "/node/' . $node->id() . '")]');
  }

  /**
   * Filtered node results include a Revisions link to the node history page.
   */
  public function testFilteredNodeViewShowsRevisionLinks(): void {
    $title = 'DP-47148 compromised revisions test ' . $this->randomMachineName(8);
    $this->createNodeWithRevisions($title);

    $this->drupalGet(self::NODE_VIEW_PATH);
    $this->submitForm(
      ['revision_uid' => $this->adminAutocompleteValue()],
      'Filter',
      self::NODE_EXPOSED_FORM_ID,
    );

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', '.messages--error');
    $this->assertSession()->pageTextContains($title);
    $this->assertSession()->linkExists('Revisions');
  }

  /**
   * Bulk rollback action is allowed on default revision rows.
   */
  public function testBulkRevertActionAllowsDefaultRevisionRows(): void {
    $title = 'DP-47148 default revision access ' . $this->randomMachineName(8);
    $this->createNodeWithRevisions($title);

    $view = $this->executeNodeViewWithAdminFilter();
    $this->assertNotEmpty($view->result);

    $action = \Drupal::service('plugin.manager.action')
      ->createInstance('mass_views_revert_pre_incident_node');
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $default_row_found = FALSE;
    foreach ($view->result as $row) {
      $revision = $storage->loadRevision($row->vid);
      if (!$revision || !str_contains($revision->label(), $title) || !$revision->isDefaultRevision()) {
        continue;
      }
      $default_row_found = TRUE;
      $this->assertTrue(
        $action->access($revision, $this->admin),
        'Bulk rollback must be allowed on default revision rows.',
      );
      break;
    }
    $this->assertTrue($default_row_found, 'Test node should have a default revision row in the view.');
  }

  /**
   * Bulk rollback reverts content and sets the expected revision log message.
   */
  public function testBulkRevertRestoresPreIncidentContentAndLogMessage(): void {
    $title = 'DP-47148 rollback execute ' . $this->randomMachineName(8);
    $good_title = $title . ' good';
    $bad_title = $title . ' compromised';

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $incident_time = \Drupal::time()->getRequestTime() - 3600;
    $before_incident = $incident_time - 86400;

    $node = $storage->create([
      'type' => 'page',
      'title' => $good_title,
      'status' => 1,
    ]);
    $node->set('moderation_state', MassModeration::PUBLISHED);
    $node->setRevisionUserId($this->admin->id());
    $node->setRevisionLogMessage('known good revision');
    $node->setRevisionCreationTime($before_incident);
    $node->setChangedTime($before_incident);
    $node->save();
    $pre_incident_vid = (int) $node->getRevisionId();

    $node = $storage->load($node->id());
    $node->setTitle($bad_title);
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId($this->admin->id());
    $node->setRevisionLogMessage('compromised revision');
    $node->setRevisionCreationTime($incident_time);
    $node->setChangedTime($incident_time);
    $node->save();

    $view = Views::getView('compromised_account_revisions');
    $this->assertNotNull($view);
    $view->setDisplay('page_1');
    $view->setExposedInput([
      'revision_uid' => (string) $this->admin->id(),
      'changed_from' => date('Y-m-d', $incident_time - 60),
      'changed_to' => date('Y-m-d', $incident_time + 60),
    ]);

    $action = \Drupal::service('plugin.manager.action')
      ->createInstance('mass_views_revert_pre_incident_node');
    // VBO strips exposed filters from the view during batch processing.
    $view->setExposedInput(['_views_bulk_operations_override' => TRUE]);
    $action->setView($view);
    $context = [
      'exposed_input' => [
        'revision_uid' => (string) $this->admin->id(),
        'changed_from' => date('Y-m-d', $incident_time - 60),
        'changed_to' => date('Y-m-d', $incident_time + 60),
      ],
      'sandbox' => [],
    ];
    $action->setContext($context);
    $result = $action->execute(Node::load($node->id()));
    $this->assertStringContainsString('Reverted', (string) $result);

    $reloaded = Node::load($node->id());
    $this->assertSame($good_title, $reloaded->label());
    $this->assertSame(
      'Rollback to revision #' . $pre_incident_vid . ': known good revision',
      $reloaded->getRevisionLogMessage(),
    );
  }

  /**
   * Bulk rollback succeeds when the pre-incident revision has no log message.
   */
  public function testBulkRevertHandlesNullPreIncidentRevisionLogMessage(): void {
    $title = 'DP-47148 rollback null log ' . $this->randomMachineName(8);
    $good_title = $title . ' good';
    $bad_title = $title . ' compromised';

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $incident_time = \Drupal::time()->getRequestTime() - 3600;
    $before_incident = $incident_time - 86400;

    $node = $storage->create([
      'type' => 'page',
      'title' => $good_title,
      'status' => 1,
    ]);
    $node->set('moderation_state', MassModeration::PUBLISHED);
    $node->setRevisionUserId($this->admin->id());
    $node->setRevisionLogMessage(NULL);
    $node->setRevisionCreationTime($before_incident);
    $node->setChangedTime($before_incident);
    $node->save();
    $pre_incident_vid = (int) $node->getRevisionId();

    $node = $storage->load($node->id());
    $node->setTitle($bad_title);
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId($this->admin->id());
    $node->setRevisionLogMessage('compromised revision');
    $node->setRevisionCreationTime($incident_time);
    $node->setChangedTime($incident_time);
    $node->save();

    $view = Views::getView('compromised_account_revisions');
    $this->assertNotNull($view);
    $view->setDisplay('page_1');
    $view->setExposedInput(['_views_bulk_operations_override' => TRUE]);

    $action = \Drupal::service('plugin.manager.action')
      ->createInstance('mass_views_revert_pre_incident_node');
    $action->setView($view);
    $context = [
      'exposed_input' => [
        'revision_uid' => (string) $this->admin->id(),
        'changed_from' => date('Y-m-d', $incident_time - 60),
        'changed_to' => date('Y-m-d', $incident_time + 60),
      ],
      'sandbox' => [],
    ];
    $action->setContext($context);
    $result = $action->execute(Node::load($node->id()));
    $this->assertStringContainsString('Reverted', (string) $result);

    $reloaded = Node::load($node->id());
    $this->assertSame($good_title, $reloaded->label());
    $this->assertSame(
      'Rollback to revision #' . $pre_incident_vid,
      $reloaded->getRevisionLogMessage(),
    );
  }

  /**
   * Bulk rollback actions are configured on the node and media views.
   */
  public function testBulkRevertActionsAreConfiguredOnViews(): void {
    foreach ([
      'compromised_account_revisions' => 'mass_views_revert_pre_incident_node',
      'compromised_account_media_revisions' => 'mass_views_revert_pre_incident_media',
    ] as $view_id => $action_id) {
      $view = Views::getView($view_id);
      $this->assertNotNull($view);
      $view->initDisplay();
      $handler = $view->display_handler->getHandler('field', 'views_bulk_operations_bulk_form');
      $this->assertNotNull($handler);
      $actions = $handler->options['selected_actions'] ?? [];
      $action_ids = array_column($actions, 'action_id');
      $this->assertContains($action_id, $action_ids);
      $this->assertTrue($handler->options['batch'] ?? FALSE, 'Rollback uses VBO batch processing.');
      $this->assertSame(10, $handler->options['batch_size'] ?? NULL);
    }
  }

  /**
   * Media rollback view is reachable for administrators.
   */
  public function testMediaViewIsReachableWithDatePickers(): void {
    $this->drupalGet(self::MEDIA_VIEW_PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Enter the compromised revision author and/or date range, then click Filter to see results.');
    $this->assertSession()->elementExists('css', 'input[name="changed_from"][type="date"]');
    $this->assertSession()->elementExists('css', 'input[name="changed_to"][type="date"]');
  }

  /**
   * Creates a node with two revisions attributed to the admin account.
   */
  private function createNodeWithRevisions(string $title): Node {
    $node = $this->createNode([
      'type' => 'page',
      'title' => $title,
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
    ]);
    $node->setRevisionUserId($this->admin->id());
    $node->setRevisionLogMessage('DP-47148 initial revision');
    $node->save();

    $node->setTitle($title . ' updated');
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId($this->admin->id());
    $node->setRevisionLogMessage('DP-47148 compromised revision');
    $node->save();

    return $node;
  }

  /**
   * Executes the node view filtered to the admin account.
   */
  private function executeNodeViewWithAdminFilter(): ViewExecutable {
    $view = Views::getView('compromised_account_revisions');
    $this->assertNotNull($view);
    $view->setDisplay('page_1');
    $view->setExposedInput([
      'revision_uid' => $this->adminAutocompleteValue(),
    ]);
    $view->execute();
    return $view;
  }

  /**
   * Autocomplete value for the admin revision author filter.
   */
  private function adminAutocompleteValue(): string {
    return $this->admin->getDisplayName() . ' (' . $this->admin->id() . ')';
  }

}
