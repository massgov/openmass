<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\Tests\mass_inline_message\Traits\InlineMessageJavascriptTestTrait;
use Drupal\Tests\mass_inline_message\Traits\InlineMessageTestUserTrait;
use MassGov\Dtt\MassExistingSiteSelenium2DriverTestBase;

/**
 * Base class for Message box browser (ExistingSiteJavascript) tests.
 */
abstract class MassInlineMessageJavascriptTestBase extends MassExistingSiteSelenium2DriverTestBase {

  use InlineMessageJavascriptTestTrait;
  use InlineMessageTestUserTrait;

}
