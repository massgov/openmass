<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Path;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = Path::join(dirname(__DIR__), 'changelogs');
// Iterate over Changelog files
$finder = Finder::create()
  ->in($path)
  ->name('*.yml')
  ->notName('template.yml');

foreach($finder as $file) {
  // An Exception is thrown if a file is not parseable.
  echo "Parsing " . $file->getPathname() . "\n";
  Yaml::parseFile($file->getPathname());
}
