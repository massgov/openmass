<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\Core\Render\Markup;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests InvalidImageSourceConstraint blocks save for unsupported image sources.
 */
class InvalidImageSourceConstraintValidationTest extends MassExistingSiteBase {
  use UserCreationTrait;

  /**
   * Creates and logs in a user with a specific role.
   */
  private function createAndLoginUser($role) {
    $user = $this->createUser();
    $user->addRole($role);
    $user->save();
    $this->drupalLogin($user);
  }

  /**
   * Validation message shown when invalid image source is detected.
   */
  private const VALIDATION_MESSAGE = 'One or more images in this field use an unsupported format. Please remove those images or re-add them using the Insert Image button.';

  /**
   * Save is blocked when rich text contains a data: image src.
   *
   * Uses a long base64 string so that if the value is filtered (data: stripped)
   * before validation, the mangled form still triggers the constraint.
   */
  public function testDataUriImageBlocksSave() {
    $base64 = str_repeat('A', 600);
    $html = '<p>Some text</p><img src="data:image/png;base64,' . $base64 . '" alt="Inline">';
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test invalid image source',
      'field_info_detail_overview' => Markup::create($html),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createAndLoginUser('administrator');
    $this->visit($node->toUrl()->toString() . '/edit');
    $this->getSession()->getPage()->pressButton('edit-submit');
    $page_contents = $this->getSession()->getPage()->getContent();

    $this->assertStringContainsString(self::VALIDATION_MESSAGE, $page_contents, 'Validation message should appear when data: image is present.');
  }

  /**
   * Save is blocked when rich text contains mangled base64 src (no data: prefix).
   */
  public function testMangledBase64ImageBlocksSave() {
    $mangled = 'image/png;base64,' . str_repeat('A', 500);
    $html = '<p>Content</p><img src="' . $mangled . '" alt="Broken">';
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test mangled base64 image',
      'field_info_detail_overview' => Markup::create($html),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createAndLoginUser('administrator');
    $this->visit($node->toUrl()->toString() . '/edit');
    $this->getSession()->getPage()->pressButton('edit-submit');
    $page_contents = $this->getSession()->getPage()->getContent();

    $this->assertStringContainsString(self::VALIDATION_MESSAGE, $page_contents, 'Validation message should appear when mangled base64 image src is present.');
  }

  /**
   * Save succeeds when rich text contains only normal image URLs.
   */
  public function testNormalImageUrlAllowsSave() {
    $html = '<p>Text</p><img src="https://www.mass.gov/sites/default/files/example.png" alt="OK">';
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test valid image URL',
      'field_info_detail_overview' => Markup::create($html),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createAndLoginUser('administrator');
    $this->visit($node->toUrl()->toString() . '/edit');
    $this->getSession()->getPage()->pressButton('edit-submit');
    $page_contents = $this->getSession()->getPage()->getContent();

    $this->assertStringNotContainsString(self::VALIDATION_MESSAGE, $page_contents, 'Validation message should not appear for normal https image URL.');
  }

  /**
   * Save succeeds when rich text has no images.
   */
  public function testNoImagesAllowsSave() {
    $html = '<p>Just text, no images.</p>';
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test no images',
      'field_info_detail_overview' => Markup::create($html),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createAndLoginUser('administrator');
    $this->visit($node->toUrl()->toString() . '/edit');
    $this->getSession()->getPage()->pressButton('edit-submit');
    $page_contents = $this->getSession()->getPage()->getContent();

    $this->assertStringNotContainsString(self::VALIDATION_MESSAGE, $page_contents, 'Validation message should not appear when there are no images.');
  }

}
