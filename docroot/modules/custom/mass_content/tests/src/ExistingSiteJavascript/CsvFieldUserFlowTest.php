<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests csv_field real content flow with org pages.
 */
class CsvFieldUserFlowTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates an administrator user.
   */
  private function createAdminUser() {
    $user = $this->createUser();
    $user->addRole('administrator');
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Creates a CSV file entity in public:// for testing.
   */
  private function createCsvFile(string $name, string $contents): File {
    $uri = 'public://' . $name;
    file_put_contents($uri, $contents);
    $file = File::create([
      'uri' => $uri,
      'status' => 1,
    ]);
    $file->save();
    $this->markEntityForCleanup($file);
    return $file;
  }

  /**
   * Creates a larger CSV fixture for interaction testing.
   */
  private function createLargeCsvFile(string $name): File {
    $rows = [
      'Name,Department,Website',
      'Alpha Office,Health,https://www.mass.gov/alpha',
      'Beta Office,Education,https://www.mass.gov/beta',
      'Gamma Office,Transportation,https://www.mass.gov/gamma',
      'Delta Office,Revenue,https://www.mass.gov/delta',
      'Epsilon Office,Public Safety,https://www.mass.gov/epsilon',
      'Zeta Office,Energy,https://www.mass.gov/zeta',
      'Eta Office,Housing,https://www.mass.gov/eta',
      'Theta Office,Workforce,https://www.mass.gov/theta',
      'Iota Office,IT,https://www.mass.gov/iota',
      'Kappa Office,Environment,https://www.mass.gov/kappa',
      'Lambda Office,Consumer Affairs,https://www.mass.gov/lambda',
      'Unique Agency,Digital Service,https://www.mass.gov/unique',
    ];
    return $this->createCsvFile($name, implode("\n", $rows) . "\n");
  }

  /**
   * Creates a wide CSV fixture for responsive interaction testing.
   */
  private function createWideCsvFile(string $name): File {
    $rows = [
      'Agency,ColA,ColB,ColC,ColD,ColE,ColF,ColG,ColH,ColI,ColJ,ColK',
      'Alpha Office,Very long value A1,Very long value B1,Very long value C1,Very long value D1,Very long value E1,Very long value F1,Very long value G1,Very long value H1,Very long value I1,Very long value J1,Very long value K1',
      'Beta Office,Very long value A2,Very long value B2,Very long value C2,Very long value D2,Very long value E2,Very long value F2,Very long value G2,Very long value H2,Very long value I2,Very long value J2,Very long value K2',
    ];
    return $this->createCsvFile($name, implode("\n", $rows) . "\n");
  }

  /**
   * Creates a csv_table paragraph with supplied CSV settings.
   */
  private function createCsvTableParagraph(File $file, array $settings, string $title): Paragraph {
    $paragraph = Paragraph::create([
      'type' => 'csv_table',
      'field_csvtable_title' => $title,
      'field_csv_file' => [
        [
          'target_id' => $file->id(),
          'description' => $title . ' Download CSV',
          'settings' => $settings,
        ],
      ],
    ]);
    $paragraph->save();
    $this->markEntityForCleanup($paragraph);
    return $paragraph;
  }

  /**
   * Creates a section_long_form paragraph wrapping one or more csv_table paragraphs.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph|array $csvTables
   *   A single csv_table paragraph or a list of them.
   */
  private function createSectionParagraph($csvTables): Paragraph {
    if (!is_array($csvTables)) {
      $csvTables = [$csvTables];
    }
    $section = Paragraph::create([
      'type' => 'section_long_form',
      'field_section_long_form_content' => array_map(static function ($paragraph) {
        return ['entity' => $paragraph];
      }, $csvTables),
    ]);
    $section->save();
    $this->markEntityForCleanup($section);
    return $section;
  }

  /**
   * Default CSV table settings for accessibility scenarios.
   */
  private function defaultCsvTableSettings(array $overrides = []): array {
    return array_merge([
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], $overrides);
  }

  /**
   * Waits until the expected number of DataTables instances are present.
   *
   * Note: csv-field.js removes the csv-table class from the wrapper after init;
   * the table keeps dataTable classes and settings stay on div[data-settings].
   */
  private function waitForCsvTables(int $expected_count = 1): void {
    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dt-container table.dataTable, .dataTables_wrapper table.dataTable, table.dataTable.display');

    $deadline = time() + 20;
    do {
      $count = count($this->getSession()->getPage()->findAll('css', 'table.dataTable.display, table.dataTable'));
      if ($count >= $expected_count) {
        $this->assertGreaterThanOrEqual($expected_count, $count);
        return;
      }
      usleep(250000);
    } while (time() < $deadline);

    $this->fail('Expected ' . $expected_count . ' rendered CSV DataTable(s), found ' . $count . '.');
  }

  /**
   * Returns the CSV field wrapper that stores data-settings (post-init markup).
   */
  private function getCsvTableSettingsWrapper(int $index = 0) {
    $wrappers = $this->getSession()->getPage()->findAll('css', 'div[data-settings]');
    $this->assertArrayHasKey($index, $wrappers, 'Expected CSV table wrapper at index ' . $index . '.');
    return $wrappers[$index];
  }

  /**
   * Waits until the CSV table is initialized and interactive.
   */
  private function waitForCsvTableReady(bool $expect_initial_rows = TRUE, string $initial_row_text = 'Alpha Office'): void {
    $this->waitForCsvTables();
    $this->assertSession()->waitForElement('css', 'button.csv-field-search-submit', 30);

    if (!$expect_initial_rows) {
      return;
    }

    $this->waitForCsvTableRowText($initial_row_text);
  }

  /**
   * Whether any table body row contains the given text.
   */
  private function csvTableBodyContainsText(string $text): bool {
    foreach ($this->getSession()->getPage()->findAll('css', 'table.dataTable tbody tr') as $row) {
      if (str_contains($row->getText(), $text)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Waits until a row with the given text appears in the CSV table body.
   */
  private function waitForCsvTableRowText(string $text, int $timeout = 30): void {
    $deadline = time() + $timeout;
    do {
      if ($this->csvTableBodyContainsText($text)) {
        return;
      }
      usleep(250000);
    } while (time() < $deadline);

    $this->fail('Timed out waiting for CSV table row containing: ' . $text);
  }

  /**
   * Waits until no table body row contains the given text.
   */
  private function waitForCsvTableRowTextAbsent(string $text, int $timeout = 30): void {
    $deadline = time() + $timeout;
    do {
      if (!$this->csvTableBodyContainsText($text)) {
        return;
      }
      usleep(250000);
    } while (time() < $deadline);

    $this->fail('Timed out waiting for CSV table row to disappear: ' . $text);
  }

  /**
   * Waits until filtered table text appears after a search submit.
   */
  private function waitForCsvTableText(string $text, int $timeout = 30): void {
    $this->assertSession()->waitForText($text, $timeout);
    $this->waitForCsvTableRowText($text, $timeout);
  }

  /**
   * Sets the search value and submits the CSV table search form.
   */
  private function searchCsvTable(string $search_term, ?string $absent_row_text = NULL): void {
    $assert = $this->assertSession();
    $search_input = $assert->elementExists('css', '.dt-search input[type="search"], .dataTables_filter input[type="search"]');
    $search_input->setValue($search_term);
    $this->submitCsvTableSearch($search_input);
    $this->waitForCsvTableRowText($search_term);
    if ($absent_row_text !== NULL) {
      $this->waitForCsvTableRowTextAbsent($absent_row_text);
    }
  }

  /**
   * Asserts pagination and length controls have unique aria-labelledby values.
   */
  private function assertUniqueCsvTableLandmarkLabels(int $expected_table_count): void {
    $page = $this->getSession()->getPage();

    $pagination_navs = $page->findAll('css', '.dt-paging nav[aria-labelledby], .dataTables_paginate nav[aria-labelledby]');
    $length_selects = $page->findAll('css', '.dt-length select[aria-labelledby], .dataTables_length select[aria-labelledby]');

    $this->assertCount($expected_table_count, $pagination_navs);
    $this->assertCount($expected_table_count, $length_selects);

    $pagination_labels = array_map(static function ($element) {
      return $element->getAttribute('aria-labelledby');
    }, $pagination_navs);
    $length_labels = array_map(static function ($element) {
      return $element->getAttribute('aria-labelledby');
    }, $length_selects);

    $this->assertCount($expected_table_count, array_unique($pagination_labels));
    $this->assertCount($expected_table_count, array_unique($length_labels));

    foreach ($pagination_labels as $labelledby) {
      $this->assertNotEmpty($labelledby);
    }

    $this->assertSession()->elementNotExists('css', '.dt-paging nav[aria-label="pagination"], .dataTables_paginate nav[aria-label="pagination"]');
  }

  /**
   * Returns sorted unique page-length option values from length selects.
   */
  private function getPageLengthOptionValues(): array {
    $values = [];
    foreach ($this->getSession()->getPage()->findAll('css', '.dt-length select option, .dataTables_length select option') as $option) {
      $values[] = (int) $option->getValue();
    }
    $values = array_values(array_unique($values));
    sort($values);
    return $values;
  }

  /**
   * Counts visible body rows in the first rendered CSV DataTable.
   */
  private function countVisibleCsvTableBodyRows(): int {
    return count($this->getSession()->getPage()->findAll('css', 'table.dataTable.display tbody tr, table.dataTable tbody tr'));
  }

  /**
   * Clicks a paging control when overlays intercept native WebDriver clicks.
   */
  private function clickCsvTablePagingNext(): void {
    $this->getSession()->executeScript(
      "var el = document.querySelector('.dt-paging-button.next:not(.disabled), .dt-paging .next:not(.disabled), .dataTables_paginate .next:not(.disabled)'); if (el) { el.click(); }"
    );
  }

  /**
   * Submits CSV table search (custom Search button or Enter fallback).
   */
  private function submitCsvTableSearch($search_input): void {
    $page = $this->getSession()->getPage();
    if ($page->find('css', 'button.csv-field-search-submit')) {
      // CI overlays (e.g. ed11y) can intercept native WebDriver clicks.
      $this->getSession()->executeScript(
        "var btn = document.querySelector('button.csv-field-search-submit'); if (btn) { btn.click(); }"
      );
      return;
    }
    // Fallback when the custom submit button is not present.
    $search_input->keyDown(13);
    $search_input->keyUp(13);
  }

  /**
   * Creates an org page node containing a CSV table paragraph.
   */
  private function createOrgPageWithCsvTable(Paragraph $section, string $title) {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => $title,
      'field_organization_sections' => [
        [
          'entity' => $section,
        ],
      ],
      'status' => 1,
    ]);
    return $node;
  }

  /**
   * Ensures csv table renders with default-like settings.
   */
  public function testCsvFlowDefaultVariation(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createCsvFile(
      'csv-default.csv',
      "Name,Website\nMass.gov,https://www.mass.gov\nExample,https://www.example.com\n"
    );
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Default');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Default');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $assert->pageTextContains('Example');
    $table = $assert->elementExists('css', '.dataTable.display');
    $settings = $table->getAttribute('data-settings');
    $this->assertStringContainsString('"pageLength":5', $settings);
    $this->assertStringContainsString('"lengthChange":1', $settings);
    $this->assertStringContainsString('"searching":1', $settings);
    $this->assertStringContainsString('"download":1', $settings);
    $this->assertStringContainsString('"responsive":"childRow"', $settings);
  }

  /**
   * Ensures csv table renders with non-default variation.
   */
  public function testCsvFlowAlternativeVariation(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createCsvFile(
      'csv-alt.csv',
      "Name,Website\nMass.gov,https://www.mass.gov\nExample,https://www.example.com\n"
    );
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 0,
      'hideSearchingData' => 1,
      'searchLabel' => 'Search by organization',
      'pageLength' => 10,
      'lengthChange' => 0,
      'responsive' => 'childRowImmediate',
      'download' => 0,
      'urls' => [
        'autolink' => 1,
        'urlColumnNumber' => '2',
      ],
    ], 'CSV Alternative');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Alternative');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $assert->pageTextContains('Example');
    $table = $assert->elementExists('css', '.dataTable.display');
    $settings = $table->getAttribute('data-settings');
    $this->assertStringContainsString('"pageLength":10', $settings);
    $this->assertStringContainsString('"lengthChange":0', $settings);
    $this->assertStringContainsString('"searching":0', $settings);
    $this->assertStringContainsString('"download":0', $settings);
    $this->assertStringContainsString('"responsive":"childRowImmediate"', $settings);
    $this->assertStringContainsString('"autolink":1', $settings);
    $this->assertStringContainsString('"urlColumnNumber":"2"', $settings);
    $this->assertStringContainsString('"searchLabel":"Search by organization"', $settings);
  }

  /**
   * Ensures end users can search within rendered CSV tables.
   */
  public function testCsvFlowSearchInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-search.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 10,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Search');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Search Interaction');

    $this->drupalGet('node/' . $node->id());

    $this->waitForCsvTableReady();
    $this->searchCsvTable('Unique Agency', 'Alpha Office');

    $this->assertSession()->pageTextContains('Unique Agency');
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'));
  }

  /**
   * Ensures end users can change page length and paginate rows.
   */
  public function testCsvFlowPaginationInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-pagination.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Pagination');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Pagination Interaction');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $page = $this->getSession()->getPage();
    $assert->pageTextContains('Alpha Office');
    $assert->pageTextNotContains('Unique Agency');

    $length_select = $assert->elementExists('css', '.dataTables_length select, .dt-length select');
    $length_select->selectOption('10');
    $this->waitForCsvTableText('Kappa Office');

    $assert->pageTextContains('Kappa Office');
    $assert->pageTextNotContains('Unique Agency');

    $next_button = $page->find('css', '.dataTables_paginate .next:not(.disabled), .dt-paging .next:not(.disabled), .dt-paging-button.next:not(.disabled)');
    if ($next_button) {
      $this->clickCsvTablePagingNext();
      $this->waitForCsvTableText('Unique Agency');
      $assert->pageTextContains('Unique Agency');
    }
  }

  /**
   * Ensures hide-until-search behavior works for end users.
   */
  public function testCsvFlowHideUntilSearchInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-hide-search.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'hideSearchingData' => 1,
      'searchLabel' => 'Search by agency',
      'pageLength' => 10,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Hide Search');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Hide Search Interaction');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $this->waitForCsvTableReady(FALSE);
    $settings = $this->getCsvTableSettingsWrapper()->getAttribute('data-settings');
    $this->assertStringContainsString('"hideSearchingData":1', $settings);
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'));

    $this->searchCsvTable('Unique Agency', 'Alpha Office');
    $assert->pageTextContains('Unique Agency');
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'));
  }

  /**
   * Ensures control visibility and autolink behavior follow settings.
   */
  public function testCsvFlowControlsAndAutolinkInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createCsvFile(
      'csv-controls-autolink.csv',
      "Name,LinkText,URL\nAlpha,Visit Alpha,https://www.mass.gov/alpha\nBeta,Visit Beta,https://www.mass.gov/beta\n"
    );
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 0,
      'pageLength' => 5,
      'lengthChange' => 0,
      'responsive' => 'childRowImmediate',
      'download' => 0,
      'urls' => [
        'autolink' => 1,
        'urlColumnNumber' => '3',
      ],
    ], 'CSV Controls Autolink');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Controls And Autolink');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');

    // Searching and length controls should be absent when disabled.
    $assert->elementNotExists('css', '.dataTables_filter input[type="search"], .dt-search input[type="search"]');
    $assert->elementNotExists('css', '.dataTables_length select, .dt-length select');

    // Download link should be removed when download setting is off.
    $assert->linkNotExists('CSV Controls Autolink Download CSV');

    // URL autolink should render link text from the previous column.
    $assert->linkExists('Visit Alpha');
    $assert->linkExists('Visit Beta');
  }

  /**
   * Ensures responsive child-row controls are usable in narrow viewport.
   */
  public function testCsvFlowResponsiveChildRowInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createWideCsvFile('csv-responsive.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Responsive');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Responsive Child Row');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $this->getSession()->resizeWindow(480, 900, 'current');
    $assert->waitForElement('css', 'table.dataTable tbody tr td.dtr-control');
    $assert->elementExists('css', 'table.dataTable tbody tr td.dtr-control');
  }

  /**
   * Ensures hidden responsive column headers are not keyboard focusable.
   */
  public function testCsvFlowHiddenResponsiveHeadersNotFocusable(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createWideCsvFile('csv-responsive-hidden-headers.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Responsive Header Focus');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Hidden Responsive Headers');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dt-container, .dataTables_wrapper');
    $this->getSession()->resizeWindow(360, 900, 'current');

    $hidden_headers = [];
    $deadline = time() + 20;
    do {
      $hidden_headers = $this->getSession()->getPage()->findAll(
        'css',
        'table.dataTable thead th.dtr-hidden, table.dataTable thead th[style*="display: none"]'
      );
      if (!empty($hidden_headers)) {
        break;
      }
      usleep(500000);
    } while (time() < $deadline);

    $this->assertNotEmpty($hidden_headers, 'Expected at least one responsive-hidden column header at narrow width.');

    foreach ($hidden_headers as $header) {
      $this->assertSame('-1', $header->getAttribute('tabindex'));
      foreach ($header->findAll('css', '.dt-column-order') as $sort_control) {
        $tabindex = $sort_control->getAttribute('tabindex');
        if ($tabindex !== NULL) {
          $this->assertSame('-1', $tabindex);
        }
      }
    }
  }

  /**
   * Ensures first column is rendered as table row headers.
   */
  public function testCsvFlowFirstColumnRowHeaderInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-row-header.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'firstColumnRowHeader' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Row Header');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow First Column Row Header');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $table = $assert->elementExists('css', '.dataTable.display');
    $settings = $table->getAttribute('data-settings');
    $this->assertStringContainsString('"firstColumnRowHeader":1', $settings);
    $assert->elementExists('css', 'table.dataTable tbody tr th');
    $assert->pageTextContains('Alpha Office');
  }

  /**
   * Ensures first column defaults to regular data cells when unchecked.
   */
  public function testCsvFlowFirstColumnRowHeaderDefaultsUnchecked(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-row-header-default-unchecked.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Row Header Default Unchecked');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow First Column Row Header Default Unchecked');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $table = $assert->elementExists('css', '.dataTable.display');
    $settings = $table->getAttribute('data-settings');
    $this->assertStringNotContainsString('"firstColumnRowHeader":1', $settings);
    $assert->elementNotExists('css', 'table.dataTable tbody tr th');
    $assert->elementExists('css', 'table.dataTable tbody tr td');
    $assert->pageTextContains('Alpha Office');
  }

  /**
   * Ensures download link points to the CSV and returns expected content.
   */
  public function testCsvFlowDownloadActionCorrectness(): void {
    $this->drupalLogin($this->createAdminUser());

    $csv_contents = "Name,Code\nDownload Test,UNIQUE_DOWNLOAD_MARKER\n";
    $file = $this->createCsvFile('csv-download.csv', $csv_contents);
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Download');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Download Action');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $download_link = $assert->elementExists('css', '.dataTable.display a[href$=".csv"]');
    $href = $download_link->getAttribute('href');
    $this->assertNotEmpty($href);

    $path = parse_url($href, PHP_URL_PATH);
    $this->drupalGet($path);
    $assert->responseContains('UNIQUE_DOWNLOAD_MARKER');
  }

  /**
   * Ensures autolink supports multiple URL columns.
   */
  public function testCsvFlowAutolinkMultipleColumnsInteraction(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createCsvFile(
      'csv-multicol-autolink.csv',
      "Agency,Profile Text,Profile URL,Docs Text,Docs URL\nAlpha Office,Alpha Profile,https://www.mass.gov/alpha-profile,Alpha Docs,https://www.mass.gov/alpha-docs\nBeta Office,Beta Profile,https://www.mass.gov/beta-profile,Beta Docs,https://www.mass.gov/beta-docs\n"
    );
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 5,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 1,
        'urlColumnNumber' => '3,5',
      ],
    ], 'CSV Multi Autolink');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow Autolink Multi Column');

    $this->drupalGet('node/' . $node->id());

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $assert->linkExists('Alpha Profile');
    $assert->linkExists('Alpha Docs');
    $assert->linkExists('Beta Profile');
    $assert->linkExists('Beta Docs');
  }

  /**
   * Ensures search state does not persist after reload.
   */
  public function testCsvFlowStatePersistenceOptional(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-state-persistence.csv');
    $csv_table = $this->createCsvTableParagraph($file, [
      'searching' => 1,
      'pageLength' => 10,
      'lengthChange' => 1,
      'responsive' => 'childRow',
      'download' => 1,
      'urls' => [
        'autolink' => 0,
      ],
    ], 'CSV Persistence');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV Flow State Persistence');
    $path = 'node/' . $node->id();

    $this->drupalGet($path);

    $this->waitForCsvTableReady();
    $this->searchCsvTable('Unique Agency', 'Alpha Office');
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'));

    $this->drupalGet($path);
    $this->waitForCsvTableReady();
    $this->assertTrue($this->csvTableBodyContainsText('Alpha Office'));
  }

  /**
   * A11Y0000412: Pagination and length controls use unique aria-labelledby per table.
   */
  public function testCsvA11yMultipleTablesUniqueLandmarkLabels(): void {
    $this->drupalLogin($this->createAdminUser());

    $file_one = $this->createCsvFile(
      'csv-a11y-multi-one.csv',
      "Name,Website\nAlpha,https://www.mass.gov/alpha\n"
    );
    $file_two = $this->createCsvFile(
      'csv-a11y-multi-two.csv',
      "Name,Website\nBeta,https://www.mass.gov/beta\n"
    );

    $table_one = $this->createCsvTableParagraph(
      $file_one,
      $this->defaultCsvTableSettings(['tableLabel' => 'First Table']),
      'First Table'
    );
    $table_two = $this->createCsvTableParagraph(
      $file_two,
      $this->defaultCsvTableSettings(['tableLabel' => 'Second Table']),
      'Second Table'
    );
    $section = $this->createSectionParagraph([$table_one, $table_two]);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Multiple Tables');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTables(2);
    $this->assertUniqueCsvTableLandmarkLabels(2);
  }

  /**
   * A11Y0000375: Skip link appears for tables with links and targets table footer.
   */
  public function testCsvA11ySkipLinkForTablesWithLinks(): void {
    $this->drupalLogin($this->createAdminUser());

    $with_links = $this->createCsvFile(
      'csv-a11y-skip-with-links.csv',
      "Name,Website\nAlpha,https://www.mass.gov/alpha\n"
    );
    $without_links = $this->createCsvFile(
      'csv-a11y-skip-no-links.csv',
      "Name,Department\nAlpha,Health\n"
    );

    $linked_table = $this->createCsvTableParagraph(
      $with_links,
      $this->defaultCsvTableSettings([
        'urls' => [
          'autolink' => 1,
          'urlColumnNumber' => '2',
        ],
      ]),
      'Linked Table'
    );
    $plain_table = $this->createCsvTableParagraph(
      $without_links,
      $this->defaultCsvTableSettings(),
      'Plain Table'
    );
    $section = $this->createSectionParagraph([$linked_table, $plain_table]);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Skip Link');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTables(2);

    $skip_links = $this->getSession()->getPage()->findAll('css', 'a.csv-field-skip-link');
    $this->assertCount(1, $skip_links, 'Only the table with hyperlinks should have a skip link.');
    $this->assertStringContainsString('Linked Table', $skip_links[0]->getText());
    $this->assertStringContainsString('footer', strtolower($skip_links[0]->getText()));

    $footer_target_id = ltrim($skip_links[0]->getAttribute('href'), '#');
    $this->assertNotEmpty($footer_target_id);
    $this->assertSession()->elementExists('css', '.csv-field-skip-footer#' . $footer_target_id);
  }

  /**
   * A11Y0000344: Search uses a button; rows stay visible until search is submitted.
   */
  public function testCsvA11ySearchButtonShowsRowsByDefault(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-a11y-search-button.csv');
    $csv_table = $this->createCsvTableParagraph(
      $file,
      $this->defaultCsvTableSettings(['pageLength' => 10]),
      'Search Button Table'
    );
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Search Button');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTableReady();

    $assert = $this->assertSession();
    $assert->elementExists('css', 'button.csv-field-search-submit');
    $this->assertTrue($this->csvTableBodyContainsText('Alpha Office'));

    $search_input = $assert->elementExists('css', '.dt-search input[type="search"], .dataTables_filter input[type="search"]');
    $search_input->setValue('Unique Agency');
    $this->assertTrue($this->csvTableBodyContainsText('Alpha Office'), 'Typing alone must not filter results.');

    $this->searchCsvTable('Unique Agency', 'Alpha Office');
    $assert->pageTextContains('Unique Agency');
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'));
  }

  /**
   * A11Y0000344: Hide-until-search still hides the table until search is submitted.
   */
  public function testCsvA11yHideUntilSearchStillRequiresSubmit(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-a11y-hide-until-search.csv');
    $csv_table = $this->createCsvTableParagraph($file, $this->defaultCsvTableSettings([
      'hideSearchingData' => 1,
      'searchLabel' => 'Search by agency',
    ]), 'Hide Until Search Table');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Hide Until Search');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTableReady(FALSE);

    $assert = $this->assertSession();
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'));

    $search_input = $assert->elementExists('css', '.dt-search input[type="search"], .dataTables_filter input[type="search"]');
    $search_input->setValue('Unique Agency');
    $this->assertFalse($this->csvTableBodyContainsText('Alpha Office'), 'Typing alone must not reveal rows when hide-until-search is enabled.');

    $this->searchCsvTable('Unique Agency');
    $assert->pageTextContains('Unique Agency');
    $this->assertTrue($this->csvTableBodyContainsText('Unique Agency'));
  }

  /**
   * Download link uses generic text, filename in title, and table-specific aria-label.
   */
  public function testCsvA11yDownloadLinkTextAndAria(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createCsvFile(
      'csv-a11y-download.csv',
      "Name,Code\nRow,VALUE\n"
    );
    $csv_table = $this->createCsvTableParagraph($file, $this->defaultCsvTableSettings([
      'tableLabel' => 'Quarterly Report',
    ]), 'Quarterly Report');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Download Link');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTables();

    $assert = $this->assertSession();
    $download_link = $assert->elementExists('css', 'div[data-settings] a[download]');
    $this->assertSame('Download table data as CSV', $download_link->getText());
    $this->assertStringContainsString('csv-a11y-download.csv', $download_link->getAttribute('title'));
    $this->assertStringContainsString(
      'Download table data as CSV for Quarterly Report',
      $download_link->getAttribute('aria-label')
    );
    $this->assertStringNotContainsString('csv-a11y-download.csv', $download_link->getText());
  }

  /**
   * Page length is limited to 5, 10, or 15; legacy values normalize to 15.
   */
  public function testCsvA11yPageLengthOptionsAndNormalization(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-a11y-page-length.csv');
    $csv_table = $this->createCsvTableParagraph($file, $this->defaultCsvTableSettings([
      'pageLength' => 25,
    ]), 'Page Length Table');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Page Length');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTables();

    $settings = $this->getCsvTableSettingsWrapper()->getAttribute('data-settings');
    $this->assertStringContainsString('"pageLength":15', $settings);
    $this->assertSame([5, 10, 15], $this->getPageLengthOptionValues());
  }

  /**
   * Default page length is 5 rows when no legacy high value is stored.
   */
  public function testCsvA11yDefaultPageLengthIsFive(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-a11y-default-page-length.csv');
    $csv_table = $this->createCsvTableParagraph($file, $this->defaultCsvTableSettings(), 'Default Page Length Table');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Default Page Length');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTables();

    $settings = $this->getCsvTableSettingsWrapper()->getAttribute('data-settings');
    $this->assertStringContainsString('"pageLength":5', $settings);
    $this->assertSame(5, $this->countVisibleCsvTableBodyRows());
  }

  /**
   * Pagination buttons keep native button semantics (no role="link").
   */
  public function testCsvA11yPaginationButtonsNoLinkRole(): void {
    $this->drupalLogin($this->createAdminUser());

    $file = $this->createLargeCsvFile('csv-a11y-pagination-role.csv');
    $csv_table = $this->createCsvTableParagraph($file, $this->defaultCsvTableSettings(), 'Pagination Role Table');
    $section = $this->createSectionParagraph($csv_table);
    $node = $this->createOrgPageWithCsvTable($section, 'CSV A11Y Pagination Role');

    $this->drupalGet('node/' . $node->id());
    $this->waitForCsvTables();

    $assert = $this->assertSession();
    $assert->elementsCount('css', '.dt-paging button[role="link"], .dataTables_paginate button[role="link"]', 0);
    $assert->elementExists('css', '.dt-paging button, .dataTables_paginate button');
  }

}
