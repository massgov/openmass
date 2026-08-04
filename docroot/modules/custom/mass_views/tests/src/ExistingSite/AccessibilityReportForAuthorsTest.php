<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\views\Views;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests the Accessibility report for authors view (DP-48112).
 *
 * @group existing-site
 */
class AccessibilityReportForAuthorsTest extends MassExistingSiteBase {

  /**
   * Pids inserted by this test, cleaned up in tearDown.
   *
   * @var int[]
   */
  private array $seededPids = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->seededPids) {
      $database = \Drupal::database();
      $database->delete('ed11y_result')
        ->condition('pid', $this->seededPids, 'IN')
        ->execute();
      $database->delete('ed11y_page')
        ->condition('pid', $this->seededPids, 'IN')
        ->execute();
    }
    parent::tearDown();
  }

  /**
   * Smoke test: report page loads without SQL errors.
   */
  public function testReportPageLoadsWithoutSqlErrors(): void {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // MassExistingSiteBase fails the test on any logged SQL error, so this
    // catches schema drift against the raw SQL in mass_views.module.
    $this->drupalGet('/admin/ma-dash/report/accessibility-report-for-authors', [
      'query' => ['status' => '1'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Accessibility report for authors');
  }

  /**
   * Ignores stale ed11y_page rows and uses the current-URL scan count.
   */
  public function testReportUsesCurrentUrlScanNotStaleAlias(): void {
    $title = 'DP-48112 accessibility report ' . $this->randomMachineName(8);
    $node = $this->createNode([
      'type' => 'service_page',
      'title' => $title,
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $nid = (int) $node->id();

    $current_alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
    if ($current_alias === '/node/' . $nid) {
      $current_alias = '/dp-48112-current-' . $this->randomMachineName(8);
      PathAlias::create([
        'path' => '/node/' . $nid,
        'alias' => $current_alias,
        'langcode' => 'en',
        'status' => 1,
      ])->save();
    }

    $stale_path = '/dp-48112-stale-' . $this->randomMachineName(8);
    $now = \Drupal::time()->getRequestTime();

    $stale_pid = $this->insertEd11yPage([
      'entity_id' => $nid,
      'entity_type' => 'Service',
      'route_name' => 'entity.node.canonical',
      'page_path' => $stale_path,
      'page_language' => 'en',
      'page_title' => $title,
      'content_results' => 7,
      'dev_results' => 0,
      'updated' => $now - 100,
    ]);
    $this->insertEd11yResult($stale_pid, 'IMAGE_DECORATIVE', 7, $now - 100);

    $current_pid = $this->insertEd11yPage([
      'entity_id' => $nid,
      'entity_type' => 'Service',
      'route_name' => 'entity.node.canonical',
      'page_path' => $current_alias,
      'page_language' => 'en',
      'page_title' => $title,
      'content_results' => 2,
      'dev_results' => 0,
      'updated' => $now,
    ]);
    $this->insertEd11yResult($current_pid, 'LINK_URL', 2, $now);

    // input_required_on_request only builds the query when the HTTP request
    // has query parameters (not just setExposedInput).
    \Drupal::request()->query->set('status', '1');

    $view = Views::getView('accessibility_report_for_authors');
    $this->assertNotNull($view);
    $view->setDisplay('default');
    $view->setItemsPerPage(0);
    $view->setExposedInput(['status' => '1']);
    $view->execute();

    $matching = [];
    foreach ($view->result as $row) {
      if (!$this->rowMatchesEntityId($row, $nid)) {
        continue;
      }
      $matching[] = $this->rowContentCount($row);
    }

    $this->assertNotEmpty($view->result, 'View should return results when status filter is in the request.');
    $this->assertCount(1, $matching, 'Node should appear once (stale alias scan ignored). Current alias: ' . $current_alias);
    $this->assertSame(2, $matching[0], 'Issue count should come from the current-URL scan.');
  }

  /**
   * Inserts an ed11y_page row and tracks its pid for cleanup.
   */
  private function insertEd11yPage(array $fields): int {
    $pid = (int) \Drupal::database()->insert('ed11y_page')
      ->fields($fields)
      ->execute();
    $this->seededPids[] = $pid;
    return $pid;
  }

  /**
   * Inserts an ed11y_result row for a page.
   */
  private function insertEd11yResult(int $pid, string $result_key, int $content_count, int $created): void {
    \Drupal::database()->insert('ed11y_result')
      ->fields([
        'pid' => $pid,
        'created' => $created,
        'result_name' => $result_key,
        'result_key' => $result_key,
        'content_count' => $content_count,
        'dev_count' => 0,
      ])
      ->execute();
  }

  /**
   * Whether a Views result row is for the given node id.
   */
  private function rowMatchesEntityId(object $row, int $nid): bool {
    foreach (get_object_vars($row) as $key => $value) {
      if (str_contains((string) $key, 'entity_id') && (int) $value === $nid) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Reads the aggregated content_count from a Views result row.
   */
  private function rowContentCount(object $row): int {
    foreach (get_object_vars($row) as $key => $value) {
      if (str_contains((string) $key, 'content_count')) {
        return (int) $value;
      }
    }
    $this->fail('Could not find content_count on Views result row: ' . implode(', ', array_keys(get_object_vars($row))));
  }

}
