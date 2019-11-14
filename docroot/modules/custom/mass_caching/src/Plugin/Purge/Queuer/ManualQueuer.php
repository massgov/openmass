<?php

namespace Drupal\mass_caching\Plugin\Purge\Queuer;

use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;

/**
 * Can queue anything, but it doesn't do anything automatically.
 *
 * @PurgeQueuer(
 *   id = "manual",
 *   label = @Translation("URL Queuer"),
 *   description = @Translation("Queues urls when invoked through custom code."),
 *   enable_by_default = false,
 * )
 */
class ManualQueuer extends QueuerBase {

}
