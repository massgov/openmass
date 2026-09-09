<?php

namespace MassGov\Dtt;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Base class for Mass.gov JavaScript ExistingSite tests.
 */
abstract class MassExistingSiteSelenium2DriverTestBase extends ExistingSiteSelenium2DriverTestBase {
  use AuthTrait;

}
