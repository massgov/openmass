<?php

namespace Drupal\Tests\mass_translations\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
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

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a user with editor role.
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->editor = $user;

    // Create a "Llama" media item.
    file_put_contents('public://llama-44.txt', 'Test');
    $this->file = File::create([
      'uri' => 'public://llama-44.txt',
    ]);

    // Generate media items.
    $this->generateMedia();

    // Create a node if type 'binder'.
    $this->binder = $this->createNode([
      'type' => 'binder',
      'title' => $this->randomMachineName(),
      'field_downloads' => $this->media->id(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

  /**
   * Helper method to generate required media items.
   */
  public function generateMedia() {
    $langcode = self::LANGCODE;
    $random = $this->randomString();
    $media = $this->createMedia([
      'bundle' => 'document',
      'field_title' => "Test Document en $random",
      'field_upload_file' => [$this->file],
      'moderation_state' => 'published',
    ]);

    $translatedMedia = $this->createMedia([
      'bundle' => 'document',
      'field_title' => "Test Document $langcode $random",
      'field_upload_file' => [$this->file],
      'field_media_english_version' => [$media],
      'moderation_state' => 'published',
      'langcode' => $langcode,
    ]);
    $predefined = \Drupal::languageManager()->getStandardLanguageList();
    // Example: Spanish.
    $this->translatedLangLabelEN = $predefined[$langcode][0];
    // Example: Español.
    $this->translatedLangLabel = $predefined[$langcode][1];
    $this->media = $media;
    $this->translatedMedia = $translatedMedia;
  }

  /**
   * Test Multilanguage document functionality.
   */
  public function testHasTranslationLinks() {
    $this->drupalLogin($this->editor);
    $this->drupalGet($this->binder->toUrl());
    $page = $this->getSession()->getPage();

    // Check if Media rendered on the page.
    $media_element = $page->find('css', '.ma__download-link__file-link')->getText();
    $this->assertStringContainsString($this->media->field_title->value, $media_element, 'Media not found on the page.');

    // Check if Media original language is rendered.
    $language_label = $page->find('css', '.ma__download-link__file-spec')->getText();
    $this->assertStringContainsString('English', $language_label, 'Document default language not found.');

    // Check if translation link is rendered and has correct value.
    $this->assertSession()->elementExists('css', ".ma__download-link .ma__inline-links .ma__inline-links__item");
    $translation_label = $page->find('css', '.ma__download-link .ma__inline-links .ma__inline-links__item:first-child')->getText();
    $this->assertStringContainsString($this->translatedLangLabel, $translation_label, 'Translated Document language label not found.');

    // Check if the "Translate labels" is rendered.
    $this->assertSession()->elementExists('css', ".ma__download-link .ma__inline-links .lang-toggle-container");

    // Check if the translation link has attribute data-label with correct value.
    $this->assertSession()->elementAttributeContains('css','.ma__download-link .ma__inline-links .ma__inline-links__item:first-child a','data-label', $this->translatedLangLabelEN);

    // Check if language toggle functionality works.
    $lang_toggle = $page->find('css', '.ma__download-link .ma__inline-links .lang-toggle-container .lang-toggle');
    $lang_toggle->click();
    $translation_label_en = $page->find('css', '.ma__download-link .ma__inline-links .ma__inline-links__item:first-child')->getText();
    $this->assertStringContainsString($this->translatedLangLabelEN, $translation_label_en, 'Translated Document language English version label not found.');

  }

}
