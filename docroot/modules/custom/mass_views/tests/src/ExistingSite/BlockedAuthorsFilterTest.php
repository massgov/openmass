<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\user\Entity\Role;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Verifies blocked-user author filtering behavior in Documents admin view.
 */
class BlockedAuthorsFilterTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Ensure editors can filter by blocked users in "Authored by".
   */
  public function testEditorCanUseBlockedAuthorsFilter(): void {
    $editor_role = Role::load('editor');
    $this->assertNotNull($editor_role);
    $this->assertTrue(
      $editor_role->hasPermission('reference blocked users'),
      'Editor role must include permission to reference blocked users.'
    );

    $author = $this->createUser([], 'blocked_author_' . $this->randomMachineName(6));
    $author->activate();
    $author->save();

    $active_author = $this->createUser([], 'active_author_' . $this->randomMachineName(6));
    $active_author->activate();
    $active_author->save();

    $this->createDocumentMediaForAuthor(
      'Blocked author document ' . $this->randomMachineName(6),
      (int) $author->id()
    );
    $active_author_title = 'Active author document ' . $this->randomMachineName(6);
    $editable_media_id = $this->createDocumentMediaForAuthor(
      $active_author_title,
      (int) $active_author->id()
    );

    // Simulate former employee account lifecycle.
    $author->block();
    $author->save();

    $editor = $this->createUser([], 'editor_user_' . $this->randomMachineName(6));
    $editor->addRole('editor');
    $editor->save();
    $this->drupalLogin($editor);

    $this->drupalGet('admin/ma-dash/documents');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('edit-uid');

    // Filter by blocked author in exposed "Authored by" filter.
    $this->submitForm(
      [
        'uid' => $author->getAccountName() . ' (' . $author->id() . ')',
      ],
      'Filter',
      'views-exposed-form-all-documents-page-1'
    );

    $this->assertSession()->pageTextContains('Blocked author document');
    $this->assertSession()->pageTextNotContains($active_author_title);

    // Also verify active users are still referenceable in the same field.
    $this->submitForm(
      [
        'uid' => $active_author->getAccountName() . ' (' . $active_author->id() . ')',
      ],
      'Filter',
      'views-exposed-form-all-documents-page-1'
    );

    $this->assertSession()->pageTextContains($active_author_title);
    $this->assertSession()->pageTextNotContains('Blocked author document');

    // Keep one editable media id for JS workflow coverage in companion test.
    $this->assertGreaterThan(0, $editable_media_id);
  }

  /**
   * Creates a published document media item for a specific author.
   */
  private function createDocumentMediaForAuthor(string $title, int $author_id): int {
    $destination = 'public://' . $this->randomMachineName(12) . '.txt';
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    \Drupal::service('file_system')->copy($src, $destination, TRUE);

    $media = $this->createMedia([
      'bundle' => 'document',
      'title' => $title,
      'field_title' => $title,
      'uid' => $author_id,
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
    ]);

    return (int) $media->id();
  }

}
