<?php

declare(strict_types=1);

// CVE-2026-109618 and CVE-2026-109619 require a patched PHP runtime.
// Check the executing binary; Composer's platform.php is a resolver override.
$minimumVersions = [
    3 => '8.3.36',
    4 => '8.4.27',
    5 => '8.5.12',
];
$minimum = PHP_MAJOR_VERSION === 8 ? ($minimumVersions[PHP_MINOR_VERSION] ?? null) : null;
if ($minimum === null || version_compare(PHP_VERSION, $minimum, '<')) {
    fwrite(STDERR, 'Unsupported or vulnerable PHP runtime: ' . PHP_VERSION . PHP_EOL);
    fwrite(STDERR, 'Install a patched release supported by composer.json before continuing.' . PHP_EOL);
    exit(1);
}

echo 'PHP security baseline satisfied: ' . PHP_VERSION . PHP_EOL;
