<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\user\Entity\Role;
use Drupal\views\Views;
use MassGov\Dtt\MassExistingSiteBase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the redirect link normalizer Views report, CSV export, and permissions.
 *
 * Covers: report page, change log service clear, CSV export pagination,
 * and role-based access control.
 *
 * @group existing-site
 */
class ReportViewTest extends MassExistingSiteBase {

  use RedirectNormalizerTestTrait;

  /**
   * Tests the redirect link normalization report view lists change log rows.
   */
  public function testChangeLogReportViewShowsLoggedData(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    $successEntityId = 45601;
    $failureEntityId = 78901;
    $beforeMarkup = '<p><a href="/old-path-mnrl-view-test">Old</a></p>';
    $afterMarkup = '<p><a href="/new-path-mnrl-view-test">New</a></p>';
    $failureMessage = 'MNRL view test failure message.';

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkChangeLog $service */
    $service = \Drupal::service('mass_redirect_normalizer.change_log');
    $service->logChanges('node', $successEntityId, 'page', 'drush', [
      [
        'field' => 'body',
        'delta' => 0,
        'kind' => 'text',
        'before' => $beforeMarkup,
        'after' => $afterMarkup,
      ],
    ]);
    $service->logFailure('node', $failureEntityId, 'page', 'drush', $failureMessage);

    $view = Views::getView('redirect_link_normalizer_report');
    $this->assertNotNull($view, 'redirect_link_normalizer_report view must exist.');
    $view->setDisplay('page_1');
    $view->execute();
    $this->assertNotEmpty($view->result);

    $successRow = NULL;
    $failureRow = NULL;
    foreach ($view->result as $row) {
      $entityId = (int) $this->viewResultValue($row, 'entity_id');
      if ($entityId === $successEntityId) {
        $successRow = $row;
      }
      if ($entityId === $failureEntityId) {
        $failureRow = $row;
      }
    }

    $this->assertNotNull($successRow, 'Report view should include the succeeded change row.');
    $this->assertSame('succeeded', $this->viewResultValue($successRow, 'status'));
    $this->assertSame('body', $this->viewResultValue($successRow, 'field_name'));
    $this->assertSame('drush', $this->viewResultValue($successRow, 'source'));
    $this->assertStringContainsString(
      '/old-path-mnrl-view-test',
      (string) $this->viewResultValue($successRow, 'before_value')
    );
    $this->assertStringContainsString(
      '/new-path-mnrl-view-test',
      (string) $this->viewResultValue($successRow, 'after_value')
    );

    $this->assertNotNull($failureRow, 'Report view should include the failed change row.');
    $this->assertSame('failed', $this->viewResultValue($failureRow, 'status'));
    $this->assertSame($failureMessage, $this->viewResultValue($failureRow, 'error_message'));
  }

  /**
   * Tests change log service clear removes all records.
   */
  public function testChangeLogServiceClearAll(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkChangeLog $service */
    $service = \Drupal::service('mass_redirect_normalizer.change_log');
    $service->logChanges('node', 123, 'page', 'drush', [
      [
        'field' => 'body',
        'delta' => 0,
        'kind' => 'text',
        'before' => '<a href="/old">old</a>',
        'after' => '<a href="/new">new</a>',
      ],
    ]);
    $service->logChanges('node', 456, 'page', 'presave', [
      [
        'field' => 'body',
        'delta' => 0,
        'kind' => 'text',
        'before' => '<a href="/old2">old2</a>',
        'after' => '<a href="/new2">new2</a>',
      ],
    ]);

    $before = (int) \Drupal::database()
      ->query('SELECT COUNT(*) FROM {mass_redirect_normalizer_change_log}')
      ->fetchField();
    $this->assertGreaterThan(0, $before);

    $service->clearAll();

    $after = (int) \Drupal::database()
      ->query('SELECT COUNT(*) FROM {mass_redirect_normalizer_change_log}')
      ->fetchField();
    $this->assertSame(0, $after);
  }

  /**
   * Tests report permissions are scoped to content admins and admin role.
   */
  public function testReportPermissionsRoleScope(): void {
    $contentTeam = Role::load('content_team');
    $editor = Role::load('editor');
    $author = Role::load('author');
    $this->assertNotNull($contentTeam);
    $this->assertNotNull($editor);
    $this->assertNotNull($author);

    $contentTeamPermissions = $contentTeam->getPermissions();
    $this->assertContains('view mass redirect normalizer report', $contentTeamPermissions);
    $this->assertContains('export mass redirect normalizer report', $contentTeamPermissions);
    $this->assertContains('clear mass redirect normalizer report', $contentTeamPermissions);
    $this->assertNotContains('view mass redirect normalizer report', $editor->getPermissions());
    $this->assertNotContains('view mass redirect normalizer report', $author->getPermissions());
  }

  /**
   * Tests CSV export returns all rows when the change log spans multiple pages.
   *
   * The page_1 display paginates at 50 rows. This test seeds 55 rows (more
   * than one page worth) so that a naïve export using the page pager would
   * truncate the output. The data_export_1 display uses pager type "none" and
   * export_method "standard", so all rows must appear in the downloaded CSV
   * regardless of the page display's items-per-page setting.
   */
  public function testCsvExportReturnsAllRowsAcrossMultiplePages(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    // 55 rows: one full page (50) plus 5 on a second page.
    $rowCount = 55;
    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkChangeLog $service */
    $service = \Drupal::service('mass_redirect_normalizer.change_log');
    for ($i = 1; $i <= $rowCount; $i++) {
      $service->logChanges('node', 900000 + $i, 'page', 'drush', [
        [
          'field' => 'body',
          'delta' => 0,
          'kind' => 'text',
          'before' => sprintf('<a href="/old-%d">old</a>', $i),
          'after' => sprintf('<a href="/new-%d">new</a>', $i),
        ],
      ]);
    }

    // Verify the page display sees only 50 (paged).
    $pageView = Views::getView('redirect_link_normalizer_report');
    $this->assertNotNull($pageView);
    $pageView->setDisplay('page_1');
    $pageView->execute();
    $this->assertCount(50, $pageView->result, 'Page display should return only the first 50 rows.');

    // Hit the export route as a freshly created admin user via an HTTP kernel
    // sub-request. This is the same code path as a browser clicking the
    // Export CSV button.
    $adminUser = $this->createUser();
    $adminUser->addRole('administrator');
    $adminUser->activate();
    $adminUser->save();

    $accountSwitcher = \Drupal::service('account_switcher');
    $accountSwitcher->switchTo($adminUser);

    $exportRequest = HttpRequest::create(
      '/admin/reports/redirect-link-normalizer/export'
    );
    $exportResponse = \Drupal::service('http_kernel')->handle(
      $exportRequest,
      HttpKernelInterface::SUB_REQUEST
    );
    $accountSwitcher->switchBack();

    $this->assertSame(200, $exportResponse->getStatusCode(), 'Export route must return HTTP 200.');
    $csv = (string) $exportResponse->getContent();
    $this->assertNotEmpty($csv, 'CSV export response must not be empty.');
    $this->assertStringContainsString('Changed,Status', $csv, 'CSV must contain a header row.');

    // Spot-check entity IDs from both "pages" appear in the CSV.
    $this->assertStringContainsString('900001', $csv, 'First entity ID must appear in CSV.');
    $this->assertStringContainsString('900055', $csv, 'Last entity ID (page 2) must appear in CSV.');

    // Use PHP's csv parser to count logical data rows reliably, correctly
    // handling RFC-4180 quoted fields that may contain embedded newlines.
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csv);
    rewind($handle);
    $csvRows = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
      $csvRows[] = $row;
    }
    fclose($handle);
    // First row is the header; subtract it.
    $dataRowCount = count($csvRows) - 1;
    $this->assertSame(
      $rowCount,
      $dataRowCount,
      sprintf(
        'CSV export should contain all %d rows, not just one page worth (%d found).',
        $rowCount,
        $dataRowCount
      )
    );
  }

}
