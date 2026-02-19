<?php

declare(strict_types=1);

if (!class_exists('DOMDocument')) {
  fwrite(STDERR, "DOM extension is required to parse --list-tests-xml output.\n");
  exit(1);
}

$root = dirname(__DIR__);

// No CLI flags – always use default grouped output.
$full = false;
$oneLine = false;
$outputTable = false;
$groupsOnly = false;
$noDocs = false;


// 1) Get all discovered tests (no execution)
// If this script is executed *inside* the ddev container, don't call `ddev exec` again.
$in_ddev_container = (bool) getenv('DDEV_PROJECT') || (bool) getenv('IS_DDEV_PROJECT') || file_exists('/etc/ddev/ddev-php-base');

$lines = [];
$code = 1;
$cmd = '';
// PHPUnit 11 requires a file path for --list-tests-xml
$xmlFile = sys_get_temp_dir() . '/phpunit-test-list.xml';
if (file_exists($xmlFile)) {
  unlink($xmlFile);
}
$phpunit_cmd = './vendor/bin/phpunit -c phpunit.xml.dist --list-tests-xml ' . escapeshellarg($xmlFile);
$cmd = $in_ddev_container ?  $phpunit_cmd: ('ddev exec ' . $phpunit_cmd);

// Capture STDERR too (phpunit sometimes prints to STDERR).
$lines = [];
$code = 0;
exec($cmd . ' 2>&1', $lines, $code);
// If XML mode was used, load XML from file instead of stdout
if (isset($xmlFile) && file_exists($xmlFile)) {
  $xmlContent = file_get_contents($xmlFile);
  if ($xmlContent !== false) {
    $lines = explode("\n", $xmlContent);
  }
}
// At this point `$lines` contains the raw output from `--list-tests`.

if ($code !== 0) {
  fwrite(STDERR, "Failed listing tests. Command was:\n{$cmd}\n\nOutput (first 120 lines):\n");
  $preview = array_slice($lines, 0, 120);
  foreach ($preview as $pl) {
    fwrite(STDERR, $pl . "\n");
  }
  exit(1);
}

// If nothing came back, print the raw output to help diagnose CI/container issues.
if (empty($lines)) {
  fwrite(STDERR, "No output from phpunit list command. Command was: {$cmd}\n");
  exit(1);
}

// Detect the common case where phpunit printed only headers (no Class::method lines).
$has_tests = false;
foreach ($lines as $l) {
  if (strpos($l, '::') !== false) {
    $has_tests = true;
    break;
  }
}
if (!$has_tests) {
  fwrite(STDERR, "phpunit list command returned output, but no discoverable tests were found.\n");
  fwrite(STDERR, "Command was: {$cmd}\n\n");
  fwrite(STDERR, "First 60 lines of raw output:\n");
  $preview = array_slice($lines, 0, 60);
  foreach ($preview as $pl) {
    fwrite(STDERR, $pl . "\n");
  }
  exit(1);
}

/**
 * Parse PHPUnit --list-tests-xml output into an array of tests with file/class/method.
 * Works across PHPUnit 10/11 variations by searching for nodes that have class+method.
 */
function parse_list_tests_xml(string $xml): array {
  $xml = trim($xml);
  if ($xml === '' || $xml[0] !== '<') {
    return [];
  }

  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  if (!$dom->loadXML($xml)) {
    return [];
  }

  $xp = new DOMXPath($dom);
  $xp->registerNamespace('t', 'https://xml.phpunit.de/testSuite');

  $tests = [];

  // PHPUnit 11 structure: <testClass name="..." file="...">
  //                         <testMethod name="..." id="Class::method" />
  $classNodes = $xp->query('//t:testClass');
  foreach ($classNodes as $classNode) {
    /** @var DOMElement $classNode */
    $className = $classNode->getAttribute('name');
    $file = $classNode->getAttribute('file');

    foreach ($classNode->getElementsByTagName('testMethod') as $methodNode) {
      /** @var DOMElement $methodNode */
      $methodName = $methodNode->getAttribute('name');

      $tests[] = [
        'class' => $className,
        'method' => $methodName,
        'file' => $file,
      ];
    }
  }

  return $tests;
}

/**
 * Extract the docblock for a method from a PHP file.
 * Uses token_get_all() so we don't accidentally capture code as a docblock.
 */
function extract_method_docblock_from_file(string $file, string $method): ?string {
  if ($file === '' || !is_file($file)) {
    return null;
  }

  $code = file_get_contents($file);
  if ($code === false) {
    return null;
  }

  // PHPUnit may include data-provider suffixes like testFoo#0.
  $method_base = preg_replace('/#.+$/', '', $method);

  $tokens = token_get_all($code);
  $lastDoc = null;
  $seenClass = false;

  for ($i = 0, $n = count($tokens); $i < $n; $i++) {
    $t = $tokens[$i];

    if (is_array($t) && $t[0] === T_DOC_COMMENT) {
      $lastDoc = $t[1];
      continue;
    }

    // Only consider methods once we're inside the first class in the file.
    if (is_array($t) && $t[0] === T_CLASS) {
      $seenClass = true;
      $lastDoc = $lastDoc; // keep
      continue;
    }

    if ($seenClass && is_array($t) && $t[0] === T_FUNCTION) {
      // Next non-whitespace token is the function name (or '&' then name).
      $j = $i + 1;
      while ($j < $n) {
        $tj = $tokens[$j];
        if (is_array($tj) && ($tj[0] === T_WHITESPACE || $tj[0] === T_COMMENT)) {
          $j++;
          continue;
        }
        if ($tj === '&') {
          $j++;
          continue;
        }
        break;
      }

      $nameTok = $tokens[$j] ?? null;
      if (is_array($nameTok) && $nameTok[0] === T_STRING) {
        $fn = $nameTok[1];
        if ($fn === $method_base) {
          return $lastDoc ? trim($lastDoc) : null;
        }
      }

      $lastDoc = null;
    }
  }

  return null;
}

/**
 * Extract the docblock for a class from a PHP file.
 * Uses token_get_all() so we don't accidentally capture code as a docblock.
 */
function extract_class_docblock_from_file(string $file, string $class): ?string {
  if ($file === '' || !is_file($file)) {
    return null;
  }

  $code = file_get_contents($file);
  if ($code === false) {
    return null;
  }

  $short = str_contains($class, '\\') ? substr($class, strrpos($class, '\\') + 1) : $class;

  $tokens = token_get_all($code);
  $lastDoc = null;

  for ($i = 0, $n = count($tokens); $i < $n; $i++) {
    $t = $tokens[$i];

    if (is_array($t) && $t[0] === T_DOC_COMMENT) {
      $lastDoc = $t[1];
      continue;
    }

    if (is_array($t) && $t[0] === T_CLASS) {
      // Next non-whitespace token is the class name.
      $j = $i + 1;
      while ($j < $n) {
        $tj = $tokens[$j];
        if (is_array($tj) && ($tj[0] === T_WHITESPACE || $tj[0] === T_COMMENT)) {
          $j++;
          continue;
        }
        break;
      }

      $nameTok = $tokens[$j] ?? null;
      if (is_array($nameTok) && $nameTok[0] === T_STRING) {
        $cn = $nameTok[1];
        if ($cn === $short) {
          return $lastDoc ? trim($lastDoc) : null;
        }
      }

      $lastDoc = null;
    }
  }

  return null;
}

require $root . '/vendor/autoload.php';

 /**
 * Parse a docblock into structured parts.
 */
function parse_docblock(?string $doc): array {
  if (!$doc) {
    return [
      'summary' => '',
      'description' => '',
      'testdox' => '',
      'groups' => [],
    ];
  }

  // Clean up comment syntax
  $doc = preg_replace('~/\*\*|\*/~', '', $doc);
  $doc = preg_replace('~^\s*\*\s?~m', '', $doc);
  $doc = trim($doc);

  $lines = explode("\n", $doc);

  $summary = '';
  $description = '';
  $testdox = '';
  $groups = [];

  foreach ($lines as $line) {
    $line = trim($line);

    if (str_starts_with($line, '@testdox')) {
      $testdox = trim(substr($line, 8));
    }
    elseif (str_starts_with($line, '@group')) {
      $groups[] = trim(substr($line, 6));
    }
  }

  // Extract summary + description (ignore annotation lines)
  $textLines = array_filter($lines, fn($l) => !str_starts_with(trim($l), '@'));
  $textLines = array_values($textLines);

  if (!empty($textLines)) {
    $summary = trim($textLines[0]);
    if (count($textLines) > 1) {
      $description = trim(implode("\n", array_slice($textLines, 1)));
    }
  }

  return [
    'summary' => $summary,
    'description' => $description,
    'testdox' => $testdox,
    'groups' => $groups,
  ];
}

echo "\n================ PHPUnit Test Inventory ================\n\n";

// Try to parse XML first.
$raw = implode("\n", $lines);
$tests = parse_list_tests_xml($raw);

// If XML parsing failed (for example if phpunit printed plain text), fall back to plain-text parsing.
if (!$tests) {
  $tests = [];
  foreach ($lines as $line) {
    $line = trim($line);
    if (!$line || strpos($line, '::') === false) {
      continue;
    }
    $line = ltrim($line, "- \t");
    [$class, $method] = explode('::', $line, 2);
    $tests[] = ['class' => trim($class), 'method' => trim($method), 'file' => ''];
  }
}

if (!$tests) {
  fwrite(STDERR, "[DEBUG] Could not parse any tests from phpunit output.\n");
  exit(1);
}

echo "Found " . count($tests) . " tests.\n\n";

function shorten_class(string $fqcn): string {
  $pos = strrpos($fqcn, '\\');
  return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
}

function normalize_path(string $file): string {
  if ($file === '') {
    return '';
  }
  // Prefer repo-relative-ish paths for readability.
  $prefix = '/var/www/html/';
  if (str_starts_with($file, $prefix)) {
    return substr($file, strlen($prefix));
  }
  return $file;
}

function truncate_cell(string $s, int $max): string {
  $s = trim($s);
  if ($max <= 0) {
    return '';
  }
  if (mb_strlen($s) <= $max) {
    return $s;
  }
  // Leave room for ellipsis.
  $cut = max(0, $max - 1);
  return rtrim(mb_substr($s, 0, $cut)) . '…';
}

function pad_cell(string $s, int $width): string {
  $len = mb_strlen($s);
  if ($len >= $width) {
    return $s;
  }
  return $s . str_repeat(' ', $width - $len);
}

function render_table(array $rows, array $headers, array $widths): void {
  // Build separators.
  $sepParts = [];
  foreach ($headers as $key => $_label) {
    $w = $widths[$key] ?? 10;
    $sepParts[] = str_repeat('-', $w);
  }

  // Header.
  $lineParts = [];
  foreach ($headers as $key => $label) {
    $lineParts[] = pad_cell($label, $widths[$key]);
  }
  echo implode(' | ', $lineParts) . "\n";
  echo implode('-+-', $sepParts) . "\n";

  // Rows.
  foreach ($rows as $r) {
    $out = [];
    foreach ($headers as $key => $_label) {
      $out[] = pad_cell($r[$key] ?? '', $widths[$key]);
    }
    echo implode(' | ', $out) . "\n";
  }
}

// Group tests by class for readability.
$byClass = [];
foreach ($tests as $t) {
  $byClass[$t['class']][] = $t;
}
ksort($byClass);

// Default (non-table) output mode.
foreach ($byClass as $class => $classTests) {
  $classShort = shorten_class($class);
  $fileForHeader = '';
  foreach ($classTests as $t) {
    if (!empty($t['file'])) {
      $fileForHeader = normalize_path($t['file']);
      break;
    }
  }

  // Optional class doc.
  $classDoc = ['summary' => '', 'description' => '', 'testdox' => '', 'groups' => []];
  if (!$noDocs && $fileForHeader) {
    $classDocRaw = extract_class_docblock_from_file('/var/www/html/' . $fileForHeader, $class);
    $classDoc = parse_docblock($classDocRaw);
  }

  echo "--------------------------------------------------------\n";
  echo "Class: {$class}\n";
  if ($fileForHeader) {
    echo "File:  {$fileForHeader}\n";
  }
  if ($full && $classDoc['summary']) {
    echo "About: {$classDoc['summary']}\n";
  }
  echo "\n";

  foreach ($classTests as $t) {
    $method = $t['method'];
    $file = normalize_path($t['file'] ?? '');

    $methodDoc = ['summary' => '', 'description' => '', 'testdox' => '', 'groups' => []];
    if (!$noDocs && $file) {
      $methodDocRaw = extract_method_docblock_from_file('/var/www/html/' . $file, $method);
      $methodDoc = parse_docblock($methodDocRaw);
    }

    $groups = $methodDoc['groups'] ? implode(', ', $methodDoc['groups']) : '';
    $label = $methodDoc['testdox'] ?: ($methodDoc['summary'] ?: '');

    if ($oneLine) {
      $bits = [];
      $bits[] = "- {$classShort}::{$method}";
      if ($label && !$groupsOnly) {
        $bits[] = "— {$label}";
      }
      if ($groups) {
        $bits[] = "[{$groups}]";
      }
      echo implode(' ', $bits) . "\n";
      continue;
    }

    echo "  - Method: {$method}\n";
    if ($label && !$groupsOnly) {
      echo "    Label:  {$label}\n";
    }
    if ($groups) {
      echo "    Groups: {$groups}\n";
    }
    if ($full && $methodDoc['description'] && !$groupsOnly) {
      echo "    Notes:\n";
      foreach (explode("\n", $methodDoc['description']) as $ln) {
        echo "      {$ln}\n";
      }
    }
    echo "\n";
  }
}

// Cleanup temp XML file
if (isset($xmlFile) && file_exists($xmlFile)) {
  unlink($xmlFile);
}
