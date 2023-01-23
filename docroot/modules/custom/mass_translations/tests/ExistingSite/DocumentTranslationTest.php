<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Service Details translation tests.
 */
class DocumentTranslationTest extends ExistingSiteBase {

  use MediaCreationTrait;
  use LoginTrait;

  private $editor;
  private $orgNode;
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

    $this->orgNode = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    // Create a "Llama" media item.
    file_put_contents('public://llama-43.txt', 'Test');
    $this->file = File::create([
      'uri' => 'public://llama-43.txt',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getMedia(): EntityInterface {
    $media = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Test Document',
      'field_organizations' => [$this->orgNode],
      'field_upload_file' => [$this->file],
      'moderation_state' => 'published',
    ]);

    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
    $translation = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Test Document',
      'field_organizations' => [$this->orgNode],
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
  public function testHasTranslation() {
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

}
