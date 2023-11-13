<?php

use Drupal\Core\DrupalKernel;
use Drupal\prod_no_redirect\ProdNoRedirectDrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once 'autoload.php';

$kernel = new ProdNoRedirectDrupalKernel('prod', $autoloader);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);

print \Drupal::request()->getClientIp();

print_r($_SERVER);
