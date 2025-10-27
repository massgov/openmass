<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_friendly_redirects\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * @group mass_friendly_redirects
 */
final class FriendlyRedirectsServiceTest extends ExistingSiteBase {

  public function testPrefixManagerReturnsOptions(): void {
    /** @var \Drupal\mass_friendly_redirects\Service\PrefixManager $pm */
    $pm = \Drupal::service('mass_friendly_redirects.prefix_manager');

    // Ensure at least the vocab exists; options may be empty on a fresh DB.
    $this->assertSame('friendly_url_prefixes', $pm->getVocabularyMachineName());

    // Create a term and ensure it appears as an option.
    $tid = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create([
      'vid' => 'friendly_url_prefixes',
      'name' => 'qag',
      'status' => 1,
    ])->save();

    $opts = $pm->getPrefixOptions();
    $this->assertNotEmpty($opts);
    $this->assertContains('qag', array_values($opts));
  }
}
