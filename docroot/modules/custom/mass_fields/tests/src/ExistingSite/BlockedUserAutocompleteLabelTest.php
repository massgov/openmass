<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_fields\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies blocked users are clearly marked in entity autocomplete labels.
 */
class BlockedUserAutocompleteLabelTest extends MassExistingSiteBase {

  /**
   * Tests blocked-user lifecycle in entity autocomplete results.
   */
  public function testBlockedUserAutocompleteLabel(): void {
    $name_prefix = 'blocked_user_label_' . $this->randomMachineName(6);

    // Step 1: Create an author and content authored by that user.
    $author_user = $this->createUser([], $name_prefix . '_author');
    $author_user->activate();
    $author_user->save();

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Blocked author coverage ' . $name_prefix,
      'uid' => $author_user->id(),
      'field_info_detail_overview' => '<p>Autocomplete coverage body</p>',
    ]);
    $this->assertEquals($author_user->id(), (int) $node->getOwnerId());

    // Step 2: Block that author user.
    $author_user->block();
    $author_user->save();

    // Step 3: Create another user who searches in autocomplete.
    $editor_user = $this->createUser(['reference blocked users']);
    $this->drupalLogin($editor_user);

    // Additional active account with similar prefix for mixed results.
    $active_user = $this->createUser([], $name_prefix . '_active');
    $active_user->activate();
    $active_user->save();

    $matcher = \Drupal::service('mass_fields.autocomplete_matcher');
    $matches = $matcher->getMatches('user', 'default', [], $name_prefix);

    $labels = array_column($matches, 'label');
    $labels_as_string = implode("\n", $labels);

    $this->assertStringContainsString($active_user->getDisplayName(), $labels_as_string);
    $this->assertStringContainsString($author_user->getDisplayName() . ' (blocked)', $labels_as_string);
  }

}
