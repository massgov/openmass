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
      'Agency,ColA,ColB,ColC,ColD,ColE,ColF,ColG',
      'Alpha Office,Very long value A1,Very long value B1,Very long value C1,Very long value D1,Very long value E1,Very long value F1,Very long value G1',
      'Beta Office,Very long value A2,Very long value B2,Very long value C2,Very long value D2,Very long value E2,Very long value F2,Very long value G2',
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
   * Creates a section_long_form paragraph wrapping a csv_table paragraph.
   */
  private function createSectionParagraph(Paragraph $csvTable): Paragraph {
    $section = Paragraph::create([
      'type' => 'section_long_form',
      'field_section_long_form_content' => [
        [
          'entity' => $csvTable,
        ],
      ],
    ]);
    $section->save();
    $this->markEntityForCleanup($section);
    return $section;
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

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $search_input = $assert->elementExists('css', '.dataTables_filter input[type="search"], .dt-search input[type="search"]');
    $search_input->setValue('Unique Agency');
    $this->getSession()->wait(5000, "document.body.innerText.indexOf('Unique Agency') !== -1");

    $assert->pageTextContains('Unique Agency');
    $assert->pageTextNotContains('Alpha Office');
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
    $this->getSession()->wait(5000, "document.body.innerText.indexOf('Kappa Office') !== -1");

    $assert->pageTextContains('Kappa Office');
    $assert->pageTextNotContains('Unique Agency');

    $next_button = $page->find('css', '.dataTables_paginate .next, .dt-paging .next, .dt-paging-button.next');
    if ($next_button) {
      // Use JS click to avoid intermittent overlay interception in CI/headless.
      $this->getSession()->executeScript(
        "var el = document.querySelector('.dataTables_paginate .next, .dt-paging .next, .dt-paging-button.next'); if (el) { el.click(); }"
      );
      $this->getSession()->wait(5000, "document.body.innerText.indexOf('Unique Agency') !== -1");
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
    $assert->waitForElement('css', '.dataTables_wrapper');
    $table = $assert->elementExists('css', '.dataTable.display');
    $settings = $table->getAttribute('data-settings');
    $this->assertStringContainsString('"hideSearchingData":1', $settings);
    $search_input = $assert->elementExists('css', '.dt-search input[type="search"], .dataTables_filter input[type="search"]');
    $search_input->setValue('Unique Agency');
    $search_button = $this->getSession()->getPage()->find('css', 'button.dt-search-submit');
    if ($search_button) {
      $search_button->click();
    }
    else {
      // Fallback for DataTables default search mode (no custom submit button).
      $search_input->keyDown(13);
      $search_input->keyUp(13);
    }

    $this->getSession()->wait(5000, "document.body.innerText.indexOf('Unique Agency') !== -1");
    $assert->pageTextContains('Unique Agency');
    $assert->pageTextNotContains('Alpha Office');
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
    $this->getSession()->wait(3000);

    $control = $this->getSession()->getPage()->find('css', 'table.dataTable tbody tr td.dtr-control');
    $this->assertNotNull($control, 'Responsive control column should be visible at narrow width.');
    $control->click();
    $assert->waitForElement('css', 'table.dataTable tbody tr.child');
    $assert->elementExists('css', 'table.dataTable tbody tr.child');
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
    $assert->elementExists('css', 'table.dataTable tbody tr th');
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

    $assert = $this->assertSession();
    $assert->waitForElement('css', '.dataTables_wrapper');
    $search_input = $assert->elementExists('css', '.dataTables_filter input[type="search"], .dt-search input[type="search"]');
    $search_input->setValue('Unique Agency');
    $this->getSession()->wait(5000, "document.body.innerText.indexOf('Unique Agency') !== -1");
    $assert->pageTextNotContains('Alpha Office');

    $this->drupalGet($path);
    $assert->waitForElement('css', '.dataTables_wrapper');
    $assert->pageTextContains('Alpha Office');
  }

}
