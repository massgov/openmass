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
      $this->logger()->debug('New Relic extension is not loaded.');
      return;
    }
    $annotationData = $commandData->annotationData();
    $name = $annotationData['command'];
    if ($commandData->input()->hasOption('nrname') && $commandData->input()->getOption('nrname')) {
      $name = $commandData->input()->getOption('nrname');
    }
    newrelic_record_custom_event("cliDrushCommand", ["name" => "cli.drush.$name"]);
    $this->logger()->info('New Relic custom event recorded.');
  }

}
