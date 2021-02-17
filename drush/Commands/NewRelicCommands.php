<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;

class NewRelicCommands extends DrushCommands
{

  /**
   * @hook option *
   *
   * @option nrname New Relic transaction name.
   */
  public function optionsetNrName($options = ['nrname' => self::REQ])
  {
  }

  /**
   * Set NR name
   *
   * @hook post-command *
   */
  public function name($result, CommandData $commandData)
  {
    if (!extension_loaded('newrelic')) {
      return;
    }
    $annotationData = $commandData->annotationData();
    $commandName = $annotationData['command'];
    $name = $commandData->input()->getOption('nrname') ?: $commandName;
    newrelic_name_transaction("cli.drush.$name");
  }
}
