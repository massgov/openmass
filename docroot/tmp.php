<?php

use Drupal\Core\DrupalKernel;
use Drupal\prod_no_redirect\ProdNoRedirectDrupalKernel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Force Drupal to use trusted Akamai header even without knowing all the possible IPs before it.
 * As suggested by https://www.drupal.org/project/reverse_proxy_header
 */
//if (isset($_SERVER['HTTP_TRUE_CLIENT_IP'])) {
//  $_SERVER['HTTP_X_FORWARDED_FOR'] = $_SERVER['HTTP_TRUE_CLIENT_IP'];
//}

$autoloader = require_once 'autoload.php';

$kernel = new ProdNoRedirectDrupalKernel('prod', $autoloader);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);

print \Drupal::request()->getClientIp();

print_r($_SERVER);
