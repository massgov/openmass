<?php

declare(strict_types=1);

namespace Drupal\mass_media;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ensures managed files exist locally by fetching from the SFP origin.
 */
class StageFileProxyHelper {

  /**
   * Constructs a StageFileProxyHelper.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
    protected ?DownloadManagerInterface $downloadManager = NULL,
  ) {}

  /**
   * Ensures a managed file exists on the local filesystem.
   */
  public function ensureLocalFile(FileInterface $file): bool {
    return $this->ensureLocalFileUri($file->getFileUri());
  }

  /**
   * Ensures a file URI exists on the local filesystem.
   */
  public function ensureLocalFileUri(string $uri): bool {
    if ($this->localFileExists($uri)) {
      return TRUE;
    }

    if (!$this->isEnabled()) {
      return FALSE;
    }

    $relative_path = StreamWrapperManager::getTarget($uri);
    if ($relative_path === FALSE || $relative_path === '') {
      return FALSE;
    }

    $settings = $this->configFactory->get('stage_file_proxy.settings');
    $server = $settings->get('origin');
    $origin_dir = trim($settings->get('origin_dir') ?? 'sites/default/files');
    $options = ['verify' => $settings->get('verify')];

    try {
      $fetched = $this->downloadManager->fetch($server, $origin_dir, $relative_path, $options);
    }
    catch (\Throwable $exception) {
      $this->logger->warning('Stage File Proxy fetch failed for @uri: @message', [
        '@uri' => $uri,
        '@message' => $exception->getMessage(),
      ]);
      return FALSE;
    }

    if (!$fetched) {
      return FALSE;
    }

    if (StreamWrapperManager::getScheme($uri) === 'private') {
      $public_uri = 'public://' . $relative_path;
      if (!$this->localFileExists($public_uri)) {
        return FALSE;
      }

      $private_dir = $this->fileSystem->dirname($uri);
      if (!$this->fileSystem->prepareDirectory($private_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        return FALSE;
      }

      if (!$this->fileSystem->copy($public_uri, $uri, FileExists::Replace)) {
        return FALSE;
      }
    }

    return $this->localFileExists($uri);
  }

  /**
   * Whether a file URI exists on the local filesystem.
   */
  public function fileExistsLocally(string $uri): bool {
    return $this->localFileExists($uri);
  }

  /**
   * Whether Stage File Proxy is configured to fetch from an origin.
   */
  protected function isEnabled(): bool {
    if (!$this->downloadManager) {
      return FALSE;
    }

    $origin = $this->configFactory->get('stage_file_proxy.settings')->get('origin');
    return !empty($origin);
  }

  /**
   * Whether the URI resolves to an existing local file.
   */
  protected function localFileExists(string $uri): bool {
    $realpath = $this->fileSystem->realpath($uri);
    return !empty($realpath) && file_exists($realpath);
  }

}
