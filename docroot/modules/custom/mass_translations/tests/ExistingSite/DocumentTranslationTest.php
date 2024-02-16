<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\LoginTrait\LoginTrait;

class DocumentTranslationTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use LoginTrait;

  private $editor;
  private $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = User::create(['name' => $this->randomMachineName()]);
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->editor = $user;

    // Create a "Llama" media item.
    file_put_contents('public://llama-43.txt', 'Test');
    $this->file = File::create([
      'uri' => 'public://llama-43.txt',
    ]);
  }

  /**
   * Helper method to create curated_list node.
   */
  public function createCuratedListNode($desc_type): EntityInterface {
    return $this->createNode([
      'type' => 'curated_list',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_curatedlist_list_section' => Paragraph::create([
        'type' => 'list_static',
        'field_liststatic_title' => $this->randomMachineName(),
        'field_liststatic_items' => Paragraph::create([
          'type' => 'list_item_document',
          'field_listitemdoc_desc_type' => $desc_type,
          'field_listitemdoc_desc_manual' => 'List item manual description',
          'field_liststaticdoc_item' => $this->getMedia(),
        ]),
      ]),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getMedia(): EntityInterface {
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $media = $this->createMedia([
      'bundle' => 'document',
      'name' => 'Test Document',
      'field_title' => 'Test Document',
      'field_document_listing_desc' => 'Test Document description',
      'field_organizations' => [$org_node],
      'field_upload_file' => [$this->file],
      'moderation_state' => 'published',
    ]);

    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
    $translation = $this->createMedia([
      'bundle' => 'document',
      'name' => 'Test Document',
      'field_title' => 'Test Document',
      'field_document_listing_desc' => 'Translated Document description',
      'field_organizations' => [$org_node],
      'field_upload_file' => [$this->file],
      'field_media_english_version' => [$media],
      'moderation_state' => 'published',
      'langcode' => $langcode,
    ]);

    return $translation;
  }

  /**
   * Retrieve the href value for translated link.
   */
  public function testMediaHasTranslation() {
    $this->drupalLogin($this->editor);
    $entity = $this->getMedia();
    $this->drupalGet($entity->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', '.ma__listing-table__container')->getText();
    $this->assertStringContainsString($entity->language()->getName(), $element, 'Language not found');
    $tabs = $page->find('css', '.primary-tabs')->getText();
    $this->assertStringContainsString('Translations', $tabs, 'No Translations tab was found');
  }

  /**
   * Test Download link view mode of media is rendered correctly.
   */
  public function testHasTranslationDownloadLink() {
    $this->drupalLogin($this->editor);
    // Passing the none option to skip descriptions.
    $node = $this->createCuratedListNode('none');
    $this->drupalGet($node->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Node page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'a.lang-toggle')->getText();
    $this->assertStringContainsString('Translate labels', $element, 'Translate labels link not found');

    $translation_label = $page->find('css', '.ma__inline-links__container > li.ma__inline-links__item > a')->getText();
    $this->assertStringContainsString('English', $translation_label, 'Translation links not found');
  }

  /**
   * Test Linked description view mode of media is rendered correctly.
   */
  public function testHasTranslationLinkedDesc() {
    $this->drupalLogin($this->editor);
    // Passing the linked option which will pick the description
    // from referenced media.
    $node = $this->createCuratedListNode('linked');
    $this->drupalGet($node->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Node page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'a.lang-toggle')->getText();
    $this->assertStringContainsString('Translate labels', $element, 'Translate labels link not found');

    $translation_label = $page->find('css', '.ma__inline-links__container > li.ma__inline-links__item > a')->getText();
    $this->assertStringContainsString('English', $translation_label, 'Translation links not found');

    $description = $page->find('css', '.ma__download-link__description .ma__rich-text ')->getText();
    $this->assertStringContainsString('Translated Document description', $description, 'Document description not found');
  }

  /**
   * Test Manual description view mode of media is rendered correctly.
   */
  public function testHasTranslationManualDesc() {
    $this->drupalLogin($this->editor);
    // Passing the manual option which will pick the description
    // from the field_listitemdoc_desc_manual field.
    $node = $this->createCuratedListNode('manual');
    $this->drupalGet($node->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Node page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'a.lang-toggle')->getText();
    $this->assertStringContainsString('Translate labels', $element, 'Translate labels link not found');

    $translation_label = $page->find('css', '.ma__inline-links__container > li.ma__inline-links__item > a')->getText();
    $this->assertStringContainsString('English', $translation_label, 'Translation links not found');

    $description = $page->find('css', '.ma__download-link__description .ma__rich-text ')->getText();
    $this->assertStringContainsString('List item manual description', $description, 'Document description not found');
  }

}
