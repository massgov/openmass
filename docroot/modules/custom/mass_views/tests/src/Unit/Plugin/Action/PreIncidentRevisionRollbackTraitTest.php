<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\Unit\Plugin\Action;

use Drupal\mass_views\Plugin\Action\PreIncidentRevisionRollbackTrait;
use Drupal\Tests\UnitTestCase;

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
    $object = new class {

      use PreIncidentRevisionRollbackTrait;

      public function expose(int $target_vid, ?string $target_log): string {
        return $this->buildRollbackRevisionLogMessage($target_vid, $target_log);
      }

    };

    $this->assertSame($expected, $object->expose($target_vid, $target_log));
  }

  /**
   * @covers ::getExposedFilterInput
   */
  public function testGetExposedFilterInputPrefersVboContextOverStrippedView(): void {
    $view = $this->createMock(\Drupal\views\ViewExecutable::class);
    $view->method('getExposedInput')->willReturn([
      '_views_bulk_operations_override' => TRUE,
    ]);

    $object = new class($view) {

      use PreIncidentRevisionRollbackTrait;

      protected array $context;

      public function __construct($view) {
        $this->view = $view;
        $this->context = [
          'exposed_input' => [
            'revision_uid' => '42',
            'changed_from' => '2026-06-16',
            'changed_to' => '2026-06-16',
          ],
        ];
      }

      public function expose(): array {
        return $this->getExposedFilterInput();
      }

    };

    $this->assertSame([
      'revision_uid' => '42',
      'changed_from' => '2026-06-16',
      'changed_to' => '2026-06-16',
    ], $object->expose());
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
