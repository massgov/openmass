<?php

namespace Drupal\Tests\mass_entity_usage\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_entity_usage\Drush\Commands\MassEntityUsageCommands;
use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use Drush\Exceptions\UserAbortException;
use Drush\Log\DrushLoggerManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests the usage-regenerate command decision tree.
 *
 * An accidental full rebuild wipes all usage data and takes 1.5-2 days to
 * recover in production (the DP-46437 incident), so every branch of
 * recreate() is pinned here: which manager calls each input state is allowed
 * to make, and, just as important, which destructive calls it must never
 * make.
 *
 * @coversDefaultClass \Drupal\mass_entity_usage\Drush\Commands\MassEntityUsageCommands
 * @group mass_entity_usage
 */
class MassEntityUsageCommandsDecisionTest extends TestCase {

  /**
   * Builds the command with a mocked batch manager and console IO.
   */
  private function buildCommand($manager, bool $interactive = FALSE, bool $yes = FALSE): MassEntityUsageCommands {
    $command = new MassEntityUsageCommands(
      $manager,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(DateFormatterInterface::class),
    );
    $definition = new InputDefinition([
      new InputOption('yes', 'y', InputOption::VALUE_NONE),
    ]);
    $input = new ArrayInput($yes ? ['--yes' => TRUE] : [], $definition);
    $input->setInteractive($interactive);
    $command->setInput($input);
    $command->setOutput(new BufferedOutput());
    $logger = new DrushLoggerManager();
    $logger->add('null', new NullLogger());
    $command->setLogger($logger);
    return $command;
  }

  /**
   * Tests --reset --force starts a fresh run without prompting.
   */
  public function testResetWithForceStartsFreshRun(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->expects($this->once())->method('beginFreshEnqueueRun');
    $manager->expects($this->once())->method('populateQueue')->willReturn(FALSE);

    $command = $this->buildCommand($manager);
    $command->recreate(['batch-size' => 10, 'force' => TRUE, 'reset' => TRUE]);
  }

  /**
   * Tests --reset -y starts a fresh run without prompting.
   */
  public function testResetWithYesStartsFreshRun(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->expects($this->once())->method('beginFreshEnqueueRun');
    $manager->expects($this->once())->method('populateQueue')->willReturn(FALSE);

    $command = $this->buildCommand($manager, FALSE, TRUE);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => TRUE]);
  }

  /**
   * Tests --reset aborts in non-interactive mode before touching any state.
   *
   * The abort must happen before beginFreshEnqueueRun(): a reorder that
   * clears the queue first and prompts second would destroy saved progress
   * even when the operator declines.
   */
  public function testResetNonInteractiveAbortsBeforeClearingState(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->expects($this->never())->method('beginFreshEnqueueRun');
    $manager->expects($this->never())->method('populateQueue');

    $command = $this->buildCommand($manager);
    $this->expectException(UserAbortException::class);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => TRUE]);
  }

  /**
   * Tests an interrupted run resumes without prompting or wiping state.
   */
  public function testInterruptedRunResumesWithoutFreshStart(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->method('hasInterruptedProgress')->willReturn(TRUE);
    $manager->method('getProgressSummary')->willReturn(['node' => 'in progress 5/10']);
    $manager->expects($this->once())->method('prepareResume');
    $manager->expects($this->never())->method('beginFreshEnqueueRun');
    $manager->expects($this->once())->method('populateQueue')->willReturn(FALSE);

    $command = $this->buildCommand($manager);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => FALSE]);
  }

  /**
   * Tests a run completed within 24 hours exits without enqueueing.
   */
  public function testRecentCompletionExitsWithoutEnqueue(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->method('hasInterruptedProgress')->willReturn(FALSE);
    $manager->method('wasEnqueueCompletedRecently')->willReturn(TRUE);
    $manager->method('getEnqueueCompletedAt')->willReturn(1000000);
    $manager->expects($this->never())->method('beginFreshEnqueueRun');
    $manager->expects($this->never())->method('populateQueue');

    $command = $this->buildCommand($manager);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => FALSE]);
  }

  /**
   * Tests completed progress without a completion flag stamps and exits.
   */
  public function testCompletedProgressWithoutFlagStampsAndExits(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->method('hasInterruptedProgress')->willReturn(FALSE);
    $manager->method('wasEnqueueCompletedRecently')->willReturn(FALSE);
    $manager->method('getEnqueueCompletedAt')->willReturn(NULL);
    $manager->method('isAllEnqueueCompleted')->willReturn(TRUE);
    $manager->expects($this->once())->method('markEnqueueCompleted');
    $manager->expects($this->never())->method('beginFreshEnqueueRun');
    $manager->expects($this->never())->method('populateQueue');

    $command = $this->buildCommand($manager);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => FALSE]);
  }

  /**
   * Tests an expired completion flag falls through to a fresh run.
   *
   * Regression guard: before the fix, this state re-stamped the completion
   * timestamp on every invocation, so the 24-hour window never expired and a
   * new run was permanently impossible without --reset.
   */
  public function testExpiredCompletionFlagStartsFreshRunWithoutRestamping(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->method('hasInterruptedProgress')->willReturn(FALSE);
    $manager->method('wasEnqueueCompletedRecently')->willReturn(FALSE);
    $manager->method('getEnqueueCompletedAt')->willReturn(1000000);
    $manager->method('isAllEnqueueCompleted')->willReturn(TRUE);
    $manager->expects($this->never())->method('markEnqueueCompleted');
    $manager->expects($this->once())->method('beginFreshEnqueueRun');
    $manager->expects($this->once())->method('populateQueue')->willReturn(FALSE);

    $command = $this->buildCommand($manager);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => FALSE]);
  }

  /**
   * Tests a bare first run (no saved state at all) starts a fresh run.
   */
  public function testFirstRunStartsFresh(): void {
    $manager = $this->createMock(EntityUsageQueueBatchManager::class);
    $manager->method('hasInterruptedProgress')->willReturn(FALSE);
    $manager->method('wasEnqueueCompletedRecently')->willReturn(FALSE);
    $manager->method('getEnqueueCompletedAt')->willReturn(NULL);
    $manager->method('isAllEnqueueCompleted')->willReturn(FALSE);
    $manager->expects($this->once())->method('beginFreshEnqueueRun');
    $manager->expects($this->once())->method('populateQueue')->willReturn(FALSE);

    $command = $this->buildCommand($manager);
    $command->recreate(['batch-size' => 10, 'force' => FALSE, 'reset' => FALSE]);
  }

}
