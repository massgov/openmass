<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\Unit\Plugin\Action;

use Drupal\mass_views\Plugin\Action\PreIncidentRevisionRollbackTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;

/**
 * @coversDefaultClass \Drupal\mass_views\Plugin\Action\PreIncidentRevisionRollbackTrait
 *
 * @group mass_views
 */
class PreIncidentRevisionRollbackTraitTest extends UnitTestCase {

  /**
   * @covers ::buildRollbackRevisionLogMessage
   *
   * @dataProvider revisionLogMessageProvider
   */
  public function testBuildRollbackRevisionLogMessage(int $target_vid, ?string $target_log, string $expected): void {
    $object = new PreIncidentRevisionRollbackTraitTestDouble();
    $this->assertSame($expected, $object->exposeRollbackLogMessage($target_vid, $target_log));
  }

  /**
   * @covers ::getExposedFilterInput
   */
  public function testGetExposedFilterInputPrefersVboContextOverStrippedView(): void {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('getExposedInput')->willReturn([
      '_views_bulk_operations_override' => TRUE,
    ]);

    $object = new PreIncidentRevisionRollbackTraitTestDouble();
    $object->setViewForTest($view);
    $object->setContextForTest([
      'exposed_input' => [
        'revision_uid' => '42',
        'changed_from' => '2026-06-16',
        'changed_to' => '2026-06-16',
      ],
    ]);

    $this->assertSame([
      'revision_uid' => '42',
      'changed_from' => '2026-06-16',
      'changed_to' => '2026-06-16',
    ], $object->exposeExposedFilterInput());
  }

  /**
   * Revision log message data provider.
   */
  public static function revisionLogMessageProvider(): array {
    return [
      'with message' => [
        15923441,
        'known good revision',
        'Rollback to revision #15923441: known good revision',
      ],
      'empty message' => [
        15923441,
        '',
        'Rollback to revision #15923441',
      ],
      'null message' => [
        15923441,
        NULL,
        'Rollback to revision #15923441',
      ],
      'whitespace only' => [
        15923441,
        '   ',
        'Rollback to revision #15923441',
      ],
    ];
  }

}

/**
 * Test double exposing protected trait methods.
 */
class PreIncidentRevisionRollbackTraitTestDouble {

  use PreIncidentRevisionRollbackTrait;

  /**
   * The processed view.
   */
  protected ViewExecutable $view;

  /**
   * Action context from VBO.
   *
   * @var array<string, mixed>
   */
  protected array $context = [];

  /**
   * Sets the view for testing.
   */
  public function setViewForTest(ViewExecutable $view): void {
    $this->view = $view;
  }

  /**
   * Sets the action context for testing.
   *
   * @param array<string, mixed> $context
   *   The action context.
   */
  public function setContextForTest(array $context): void {
    $this->context = $context;
  }

  /**
   * Exposes buildRollbackRevisionLogMessage for testing.
   */
  public function exposeRollbackLogMessage(int $target_vid, ?string $target_log): string {
    return $this->buildRollbackRevisionLogMessage($target_vid, $target_log);
  }

  /**
   * Exposes getExposedFilterInput for testing.
   *
   * @return array<string, mixed>
   *   Exposed filter input values.
   */
  public function exposeExposedFilterInput(): array {
    return $this->getExposedFilterInput();
  }

}
