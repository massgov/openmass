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
   * Set NR name before command is run.
   */
  #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: '*')]
  public function preName($result, CommandData $commandData) {
    $this->name($result, $commandData, 'start');
  }

  /**
   * Set NR name after command is run.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: '*')]
  public function postName($result, CommandData $commandData) {
    $this->name($result, $commandData, 'finish');
  }

  /**
   * Set NR name.
   */
  private function name($result, CommandData $commandData, $prefix = '') {
    if (!extension_loaded('newrelic')) {
      $this->logger()->debug('New Relic extension is not loaded.');
      return;
    }
    $annotationData = $commandData->annotationData();
    $name = $annotationData['command'];
    if ($commandData->input()->hasOption('nrname') && $commandData->input()->getOption('nrname')) {
      $name = $commandData->input()->getOption('nrname');
    }
    $success = newrelic_name_transaction("cli.drush.$prefix.$name");
    if (!$success) {
      $this->logger()->error('Failed to set New Relic transaction name.');
    }
    else {
      $this->logger()->info('New Relic transaction name set.');
    }
  }

}
