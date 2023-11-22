<?php

declare(strict_types=1);

namespace MassGov\Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateCircleciToken {

  public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo) {
    $commandInfo->addAnnotation(name: \Drush\Commands\DeployCommands::VALIDATE_CIRCLECI_TOKEN, content: 'unused');
  }

}
