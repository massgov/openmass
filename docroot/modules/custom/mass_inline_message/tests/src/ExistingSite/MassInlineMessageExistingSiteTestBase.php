<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use Drupal\mass_inline_message\MassInlineMessageRenderer;
use Drupal\Tests\mass_inline_message\Traits\InlineMessageMarkupTestTrait;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Base class for Message box ExistingSite (non-JS) tests.
 */
abstract class MassInlineMessageExistingSiteTestBase extends MassExistingSiteBase {

  use InlineMessageMarkupTestTrait;

  /**
   * Returns the Message box renderer service.
   */
  protected function messageBoxRenderer(): MassInlineMessageRenderer {
    return \Drupal::service('mass_inline_message.renderer');
  }

}
