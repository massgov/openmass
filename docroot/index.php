<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\prod_no_redirect\ProdNoRedirectDrupalKernel;

require_once 'autoload_runtime.php';

return static function () {
  return new ProdNoRedirectDrupalKernel('prod', require 'autoload.php');
};
