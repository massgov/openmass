<?php

/**
 * This file is included very early. See our composer.json and
 * https://getcomposer.org/doc/04-schema.md#files
 */


use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

loadEnv();

/**
 * Load any .env file. See /.env.example.
 */
function loadEnv() {
  $dotenv = new Dotenv(__DIR__);
  try {
    $dotenv->load();
  }
  catch (InvalidPathException $e) {
    // Do nothing. Only local dev environments have .env files.
  }
}
