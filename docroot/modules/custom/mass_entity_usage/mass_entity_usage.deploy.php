<?php

/**
 * @file
 * Implementations of hook_deploy_NAME() for Mass Entity Usage.
 */

/**
 * Run the entity_usage recreate drush command.
 *
 * Create all entity usage statistics.
 */
function mass_entity_usage_deploy_run_entity_usage_command() {
  ini_set('memory_limit', '2048');
  $command = \Drupal::service('entity_usage.commands');
  $command->recreate(['use-queue' => TRUE, 'multi-pass' => FALSE, 'clear-multi-pass' => FALSE]);
}
