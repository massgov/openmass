<?php

namespace Drupal\Tests\mass_feedback_loop\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Site\Settings;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Drupal\node\NodeStorageInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests owner filtering before API pagination and CSV export.
 *
 * @group mass_feedback_loop
 */
class FeedbackOwnerFilterTest extends TestCase {

  /**
   * Matching owners narrow node filters, including on CSV requests.
   *
   * @dataProvider filterCases
   */
  public function testOwnerFilter(array $matching_ids, array $params, ?array $expected_ids): void {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->expects($this->once())->method('condition')
      ->with('field_sme_content_owner.target_id', [7, 8], 'IN')->willReturnSelf();
    $query->method('execute')->willReturn($matching_ids);
    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('node')->willReturn($storage);

    $history = [];
    $handler = HandlerStack::create(new MockHandler([
      new Response(200, [], isset($params['file_type']) ? 'CSV content' : '{"results":[],"total":0}'),
    ]));
    $handler->push(Middleware::history($history));
    $client_factory = $this->createMock(ClientFactory::class);
    $client_factory->method('fromOptions')->willReturn(new Client(['handler' => $handler]));
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('per_page')->willReturn(20);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);

    $service = new MassFeedbackLoopContentFetcher(
      $this->createMock(AccountProxy::class),
      $this->createMock(Connection::class),
      new Settings([
        'mass_feedback_loop' => [
          'external_api_config' => [
            'api_base_url' => 'https://feedback.example/',
            'authenticate_header' => 'test',
          ],
        ],
      ]),
      $config_factory,
      $client_factory,
      $this->createMock(LoggerInterface::class),
      $entity_manager,
      $this->createMock(PagerManagerInterface::class),
    );
    $response = $service->fetchFeedback($params + ['sme_owner_id' => [7, 8]]);
    if ($expected_ids === NULL) {
      $this->assertSame([], $history, 'No matches must never trigger an unfiltered API call.');
      $this->assertSame(0, $response['total']);
    }
    else {
      $this->assertCount(1, $history);
      $sent = json_decode((string) $history[0]['request']->getBody(), TRUE);
      $this->assertSame($expected_ids, $sent['node_id']);
      $this->assertArrayNotHasKey('sme_owner_id', $sent);
      $this->assertSame('2026-10-01', $sent['date_from']);
      $this->assertSame([42], $sent['org_id']);
      $this->assertSame(3, $sent['page']);
      if (isset($params['file_type'])) {
        $this->assertSame('csv', $sent['file_type']);
        $this->assertSame('CSV content', (string) $response);
      }
    }
  }

  /**
   * Owner matching and intersections, with the existing API filters preserved.
   */
  public static function filterCases(): array {
    $params = ['date_from' => '2026-10-01', 'org_id' => [42], 'page' => 2];
    return [
      'owners only' => [[11 => 11, 12 => 12], $params, [11, 12]],
      'specific page matches' => [[11, 12], $params + ['node_id' => [12]], [12]],
      'specific page does not match' => [[11], $params + ['node_id' => [12]], NULL],
      'owner has no pages' => [[], $params, NULL],
      'CSV uses same filter' => [[11, 12], $params + ['file_type' => 'csv'], [11, 12]],
    ];
  }

}
