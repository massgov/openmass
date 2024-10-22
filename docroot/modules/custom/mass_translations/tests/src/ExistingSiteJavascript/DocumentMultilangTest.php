<?php

namespace Drupal\Tests\mass_translations\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Multilanguage documents tests.
 */
class DocumentMultilangTest extends ExistingSiteSelenium2DriverTestBase {

  use MediaCreationTrait;
  use LoginTrait;

  const LANGCODE = 'es';
  private $editor;
  private $binder;
  private $translatedLangLabelEN;
  private $translatedLangLabel;
  private $media;
  private $translatedMedia;
  private $file;
  private $random;
  private $page;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Generate and store a random string for tests.
    $this->random = $this->randomString();

    // Create a user with editor role.
    $user = $this->createUser();
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->editor = $user;

    // Create a "Llama" media item.
    file_put_contents('public://llama-44.txt', 'Test');
    $this->file = File::create([
      'uri' => 'public://llama-44.txt',
    ]);

    // Generate media item in English.
    $this->generateMedia();

    // Generate node.
    $this->generateBinder();

  }

  /**
   * Helper method to generate node of type 'binder'.
   */
  public function generateBinder() {
    // Create a node of type 'binder'.
    $this->binder = $this->createNode([
      'type' => 'binder',
      'title' => $this->randomMachineName(),
      'field_downloads' => $this->media->id(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

  /**
   * Helper method to generate media item.
   */
  public function generateMedia() {

    $this->media = $this->createMedia([
      'bundle' => 'document',
      'field_title' => "Test Document en $this->random",
      'field_upload_file' => [$this->file],
      'moderation_state' => 'published',
      'langcode' => 'en',
    ]);
  }

  /**
   * Helper method to generate translated version of media item.
   */
  public function generateTranslatedMedia() {
    $langcode = self::LANGCODE;
    $this->translatedMedia = $this->createMedia([
      'bundle' => 'document',
      'field_title' => "Test Document $langcode $this->random",
      'field_upload_file' => [$this->file],
      'field_media_english_version' => [$this->media],
      'moderation_state' => 'published',
      'langcode' => $langcode,
    ]);
    $predefined = \Drupal::languageManager()->getStandardLanguageList();
    // Example: Spanish.
    $this->translatedLangLabelEN = $predefined[$langcode][0];
    // Example: Español.
    $this->translatedLangLabel = $predefined[$langcode][1];
  }

  /**
   * Test single media rendering.
   */
  public function runMainMediaCheck() {
    $this->drupalLogin($this->editor);
    $this->drupalGet($this->binder->toUrl());
    $this->page = $this->getSession()->getPage();
    // Check if Media rendered on the page.
    $media_element = $this->page->find('css', '.ma__download-link__file-link')->getText();
    $this->assertStringContainsString($this->media->field_title->value, $media_element, 'Media not found on the page.');

    // Check if Media original language is rendered.
    $language_label = $this->page->find('css', '.ma__download-link__file-spec')->getText();
    $this->assertStringContainsString('English', $language_label, 'Document default language not found.');
  }

  /**
   * Test single media rendering without translation links.
   */
  public function testNoTranslationLinks() {
    $this->runMainMediaCheck();
    // Check if translation link are not rendered.
    $this->assertSession()->elementNotExists('css', ".ma__download-link .ma__inline-links .ma__inline-links__item");

  }

  /**
   * Test single media rendering with translation links.
   */
  public function testHasTranslationLinks() {
    // Generate the translated version of the media.
    $this->generateTranslatedMedia();

    // We rerun the initial test to make sure
    // everything is rendered the same way.
    $this->runMainMediaCheck();

    // Check if translation link is rendered and has correct value.
    $this->assertSession()->elementExists('css', ".ma__download-link .ma__inline-links .ma__inline-links__item");
    $translation_label = $this->page->find('css', '.ma__download-link .ma__inline-links .ma__inline-links__item:first-child')->getText();
    $this->assertStringContainsString($this->translatedLangLabel, $translation_label, 'Translated Document language label not found.');

    // Check if the "Translate labels" is rendered.
    $this->assertSession()->elementExists('css', ".ma__download-link .ma__inline-links .lang-toggle-container");

    // Check if the translation link has
    // attribute data-label with correct value.
    $this->assertSession()->elementAttributeContains('css', '.ma__download-link .ma__inline-links .ma__inline-links__item:first-child a', 'data-label', $this->translatedLangLabelEN);

    // Check if language toggle functionality works.
    $lang_toggle = $this->page->find('css', '.ma__download-link .ma__inline-links .lang-toggle-container .lang-toggle');
    $lang_toggle->click();
    $translation_label_en = $this->page->find('css', '.ma__download-link .ma__inline-links .ma__inline-links__item:first-child')->getText();
    $this->assertStringContainsString($this->translatedLangLabelEN, $translation_label_en, 'Translated Document language English version label not found.');

  }

}
