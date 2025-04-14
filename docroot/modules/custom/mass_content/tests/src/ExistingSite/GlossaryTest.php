<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests Glossary.
 */
class GlossaryTest extends MassExistingSiteBase {

  const QAG_PATHS = [
    "/memorandum/qag-advisory",
    "/report/qag-binderreport",
    "/lists/qag-curatedlist",
    "/mandate/qag-decisionmandate",
    "/executive-orders/no-1-qag-executiveorder",
    "/forms/qag-formwithoutfileuploads",
    "/how-to/qag-request-help-with-a-computer-problem",
    "/info-details/qag-info-detail-with-landing-page-features",
    "/locations/qag-locationgeneral1",
    "/news/qag-newsnews",
    "/orgs/qag-digital-services",
    "/regulations/900-CMR-2-qag-regulation-title",
    "/trial-court-rules/qag-rulesofcourt",
    "/qag-service1"
  ];

  /**
   * Glossary node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $glossary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->glossary = $this->createNode([
      'type' => 'glossary',
      'field_terms' => [
        [
          'key' => 'term',
          'value' => 'definition',
        ],
        [
          'key' => 'term2',
          'value' => 'definition2',
        ],
        [
          'key' => 'Lorem',
          'value' => 'Ipsum',
        ],
      ],
      'moderation_state' => 'published',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Zero out any remaining references to prevent memory leaks.
    $this->glossary = NULL;
  }

  private function createEditor() {
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->activate();
    $editor->save();
    return $editor;
  }

  public function testGlossaryReferenceInContent() {
    $this->drupalLogin($this->createEditor());
    $paths = self::QAG_PATHS;
    $session = $this->getSession();
    $url = $this->glossary->toUrl()->toString();
    $session->visit($url);
    $this->assertEquals(200, $session->getStatusCode(), 'Failed to retrieve ' . $url);
    $page = $session->getPage();
    $this->assertStringContainsString('Glossary', $page->getContent());
    foreach ($paths as $path) {
      // Edit the node.
      $session->visit($path . '/edit');
      $this->assertEquals(200, $session->getStatusCode(), 'Failed to retrieve ' . $path . '/edit');
      $page = $session->getPage();
      $page->findField('edit-field-glossaries-0-target-id')->setValue($this->glossary->label() . ' (' . $this->glossary->id() . ') - Glossary');
      $page->findButton('Save')->submit();
      $this->assertEquals($this->baseUrl . $path, $session->getCurrentUrl());

      // Ensure glossaries are in the drupalSettings json
      $drupalSettings = $page->find('css', '[data-drupal-selector="drupal-settings-json"]');
      $this->assertStringContainsString('glossaries', $drupalSettings->getText());
    }
  }

}
