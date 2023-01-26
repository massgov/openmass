<?php

//if (isset($GLOBALS['request'])) {
//  $scriptName = $GLOBALS['request']->server->get('SCRIPT_NAME');
//  $path = getenv('TUGBOAT_DEFAULT_SERVICE_URL_PATH');
//  $scriptName = $path . $scriptName;
//  $GLOBALS['request']->server->set('SCRIPT_NAME', $scriptName);
//}

/**
 * No longer recommended. See https://docs.tugboatqa.com/faq/common-questions/#can-i-password-protect-my-preview-urls
 *
 * Password protect Tugboat environments.
 *
 * @see https://docs.acquia.com/articles/password-protect-your-non-production-environments-acquia-hosting#phpfpm
 */
//$agent = $_SERVER['HTTP_USER_AGENT'];
//if (php_sapi_name() !== 'cli' && !str_contains($agent, 'Chrome-Lighthouse') && !str_contains($agent, 'HeadlessChrome')) {
//  $username = getenv('LOWER_ENVIR_AUTH_USER');
//  $password = getenv('LOWER_ENVIR_AUTH_PASS');
//  $is_oauth = strpos($_SERVER['REQUEST_URI'], '/oauth/token') !== FALSE;
//  $is_endpoint = strpos($_SERVER['REQUEST_URI'], '/api/v1/') !== FALSE;
//  if (!$is_oauth && !$is_endpoint && !(isset($_SERVER['PHP_AUTH_USER']) && ($_SERVER['PHP_AUTH_USER']==$username && $_SERVER['PHP_AUTH_PW']==$password))) {
//    header('WWW-Authenticate: Basic realm="This site is protected"');
//    header('HTTP/1.0 401 Unauthorized');
//    // Fallback message when the user presses cancel / escape
//    echo 'Access denied';
//    exit;
//  }
//}
