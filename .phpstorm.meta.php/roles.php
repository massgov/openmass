<?php

declare(strict_types=1);

namespace PHPSTORM_META {

  registerArgumentsSet('roles',
    'anonymous',
    'authenticated',
    'author',
    'editor',
    'emergency_alert_publisher',
    'executive_orders',
    'redirect_creators',
    'content_team',
    'developer',
    'administrator',
    'tester',
    'doc_deletion',
    'd2d_redirect_manager',
    'data_administrator',
    'collection_administrator',
    'prototype_design_access',
    'mmg_editor',
  );
  expectedArguments(\Drupal\user\UserInterface::hasRole(), 0, argumentsSet('roles'));
  expectedArguments(\Drupal\user\UserInterface::addRole(), 0, argumentsSet('roles'));
  expectedArguments(\Drupal\user\UserInterface::removeRole(), 0, argumentsSet('roles'));

}
