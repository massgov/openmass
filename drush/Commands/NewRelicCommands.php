<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;

#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class NewRelicCommands extends DrushCommands {

  #[CLI\Option(name: 'nrname', description: 'New Relic transaction name.')]
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: '*')]
  public function optionsetNrName($options = ['nrname' => self::REQ]) {
  }

  /**
   * Set NR name.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: '*')]
  public function name($result, CommandData $commandData) {
    if (!extension_loaded('newrelic')) {
      $this->logger()->info('New Relic extension is not loaded.');
      return;
    }
    $annotationData = $commandData->annotationData();
    $commandName = $annotationData['command'];
    $name = $commandData->input()->hasOption('nrname') ? $commandData->input()->getOption('nrname') : $commandName;
    $success = newrelic_name_transaction("cli.drush.$name");
    if (!$success) {
      $this->logger()->error('Failed to set New Relic transaction name.');
    }
    else {
      $this->logger()->info('New Relic transaction name set.');
    }
  }

}
