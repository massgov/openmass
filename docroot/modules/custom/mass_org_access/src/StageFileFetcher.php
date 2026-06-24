<?php

declare(strict_types=1);

namespace Drupal\mass_org_access;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\stage_file_proxy\DownloadManagerInterface;

/**
 * Pulls a missing public file from the stage_file_proxy origin on demand.
 *
 * The bulk backfill resaves media entities, which regenerates thumbnails and
 * therefore needs the source file on disk. On a database copied from
 * production the rows exist but the files do not, so the save throws a
 * FileNotExistsException. This service fetches the file from the configured
 * origin (production) first — the same mechanism stage_file_proxy uses to
 * serve missing files over HTTP, but triggered programmatically since a drush
 * save never makes an HTTP request.
 *
 * Never fetches on the production Acquia environment (the files are already
 * there and the origin is production itself), and is a no-op when
 * stage_file_proxy is not installed.
 */
class StageFileFetcher {

  public function __construct(
    private readonly ?DownloadManagerInterface $downloadManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Ensures a public:// file exists locally, fetching it from prod if missing.
   *
   * @param string $uri
   *   The file URI, e.g. "public://2017-05/report.pdf".
   *
   * @return bool
   *   TRUE if the file is present locally afterwards (already there or just
   *   fetched); FALSE if it could not be made available.
   */
  public function ensureLocalCopy(string $uri): bool {
    // Already on disk — nothing to do. realpath() returns a path string even
    // for a missing file, so test file_exists() on it explicitly.
    $real = $this->fileSystem->realpath($uri);
    if ($real && file_exists($real)) {
      return TRUE;
    }

    // Never proxy on production: the file should be there, and the origin is
    // production itself.
    if (($_ENV['AH_SITE_ENVIRONMENT'] ?? '') === 'prod') {
      return FALSE;
    }

    // stage_file_proxy disabled — nothing to fetch with.
    if ($this->downloadManager === NULL) {
      return FALSE;
    }

    // Only the public scheme is mirrored by stage_file_proxy.
    if (StreamWrapperManager::getScheme($uri) !== 'public') {
      return FALSE;
    }

    $config = $this->configFactory->get('stage_file_proxy.settings');
    $server = $config->get('origin');
    if (!$server) {
      return FALSE;
    }

    return $this->downloadManager->fetch(
      rtrim((string) $server, '/ '),
      trim((string) ($config->get('origin_dir') ?? '')),
      StreamWrapperManager::getTarget($uri),
      ['verify' => (bool) $config->get('verify')],
    );
  }

}
