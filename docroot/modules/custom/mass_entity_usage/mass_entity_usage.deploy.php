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
  $command = \Drupal::service('entity_usage.commands');
  $command->recreate(['use-queue' => TRUE, 'batch-size' => 10000]);
}
