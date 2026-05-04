<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies editors can set blocked/active users in media Authored by field.
 */
class BlockedAuthorsMediaEditTest extends ExistingSiteSelenium2DriverTestBase {

  use MediaCreationTrait;

  /**
   * Tests manual authored-by changes on media edit form.
   */
  public function testEditorCanChangeMediaAuthorToBlockedAndActive(): void {
    $blocked_author = $this->createUser([], 'blocked_author_' . $this->randomMachineName(6));
    $blocked_author->activate();
    $blocked_author->save();
    $blocked_author->block();
    $blocked_author->save();

    $active_author = $this->createUser([], 'active_author_' . $this->randomMachineName(6));
    $active_author->activate();
    $active_author->save();

    $editor = $this->createUser([], 'editor_user_' . $this->randomMachineName(6));
    $editor->addRole('editor');
    $editor->save();

    $editable_media_id = $this->createDocumentMediaForAuthor(
      'Manual authored-by media ' . $this->randomMachineName(6),
      (int) $active_author->id()
    );

    $this->drupalLogin($editor);

    $this->drupalGet("media/$editable_media_id/edit");
    $this->selectAutocompleteAuthor(
      'edit-uid-0-target-id',
      $blocked_author->getAccountName(),
      $blocked_author->getAccountName()
    );
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->fieldValueEquals(
      'edit-uid-0-target-id',
      $blocked_author->getAccountName() . ' (' . $blocked_author->id() . ') - User'
    );

    $this->drupalGet("media/$editable_media_id/edit");
    $this->selectAutocompleteAuthor(
      'edit-uid-0-target-id',
      $active_author->getAccountName(),
      $active_author->getAccountName()
    );
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->fieldValueEquals(
      'edit-uid-0-target-id',
      $active_author->getAccountName() . ' (' . $active_author->id() . ') - User'
    );
  }

  /**
   * Selects an autocomplete option by typing and clicking dropdown item.
   */
  private function selectAutocompleteAuthor(string $field_id, string $search, string $option_text): void {
    $field = $this->assertSession()->fieldExists($field_id);
    if (!$field->isVisible()) {
      $this->getSession()->executeScript(sprintf(
        "(function (fieldId) {
          const input = document.getElementById(fieldId);
          if (!input) {
            return;
          }
          const details = input.closest('details');
          if (details) {
            details.open = true;
          }
        })(%s);",
        json_encode($field_id)
      ));
      $this->getSession()->wait(
        8000,
        "(() => { const el = document.getElementById(" . json_encode($field_id) . "); return !!el && el.offsetParent !== null; })()"
      );
      $field = $this->assertSession()->fieldExists($field_id);
    }
    $this->getSession()->executeScript(sprintf(
      "(function (fieldId, searchTerm) {
        const input = document.getElementById(fieldId);
        if (!input) {
          return;
        }
        input.focus();
        input.value = searchTerm;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', bubbles: true }));
        input.dispatchEvent(new KeyboardEvent('keyup', { key: 'a', bubbles: true }));
      })(%s, %s);",
      json_encode($field_id),
      json_encode($search)
    ));

    $escaped = json_encode($option_text);
    $this->getSession()->wait(
      8000,
      "document.querySelectorAll('ul.ui-autocomplete li').length > 0"
    );
    $this->getSession()->wait(
      8000,
      "Array.from(document.querySelectorAll('ul.ui-autocomplete li')).some(li => li.textContent.includes($escaped))"
    );

    $option = $this->getSession()->getPage()->find(
      'xpath',
      "(//ul[contains(@class, 'ui-autocomplete')]//li)[1]"
    );
    $this->assertNotNull($option);
    $option->click();
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
