<?php

/**
 * This file is included very early. See our composer.json and
 * https://getcomposer.org/doc/04-schema.md#files
 */


use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Webmozart\PathUtil\Path;

loadEnv();

/**
 * Load any .env file. See /.env.example.
 */
function loadEnv() {
  // The Dotenv package is only included on development environments.
  /** @noinspection ClassConstantCanBeUsedInspection */
  if (class_exists('\Dotenv\Dotenv')) {
    $dotenv = new Dotenv(Path::join(__DIR__, '.ddev'));
    try {
      $dotenv->load();
    }
    catch (InvalidPathException $e) {
      // Do nothing. Only local dev environments have .env files.
    }
  }
}
