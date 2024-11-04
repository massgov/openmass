<?php

/**
 * @file
 *   A bootstrap file for `phpunit` test runner.
 *
 * This bootstrap file from DTT is fast and customizable.
 *
 * If you get 'class not found' errors while running tests, you should copy this
 * file to a location inside your code-base --such as `/scripts`. Then add the
 * missing namespaces to the bottom of the copied field. Specify your custom
 * `bootstrap-fast.php` file as the bootstrap in `phpunit.xml`.
 *
 * Alternatively, use the bootstrap.php file, in this same directory, which is
 * slower but registers all the namespaces that Drupal tests expect.
 */

use Drupal\TestTools\PhpUnitCompatibility\PhpUnit8\ClassWriter;
use weitzman\DrupalTestTraits\AddPsr4;

[$finder, $class_loader] = AddPsr4::add();
$root = $finder->getDrupalRoot();

// Register more namespaces, as needed.
$class_loader->addPsr4('Drupal\Tests\mass_metatag\\', "$root/modules/custom/mass_metatag/tests/src");
$class_loader->addPsr4('Drupal\Tests\paragraphs\\', "$root/modules/contrib/paragraphs/tests/src");
$class_loader->addPsr4('Drupal\Tests\system\Functional\Cache\\', "$root/core/modules/system/tests/src/Functional/Cache");
$class_loader->addPsr4('Drupal\mass_schema_web_page\\', "$root/modules/custom/mass_schema_metatag/mass_schema_web_page/src");
