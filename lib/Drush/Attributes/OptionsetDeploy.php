<?php

declare(strict_types=1);

namespace MassGov\Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetDeploy {

  public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo) {
    $commandInfo->addOption('refresh-db', 'Copy the DB from Prod to replace target environment\'s DB.', [], false);
    $commandInfo->addOption('skip-maint', 'Skip maintenance mode enable/disable.', [], false);
    $commandInfo->addOption('cache-rebuild', 'Rebuild caches as needed during deployment.', [], true);
    $commandInfo->addOption('varnish', 'Purge Varnish fully at end of deployment. Otherwise, do minimalist purge.', [], false);
  }

}
