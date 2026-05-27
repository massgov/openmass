<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\mass_redirect_normalizer\Drush\Commands\MassRedirectNormalizerCommands;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\Role;
use Drupal\redirect\Entity\Redirect;
use Drupal\file\Entity\File;
use Drupal\path_alias\Entity\PathAlias;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests redirect link normalization service behavior (integration).
 *
 * @group existing-site
 */
class RedirectLinkNormalizationTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Creates a simple two-hop redirect chain to target node.
   */
  private function createRedirectChain($target): array {
    $sourceStart = 'chain-start-' . $this->randomMachineName();
    $sourceFinal = 'chain-final-' . $this->randomMachineName();

    $secondHop = Redirect::create();
    $secondHop->setRedirect('node/' . $target->id());
    $secondHop->setSource($sourceFinal);
    $secondHop->setLanguage($target->language()->getId());
    $secondHop->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $secondHop->save();
    $this->cleanupEntities[] = $secondHop;

    $firstHop = Redirect::create();
    $firstHop->setRedirect('/' . $sourceFinal);
    $firstHop->setSource($sourceStart);
    $firstHop->setLanguage($target->language()->getId());
    $firstHop->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $firstHop->save();
    $this->cleanupEntities[] = $firstHop;

    return [$sourceStart, $sourceFinal];
  }

  /**
   * Creates one redirect from source path to target local path.
   */
  private function createRedirect(string $sourcePath, string $targetPath): Redirect {
    $redirect = Redirect::create();
    $redirect->setSource(ltrim($sourcePath, '/'));
    $redirect->setRedirect($targetPath);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirect->save();
    $this->cleanupEntities[] = $redirect;
    return $redirect;
  }

  /**
   * Creates a published document media entity for tests.
   */
  private function createDocumentMedia(string $suffix) {
    $destination = 'public://redirect-normalizer-' . $suffix . '.txt';
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    \Drupal::service('file_system')->copy($src, $destination, TRUE);

    return $this->createMedia([
      'title' => 'Doc ' . $suffix,
      'bundle' => 'document',
      'field_upload_file' => ['target_id' => $file->id()],
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

  /**
   * Builds the Drush command with module services for tests.
   */
  private function createNormalizerCommand(): MassRedirectNormalizerCommands {
    return new MassRedirectNormalizerCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('mass_redirect_normalizer.enqueuer'),
      \Drupal::lock(),
      \Drupal::database(),
      \Drupal::state(),
    );
  }

  /**
   * Processes all pending redirect-link normalization queue items.
   */
  private function drainNormalizationQueue(): void {
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Deletes every row in the redirect-link normalization queue.
   */
  private function purgeNormalizationQueue(): void {
    \Drupal::database()->delete('queue')
      ->condition('name', RedirectLinkQueueEnqueuer::QUEUE_NAME)
      ->execute();
  }

  /**
   * Tests redirect chain resolution and rich-text rewriting.
   */
  public function testRedirectChainNormalizationInText(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $redirectStorage = \Drupal::entityTypeManager()->getStorage('redirect');
    $matching = $redirectStorage->loadByProperties([
      'redirect_source__path' => $sourceStart,
    ]);
    $this->assertNotEmpty($matching);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $sourceStart . '?foo=1#bar');
    $targetPath = $target->toUrl()->toString();
    $this->assertTrue($resolved['changed']);
    $this->assertStringContainsString($targetPath, $resolved['target_path']);
    $this->assertStringContainsString('?foo=1', $resolved['target_path']);
    $this->assertStringContainsString('#bar', $resolved['target_path']);
    $this->assertNotEmpty($resolved['node']);
    $this->assertEquals($target->id(), $resolved['node']->id());

    $html = '<p><a href="/' . $sourceStart . '?foo=1#bar">Test link</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($html);
    $this->assertTrue($normalized['changed']);
    $this->assertStringContainsString($targetPath, $normalized['text']);
    $this->assertStringContainsString('data-entity-type="node"', $normalized['text']);
    $this->assertStringContainsString('data-entity-substitution="canonical"', $normalized['text']);
    $this->assertStringContainsString('data-entity-uuid="' . $target->uuid() . '"', $normalized['text']);
  }

  /**
   * Tests link-field URI normalization to final internal path.
   */
  public function testNormalizeRedirectLinkUri(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeRedirectLinkUri('internal:/' . $sourceStart . '?x=1#frag');
    $this->assertTrue($normalized['changed']);
    $this->assertStringStartsWith('internal:/', $normalized['uri']);
    $this->assertStringContainsString($target->toUrl()->toString(), $normalized['uri']);
    $this->assertStringContainsString('?x=1', $normalized['uri']);
    $this->assertStringContainsString('#frag', $normalized['uri']);
  }

  /**
   * Tests presave hook normalizes node rich-text links on save.
   */
  public function testPresaveHookNormalizesNodeBodyOnSave(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $sourceNode = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Normalize me</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Trigger presave enqueue on node save.
    $sourceNode->save();
    $this->drainNormalizationQueue();

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($sourceNode->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $body = (string) $reloaded->get('body')->value;
    $this->assertStringContainsString($target->toUrl()->toString(), $body);
    $this->assertStringContainsString('data-entity-type="node"', $body);
  }

  /**
   * Tests presave enqueues the node and the queue worker rewrites redirect links.
   */
  public function testPresaveEnqueueThenWorkerNormalizesBody(): void {
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $this->purgeNormalizationQueue();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $sourceNode = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Presave queue</a></p>',
        'format' => 'full_html',
      ],
    ]);
    // Second save: presave runs with a real nid so the enqueuer can queue work.
    $sourceNode->save();

    $this->assertGreaterThan(0, $queue->numberOfItems(), 'Presave should enqueue normalization work.');

    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $claimed = $queue->claimItem();
    $this->assertNotFalse($claimed);
    $this->assertSame('node', $claimed->data['entity_type']);
    $this->assertSame((int) $sourceNode->id(), (int) $claimed->data['entity_id']);
    $this->assertSame('presave', $claimed->data['source']);
    $worker->processItem($claimed->data);
    $queue->deleteItem($claimed);

    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }

    $this->assertSame(0, $queue->numberOfItems());

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($sourceNode->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $body = (string) $reloaded->get('body')->value;
    $this->assertStringContainsString($target->toUrl()->toString(), $body);
    $this->assertStringContainsString('data-entity-type="node"', $body);
  }

  /**
   * Tests queue-processing env suppresses presave enqueue (worker re-entry guard).
   */
  public function testQueueProcessingEnvSuppressesPresaveEnqueue(): void {
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $this->purgeNormalizationQueue();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $sourceNode = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Env guard</a></p>',
        'format' => 'full_html',
      ],
    ]);
    $this->purgeNormalizationQueue();

    $countBeforeGuardedSave = $queue->numberOfItems();
    $_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING'] = TRUE;
    try {
      $sourceNode->save();
      $this->assertSame(
        $countBeforeGuardedSave,
        $queue->numberOfItems(),
        'Presave must not enqueue when MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING is set.'
      );
    }
    finally {
      unset($_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING']);
    }

    $countBeforeUnguardedSave = $queue->numberOfItems();
    $sourceNode->save();
    $this->assertGreaterThan(
      $countBeforeUnguardedSave,
      $queue->numberOfItems(),
      'Presave should enqueue when queue processing env is not set.'
    );

    $this->purgeNormalizationQueue();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->enqueueById('node', (int) $sourceNode->id(), 'presave');
    $this->assertSame(1, $queue->numberOfItems());

    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $claimed = $queue->claimItem();
    $this->assertNotFalse($claimed);
    $worker->processItem($claimed->data);
    $queue->deleteItem($claimed);

    $this->assertFalse(
      !empty($_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING']),
      'Queue worker must unset MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING after processing.'
    );
    $this->assertSame(
      0,
      $queue->numberOfItems(),
      'Queue worker saves must not re-enqueue via presave while processing.'
    );
  }

  /**
   * Tests looped redirects do not cause infinite processing.
   */
  public function testRedirectLoopIsSafelyIgnored(): void {
    $loopA = 'loop-a-' . $this->randomMachineName();
    $loopB = 'loop-b-' . $this->randomMachineName();

    $a = Redirect::create();
    $a->setRedirect('/' . $loopB);
    $a->setSource($loopA);
    $a->setLanguage('en');
    $a->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $a->save();
    $this->cleanupEntities[] = $a;

    $b = Redirect::create();
    $b->setRedirect('/' . $loopA);
    $b->setSource($loopB);
    $b->setLanguage('en');
    $b->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $b->save();
    $this->cleanupEntities[] = $b;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $loopA . '?x=1#frag');

    $this->assertFalse($resolved['changed']);
  }

  /**
   * Tests external URLs are ignored.
   */
  public function testExternalUrlIsIgnored(): void {
    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');

    $resolved = $service->resolveRedirectTarget('https://example.com/somewhere');
    $this->assertFalse($resolved['changed']);

    $text = '<p><a href="https://example.com/somewhere">External</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($text);
    $this->assertFalse($normalized['changed']);
    $this->assertStringContainsString('https://example.com/somewhere', $normalized['text']);
  }

  /**
   * Tests non-redirect local links remain unchanged.
   */
  public function testNonRedirectLocalLinkRemainsUnchanged(): void {
    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');

    $resolved = $service->resolveRedirectTarget('/this-path-does-not-redirect');
    $this->assertFalse($resolved['changed']);

    $uriNormalized = $service->normalizeRedirectLinkUri('internal:/this-path-does-not-redirect');
    $this->assertFalse($uriNormalized['changed']);
    $this->assertSame('internal:/this-path-does-not-redirect', $uriNormalized['uri']);
  }

  /**
   * Tests max-depth limit prevents over-following deep chains.
   */
  public function testResolveRedirectTargetRespectsMaxDepth(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    $p1 = 'depth-a-' . $this->randomMachineName();
    $p2 = 'depth-b-' . $this->randomMachineName();
    $p3 = 'depth-c-' . $this->randomMachineName();

    $r1 = Redirect::create();
    $r1->setSource($p1);
    $r1->setRedirect('/' . $p2);
    $r1->setLanguage('en');
    $r1->setStatusCode(301);
    $r1->save();
    $this->cleanupEntities[] = $r1;

    $r2 = Redirect::create();
    $r2->setSource($p2);
    $r2->setRedirect('/' . $p3);
    $r2->setLanguage('en');
    $r2->setStatusCode(301);
    $r2->save();
    $this->cleanupEntities[] = $r2;

    $r3 = Redirect::create();
    $r3->setSource($p3);
    $r3->setRedirect('node/' . $target->id());
    $r3->setLanguage('en');
    $r3->setStatusCode(301);
    $r3->save();
    $this->cleanupEntities[] = $r3;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $limited = $service->resolveRedirectTarget('/' . $p1, 1);
    $this->assertTrue($limited['changed']);
    $this->assertSame('/' . $p2, $limited['target_path']);

    $full = $service->resolveRedirectTarget('/' . $p1, 10);
    $this->assertTrue($full['changed']);
    $this->assertStringContainsString($target->toUrl()->toString(), $full['target_path']);
  }

  /**
   * Tests redirect chain follows hops across trailing-slash variants.
   */
  public function testResolveRedirectTargetHandlesTrailingSlashHop(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    $sourceStart = 'slash-hop-start-' . $this->randomMachineName();
    $sourceMiddle = 'slash-hop-middle-' . $this->randomMachineName();

    $first = Redirect::create();
    $first->setSource($sourceStart);
    $first->setRedirect('/' . $sourceMiddle . '/');
    $first->setLanguage('en');
    $first->setStatusCode(301);
    $first->save();
    $this->cleanupEntities[] = $first;

    $second = Redirect::create();
    $second->setSource($sourceMiddle);
    $second->setRedirect('/node/' . $target->id());
    $second->setLanguage('en');
    $second->setStatusCode(301);
    $second->save();
    $this->cleanupEntities[] = $second;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $sourceStart);
    $this->assertTrue($resolved['changed']);
    $this->assertStringContainsString($target->toUrl()->toString(), $resolved['target_path']);
  }

  /**
   * Tests redirecting to external target is ignored for rewriting.
   */
  public function testRedirectToExternalTargetIsIgnored(): void {
    $source = 'to-external-' . $this->randomMachineName();
    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect('https://example.com/outside');
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $source);
    $this->assertFalse($resolved['changed']);
  }

  /**
   * Tests alias-like final target rewrites href but does not add node metadata.
   */
  public function testAliasTargetWithoutNodeDoesNotAddEntityMetadata(): void {
    $source = 'to-alias-' . $this->randomMachineName();
    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect('/some/non-node-alias');
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $text = '<p><a href="/' . $source . '">Alias link</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($text);

    $this->assertTrue($normalized['changed']);
    $this->assertStringContainsString('/some/non-node-alias', $normalized['text']);
    $this->assertStringNotContainsString('data-entity-type="node"', $normalized['text']);
    $this->assertStringNotContainsString('data-entity-uuid=', $normalized['text']);
  }

  /**
   * Tests redirected document links are rewritten in rich text.
   */
  public function testDocumentRedirectIsNormalizedInText(): void {
    $source = 'doc-source-' . $this->randomMachineName();
    $target = '/sites/default/files/documents/example.pdf';

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect($target);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $text = '<p><a href="/' . $source . '?dl=1#frag">Doc link</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($text);

    $this->assertTrue($normalized['changed']);
    $this->assertStringContainsString($target . '?dl=1#frag', $normalized['text']);
    // Document targets are not node canonical paths,
    // so node metadata is absent.
    $this->assertStringNotContainsString('data-entity-type="node"', $normalized['text']);
    $this->assertStringNotContainsString('data-entity-uuid=', $normalized['text']);
  }

  /**
   * Tests redirected document links are rewritten in link fields.
   */
  public function testDocumentRedirectIsNormalizedInLinkField(): void {
    $source = 'doc-link-source-' . $this->randomMachineName();
    $target = '/sites/default/files/documents/example-2.pdf';

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect($target);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeRedirectLinkUri('internal:/' . $source . '?download=1#part');

    $this->assertTrue($normalized['changed']);
    $this->assertSame('internal:' . $target . '?download=1#part', $normalized['uri']);
  }

  /**
   * Tests manager idempotency after first normalization.
   */
  public function testManagerIsIdempotentAfterNormalization(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $node = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p>No redirect yet</p>',
        'format' => 'full_html',
      ],
    ]);
    $node->set('body', [
      'value' => '<p><a href="/' . $sourceStart . '">Run twice</a></p>',
      'format' => 'full_html',
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $first = $manager->normalizeEntity($node, TRUE);
    $this->assertTrue($first['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $second = $manager->normalizeEntity($reloaded, TRUE);
    $this->assertFalse($second['changed']);
  }

  /**
   * Tests command bundle filter constrains output.
   */
  public function testCommandBundleFiltering(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Node-only</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Presave hook rewrites redirect links on first save, so the stored body no
    // longer contains the redirect path. Put the redirect URL back in the DB so
    // the bulk command (which loads from storage) has something to normalize.
    $redirect_markup = '<p><a href="/' . $sourceStart . '">Node-only</a></p>';
    $nid = (int) $page->id();
    $vid = (int) $page->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => $redirect_markup])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $this->assertNotNull($reloaded);
    $dryPreview = $manager->normalizeEntity($reloaded, FALSE, TRUE);
    $this->assertNotEmpty($dryPreview['changed'], 'Dry run should detect redirect-based link in body.');

    $command = $this->createNormalizerCommand();
    $rowsObj = $command->normalizeRedirectLinks([
      'bundle' => 'page',
      'entity-ids' => (string) $page->id(),
      'limit' => 0,
      'simulate' => TRUE,
    ]);
    $rows = method_exists($rowsObj, 'getArrayCopy') ? $rowsObj->getArrayCopy() : iterator_to_array($rowsObj);

    $this->assertNotEmpty($rows);
    foreach ($rows as $row) {
      $this->assertSame('page', $row['bundle']);
      $this->assertSame('would_update', $row['status']);
      $this->assertNotSame($row['before'], $row['after']);
    }
  }

  /**
   * Tests command skips unpublished nodes.
   */
  public function testCommandSkipsUnpublishedNode(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $unpublished = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 0,
      'moderation_state' => 'draft',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Unpublished node</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $command = $this->createNormalizerCommand();
    $rowsObj = $command->normalizeRedirectLinks([
      'entity-ids' => (string) $unpublished->id(),
      'simulate' => TRUE,
    ]);
    $rows = method_exists($rowsObj, 'getArrayCopy') ? $rowsObj->getArrayCopy() : iterator_to_array($rowsObj);
    $this->assertSame([], $rows);
  }

  /**
   * Tests command writes CSV report rows for parseable output.
   */
  public function testCommandWritesCsvReport(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">CSV candidate</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Ensure source URL still exists in storage for the bulk command.
    $redirectMarkup = '<p><a href="/' . $sourceStart . '">CSV candidate</a></p>';
    $nid = (int) $page->id();
    $vid = (int) $page->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => $redirectMarkup])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    $csvPath = sys_get_temp_dir() . '/mass-redirect-normalizer-' . $this->randomMachineName() . '.csv';
    if (file_exists($csvPath)) {
      @unlink($csvPath);
    }

    $command = $this->createNormalizerCommand();
    $command->normalizeRedirectLinks([
      'entity-ids' => (string) $page->id(),
      'simulate' => TRUE,
      'csv-path' => $csvPath,
    ]);

    $this->assertFileExists($csvPath);
    $csv = (string) file_get_contents($csvPath);
    $this->assertStringContainsString('status,entity_type,entity_id', $csv);
    $this->assertStringContainsString('would_update,node,' . $page->id(), $csv);
    $this->assertStringContainsString('/' . $sourceStart, $csv);
    $this->assertStringContainsString($target->toUrl()->toString(), $csv);

    @unlink($csvPath);
  }

  /**
   * Tests CSV output appends new rows when the report file already exists.
   */
  public function testCommandAppendsToExistingCsvReport(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">CSV append</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $redirectMarkup = '<p><a href="/' . $sourceStart . '">CSV append</a></p>';
    $nid = (int) $page->id();
    $vid = (int) $page->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => $redirectMarkup])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    $csvPath = sys_get_temp_dir() . '/mass-redirect-normalizer-append-' . $this->randomMachineName() . '.csv';
    if (file_exists($csvPath)) {
      @unlink($csvPath);
    }

    $command = $this->createNormalizerCommand();
    $opts = [
      'entity-ids' => (string) $page->id(),
      'simulate' => TRUE,
      'csv-path' => $csvPath,
    ];
    $command->normalizeRedirectLinks($opts);

    $linesFirst = count(file($csvPath));

    $command->normalizeRedirectLinks($opts);

    $linesSecond = count(file($csvPath));
    $this->assertGreaterThan($linesFirst, $linesSecond);
    $this->assertSame($linesFirst + ($linesFirst - 1), $linesSecond);

    @unlink($csvPath);
  }

  /**
   * Tests node entity reference is normalized via deterministic redirect.
   */
  public function testManagerNormalizesNodeEntityReferenceField(): void {
    $sourcePerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $targetPerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->createRedirect('/node/' . $sourcePerson->id(), '/node/' . $targetPerson->id());

    $org = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $org->set('field_person_bio', ['target_id' => $sourcePerson->id()]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($org, TRUE);
    $this->assertTrue($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($org->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $this->assertSame((int) $targetPerson->id(), (int) $reloaded->get('field_person_bio')->target_id);
  }

  /**
   * Tests media entity reference is normalized via deterministic redirect.
   */
  public function testManagerNormalizesMediaEntityReferenceField(): void {
    $sourceMedia = $this->createDocumentMedia('source-' . $this->randomMachineName());
    $targetMedia = $this->createDocumentMedia('target-' . $this->randomMachineName());
    $this->createRedirect('/media/' . $sourceMedia->id(), '/media/' . $targetMedia->id());

    $binder = $this->createNode([
      'type' => 'binder',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_downloads' => [
        ['target_id' => $targetMedia->id()],
      ],
    ]);
    $binder->set('field_downloads', [
      ['target_id' => $sourceMedia->id()],
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($binder, TRUE);
    $this->assertTrue($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($binder->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $this->assertSame((int) $targetMedia->id(), (int) $reloaded->get('field_downloads')->target_id);
  }

  /**
   * Tests strict-safe entity reference rewrite skips unresolved targets.
   */
  public function testEntityReferenceRewriteSkipsUnresolvedTarget(): void {
    $sourcePerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->createRedirect('/node/' . $sourcePerson->id(), '/node/99999999');

    $org = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_person_bio' => [
        ['target_id' => $sourcePerson->id()],
      ],
    ]);
    $org->set('field_person_bio', ['target_id' => $sourcePerson->id()]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($org, TRUE);
    $this->assertFalse($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($org->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $this->assertSame((int) $sourcePerson->id(), (int) $reloaded->get('field_person_bio')->target_id);
  }

  /**
   * Tests entity ref rewrite skips alias path that resolves to same media.
   */
  public function testEntityReferenceRewriteSkipsActiveAliasConflict(): void {
    $sourceMedia = $this->createDocumentMedia('alias-source-' . $this->randomMachineName());
    $targetMedia = $this->createDocumentMedia('alias-target-' . $this->randomMachineName());

    $aliasPath = '/doc/alias-conflict-' . $this->randomMachineName();
    $alias = PathAlias::create([
      'path' => '/media/' . $sourceMedia->id(),
      'alias' => $aliasPath,
      'langcode' => 'en',
      'status' => 1,
    ]);
    $alias->save();
    $this->cleanupEntities[] = $alias;

    // This redirect conflicts with an active alias that still resolves to
    // source media, so strict-safe entity-ref rewrite must skip it.
    $this->createRedirect($aliasPath, '/media/' . $targetMedia->id());

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeEntityReferenceTarget('media', (int) $sourceMedia->id());
    $this->assertFalse($normalized['changed']);
  }

  /**
   * Tests CSV report includes entity-reference change rows.
   */
  public function testCommandCsvIncludesEntityReferenceRows(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    $sourcePerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $targetPerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->createRedirect('/node/' . $sourcePerson->id(), '/node/' . $targetPerson->id());

    $org = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_person_bio' => [
        ['target_id' => $targetPerson->id()],
      ],
    ]);

    // Force pre-normalized reference back to source for command DB read.
    $nid = (int) $org->id();
    $vid = (int) $org->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__field_person_bio', 'node_revision__field_person_bio'] as $table) {
      $connection->update($table)
        ->fields(['field_person_bio_target_id' => (int) $sourcePerson->id()])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    $csvPath = sys_get_temp_dir() . '/mass-redirect-normalizer-entity-ref-' . $this->randomMachineName() . '.csv';
    if (file_exists($csvPath)) {
      @unlink($csvPath);
    }

    $command = $this->createNormalizerCommand();
    $command->normalizeRedirectLinks([
      'entity-ids' => (string) $org->id(),
      'simulate' => TRUE,
      'csv-path' => $csvPath,
    ]);

    $this->assertFileExists($csvPath);
    $csv = (string) file_get_contents($csvPath);
    $this->assertStringContainsString(',entity_reference,', $csv);
    $this->assertStringContainsString('node:' . $sourcePerson->id(), $csv);
    $this->assertStringContainsString('node:' . $targetPerson->id(), $csv);

    @unlink($csvPath);
  }

  /**
   * Tests command kinds filter returns only selected change types.
   */
  public function testCommandKindsFilterReturnsOnlyEntityReferences(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    $sourcePerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $targetPerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->createRedirect('/node/' . $sourcePerson->id(), '/node/' . $targetPerson->id());

    $targetText = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($targetText);

    $org = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p>No redirect yet</p>',
        'format' => 'full_html',
      ],
      'field_person_bio' => [
        ['target_id' => $targetPerson->id()],
      ],
    ]);

    // Force both text + entity_reference changes for this entity in storage.
    $nid = (int) $org->id();
    $vid = (int) $org->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => '<p><a href="/' . $sourceStart . '">Text change</a></p>'])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    foreach (['node__field_person_bio', 'node_revision__field_person_bio'] as $table) {
      $connection->update($table)
        ->fields(['field_person_bio_target_id' => (int) $sourcePerson->id()])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    $command = $this->createNormalizerCommand();
    $rowsObj = $command->normalizeRedirectLinks([
      'entity-ids' => (string) $org->id(),
      'simulate' => TRUE,
      'kinds' => 'entity_reference',
    ]);
    $rows = method_exists($rowsObj, 'getArrayCopy') ? $rowsObj->getArrayCopy() : iterator_to_array($rowsObj);
    $this->assertNotEmpty($rows);
    foreach ($rows as $row) {
      $this->assertSame('entity_reference', $row['kind']);
    }
  }

  /**
   * Tests command progress checkpoint resume and show-progress behavior.
   */
  public function testCommandProgressResumeAndShowProgress(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    \Drupal::state()->delete('mass_redirect_normalizer.command_progress');

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $nodeA = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">A</a></p>',
        'format' => 'full_html',
      ],
    ]);
    $nodeB = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">B</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Re-inject source URL so bulk command can detect the change.
    foreach ([$nodeA, $nodeB] as $node) {
      $redirectMarkup = '<p><a href="/' . $sourceStart . '">Resume test</a></p>';
      $nid = (int) $node->id();
      $vid = (int) $node->getRevisionId();
      $connection = \Drupal::database();
      foreach (['node__body', 'node_revision__body'] as $table) {
        $connection->update($table)
          ->fields(['body_value' => $redirectMarkup])
          ->condition('entity_id', $nid)
          ->condition('revision_id', $vid)
          ->execute();
      }
      \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);
    }

    $ids = [(int) $nodeA->id(), (int) $nodeB->id()];
    sort($ids);
    $firstId = $ids[0];
    $secondId = $ids[1];

    $command = $this->createNormalizerCommand();

    // First pass with limit=1 should checkpoint first ID.
    $rowsObj1 = $command->normalizeRedirectLinks([
      'entity-type' => 'node',
      'bundle' => 'page',
      'entity-ids' => $firstId . ',' . $secondId,
      'start-id' => $firstId,
      'limit' => 1,
      'simulate' => TRUE,
      'reset-progress' => TRUE,
    ]);
    $rows1 = method_exists($rowsObj1, 'getArrayCopy') ? $rowsObj1->getArrayCopy() : iterator_to_array($rowsObj1);
    $this->assertNotEmpty($rows1);
    $this->assertSame((string) $firstId, (string) $rows1[0]['entity_id']);

    $checkpoint = \Drupal::state()->get('mass_redirect_normalizer.command_progress');
    $this->assertIsArray($checkpoint);
    $this->assertSame($firstId, (int) ($checkpoint['last_ids']['node'] ?? 0));

    // Resume should continue from next node.
    $rowsObj2 = $command->normalizeRedirectLinks([
      'entity-type' => 'node',
      'bundle' => 'page',
      'entity-ids' => $firstId . ',' . $secondId,
      'limit' => 1,
      'simulate' => TRUE,
      'resume' => TRUE,
    ]);
    $rows2 = method_exists($rowsObj2, 'getArrayCopy') ? $rowsObj2->getArrayCopy() : iterator_to_array($rowsObj2);
    $this->assertNotEmpty($rows2);
    $this->assertSame((string) $secondId, (string) $rows2[0]['entity_id']);

    // Show progress should not return data rows.
    $rowsObj3 = $command->normalizeRedirectLinks([
      'show-progress' => TRUE,
    ]);
    $rows3 = method_exists($rowsObj3, 'getArrayCopy') ? $rowsObj3->getArrayCopy() : iterator_to_array($rowsObj3);
    $this->assertSame([], $rows3);
  }

  /**
   * Tests absolute local URL link-field normalization.
   */
  public function testNormalizeAbsoluteLocalUrlLinkField(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeRedirectLinkUri('https://www.mass.gov/' . $sourceStart . '?q=1#x');

    $this->assertTrue($normalized['changed']);
    $this->assertStringStartsWith('internal:/', $normalized['uri']);
    $this->assertStringContainsString($target->toUrl()->toString(), $normalized['uri']);
    $this->assertStringContainsString('?q=1', $normalized['uri']);
    $this->assertStringContainsString('#x', $normalized['uri']);
  }

  /**
   * Tests mixed multi-value link field normalization on one entity.
   */
  public function testManagerNormalizesOnlyRedirectingLinksInMultiValueField(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $node = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_social_links' => [
        ['uri' => 'internal:/no-redirect-here', 'title' => 'unchanged-local'],
      ],
    ]);
    $node->set('field_social_links', [
      ['uri' => 'internal:/' . $sourceStart, 'title' => 'redirecting'],
      ['uri' => 'internal:/no-redirect-here', 'title' => 'unchanged-local'],
      ['uri' => 'https://example.com/external', 'title' => 'external'],
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($node, TRUE);
    $this->assertTrue($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $links = $reloaded->get('field_social_links')->getValue();

    $this->assertSame('entity:node/' . $target->id(), $links[0]['uri']);
    $this->assertSame('internal:/no-redirect-here', $links[1]['uri']);
    $this->assertSame('https://example.com/external', $links[2]['uri']);
  }

  /**
   * Tests link item metadata (title/options) is preserved.
   */
  public function testLinkItemMetadataIsPreservedDuringNormalization(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $options = [
      'attributes' => [
        'class' => ['my-link-class'],
      ],
    ];
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_social_links' => [
        [
          'uri' => 'internal:/no-redirect-yet',
          'title' => 'initial-title',
        ],
      ],
    ]);
    $node->set('field_social_links', [
      [
        'uri' => 'internal:/' . $sourceStart,
        'title' => 'keep-title',
        'options' => $options,
      ],
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($node, TRUE);
    $this->assertTrue($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $item = $reloaded->get('field_social_links')->first();
    $this->assertNotNull($item);
    $this->assertSame('keep-title', $item->title);
    $this->assertSame('my-link-class', $item->options['attributes']['class'][0]);
    $this->assertSame('entity:node/' . $target->id(), $item->uri);
  }

  /**
   * Tests node redirect link field uses entity URI for Linkit UX.
   */
  public function testNormalizeRedirectLinkUriUsesEntitySchemeForNodes(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeRedirectLinkUri('internal:/' . $sourceStart);
    $this->assertTrue($normalized['changed']);
    $this->assertSame('entity:node/' . $target->id(), $normalized['uri']);
    $this->assertStringNotContainsString('internal:/', $normalized['uri']);
  }

  /**
   * Tests automated URL-fix revisions are attributed to admin user.
   */
  public function testAutomatedRevisionUsesAdminAsRevisionAuthor(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->activate();
    $editor->save();

    $node = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'uid' => $editor->id(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Needs normalization</a></p>',
        'format' => 'full_html',
      ],
    ]);

    // Presave normalization may have already rewritten this during creation;
    // put redirect source back so this test exercises manager save behavior.
    $node->set('body', [
      'value' => '<p><a href="/' . $sourceStart . '">Needs normalization</a></p>',
      'format' => 'full_html',
    ]);

    $beforeRevisionId = (int) $node->getRevisionId();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($node, TRUE);
    $this->assertTrue($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $this->assertGreaterThan($beforeRevisionId, (int) $reloaded->getRevisionId());
    $this->assertSame(1, (int) $reloaded->getRevisionUserId());
  }

  /**
   * Tests execute mode enqueues work without saving inline.
   */
  public function testCommandExecuteEnqueuesWithoutSavingInline(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');
    \Drupal::state()->delete('mass_redirect_normalizer.command_progress');
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    while ($queue->numberOfItems() > 0) {
      $item = $queue->claimItem();
      if ($item) {
        $queue->deleteItem($item);
      }
    }

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Enqueue me</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $redirectMarkup = '<p><a href="/' . $sourceStart . '">Enqueue me</a></p>';
    $nid = (int) $page->id();
    $vid = (int) $page->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => $redirectMarkup])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    $command = $this->createNormalizerCommand();
    $rowsObj = $command->normalizeRedirectLinks([
      'entity-ids' => (string) $page->id(),
      'simulate' => FALSE,
    ]);
    $rows = method_exists($rowsObj, 'getArrayCopy') ? $rowsObj->getArrayCopy() : iterator_to_array($rowsObj);
    $this->assertSame([], $rows);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $this->assertStringContainsString('/' . $sourceStart, (string) $reloaded->get('body')->value);

    $this->assertGreaterThan(0, $queue->numberOfItems());
    $this->drainNormalizationQueue();
    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $this->assertNotNull($reloaded);
    $this->assertStringContainsString($target->toUrl()->toString(), (string) $reloaded->get('body')->value);
  }

  /**
   * Tests duplicate enqueue attempts do not multiply queue items.
   */
  public function testDuplicateEnqueueDedupesQueueItems(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    while ($queue->numberOfItems() > 0) {
      $item = $queue->claimItem();
      if ($item) {
        $queue->deleteItem($item);
      }
    }

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Dedupe</a></p>',
        'format' => 'full_html',
      ],
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $this->assertSame('enqueued', $enqueuer->enqueueById('node', (int) $page->id(), 'drush'));
    $this->assertSame('already_queued', $enqueuer->enqueueById('node', (int) $page->id(), 'drush'));
    $this->assertSame(1, $queue->numberOfItems());
  }

  /**
   * Tests execute mode auto-resumes enqueue checkpoint.
   */
  public function testCommandExecuteAutoResumesEnqueueCheckpoint(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    \Drupal::state()->delete('mass_redirect_normalizer.command_progress');
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    while ($queue->numberOfItems() > 0) {
      $item = $queue->claimItem();
      if ($item) {
        $queue->deleteItem($item);
      }
    }

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $nodeA = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">A</a></p>',
        'format' => 'full_html',
      ],
    ]);
    $nodeB = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">B</a></p>',
        'format' => 'full_html',
      ],
    ]);

    foreach ([$nodeA, $nodeB] as $node) {
      $redirectMarkup = '<p><a href="/' . $sourceStart . '">Resume enqueue</a></p>';
      $nid = (int) $node->id();
      $vid = (int) $node->getRevisionId();
      $connection = \Drupal::database();
      foreach (['node__body', 'node_revision__body'] as $table) {
        $connection->update($table)
          ->fields(['body_value' => $redirectMarkup])
          ->condition('entity_id', $nid)
          ->condition('revision_id', $vid)
          ->execute();
      }
      \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);
    }

    $ids = [(int) $nodeA->id(), (int) $nodeB->id()];
    sort($ids);
    $firstId = $ids[0];
    $secondId = $ids[1];

    $command = $this->createNormalizerCommand();
    $command->normalizeRedirectLinks([
      'entity-type' => 'node',
      'bundle' => 'page',
      'entity-ids' => $firstId . ',' . $secondId,
      'start-id' => $firstId,
      'limit' => 1,
      'simulate' => FALSE,
      'reset-progress' => TRUE,
    ]);

    $checkpoint = \Drupal::state()->get('mass_redirect_normalizer.command_progress');
    $this->assertIsArray($checkpoint);
    $this->assertSame($firstId, (int) ($checkpoint['last_ids']['node'] ?? 0));

    $command->normalizeRedirectLinks([
      'entity-type' => 'node',
      'bundle' => 'page',
      'entity-ids' => $firstId . ',' . $secondId,
      'limit' => 1,
      'simulate' => FALSE,
    ]);

    $checkpoint = \Drupal::state()->get('mass_redirect_normalizer.command_progress');
    $this->assertIsArray($checkpoint);
    $this->assertSame($secondId, (int) ($checkpoint['last_ids']['node'] ?? 0));
  }

  /**
   * Tests execute mode exits when enqueue lock is already held.
   */
  public function testCommandEnqueueBlockedWhileLockHeld(): void {
    $this->markTestSkipped('Covered by new lock behavior tests.');
    $lock = \Drupal::lock();
    $this->assertTrue($lock->acquire('mass_redirect_normalizer.enqueue', 3600));

    $command = $this->createNormalizerCommand();
    $rowsObj = $command->normalizeRedirectLinks([
      'simulate' => FALSE,
      'limit' => 1,
    ]);
    $rows = method_exists($rowsObj, 'getArrayCopy') ? $rowsObj->getArrayCopy() : iterator_to_array($rowsObj);
    $this->assertSame([], $rows);

    $lock->release('mass_redirect_normalizer.enqueue');
  }

  /**
   * Tests mnrl clears the normalization queue before a fresh enqueue sweep.
   */
  public function testMnrlPurgesNormalizationQueueBeforeEnqueue(): void {
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $queue->createItem(['entity_type' => 'node', 'entity_id' => 1, 'source' => 'presave']);
    $queue->createItem(['entity_type' => 'node', 'entity_id' => 2, 'source' => 'presave']);
    $this->assertSame(2, $queue->numberOfItems());

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $cleared = $enqueuer->purgeNormalizationQueue();
    $this->assertSame(2, $cleared);
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Tests --release-enqueue-lock clears a sweep lock held by another lock ID.
   */
  public function testReleaseEnqueueLockClearsStaleSweepLock(): void {
    $this->markTestSkipped('Removed --release-enqueue-lock; mnrl releases stale locks automatically.');
    $database = \Drupal::database();
    $database->delete('semaphore')
      ->condition('name', 'mass_redirect_normalizer.enqueue')
      ->execute();
    $database->insert('semaphore')
      ->fields([
        'name' => 'mass_redirect_normalizer.enqueue',
        'value' => 'stale-lock-value-not-current-request',
        'expire' => microtime(TRUE) + 3600,
      ])
      ->execute();

    $command = $this->createNormalizerCommand();
    $command->normalizeRedirectLinks([
      'release-enqueue-lock' => TRUE,
    ]);

    $count = (int) $database->select('semaphore', 's')
      ->condition('name', 'mass_redirect_normalizer.enqueue')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(0, $count);

    $lock = \Drupal::lock();
    $this->assertTrue($lock->acquire('mass_redirect_normalizer.enqueue', 3600));
    $lock->release('mass_redirect_normalizer.enqueue');
  }

  /**
   * Tests simulate mode does not enqueue queue items.
   */
  public function testSimulateDoesNotEnqueue(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    while ($queue->numberOfItems() > 0) {
      $item = $queue->claimItem();
      if ($item) {
        $queue->deleteItem($item);
      }
    }

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Simulate only</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $redirectMarkup = '<p><a href="/' . $sourceStart . '">Simulate only</a></p>';
    $nid = (int) $page->id();
    $vid = (int) $page->getRevisionId();
    $connection = \Drupal::database();
    foreach (['node__body', 'node_revision__body'] as $table) {
      $connection->update($table)
        ->fields(['body_value' => $redirectMarkup])
        ->condition('entity_id', $nid)
        ->condition('revision_id', $vid)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$nid]);

    $command = $this->createNormalizerCommand();
    $rowsObj = $command->normalizeRedirectLinks([
      'entity-ids' => (string) $page->id(),
      'simulate' => TRUE,
    ]);
    $rows = method_exists($rowsObj, 'getArrayCopy') ? $rowsObj->getArrayCopy() : iterator_to_array($rowsObj);
    $this->assertNotEmpty($rows);
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Tests queue worker writes changed rows into change log table.
   */
  public function testQueueWorkerWritesChangedRowsToChangeLogTable(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => '<p><a href="/' . $sourceStart . '">Normalize me</a></p>',
        'format' => 'full_html',
      ],
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->enqueueById('node', (int) $page->id(), 'presave');
    $this->drainNormalizationQueue();

    $count = (int) \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->condition('entity_type', 'node')
      ->condition('entity_id', (int) $page->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertGreaterThan(0, $count);

    $status = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', (int) $page->id())
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $this->assertSame('succeeded', $status);
  }

  /**
   * Tests report permissions are scoped to content admins and admin role.
   */
  public function testReportPermissionsRoleScope(): void {
    $contentTeam = Role::load('content_team');
    $editor = Role::load('editor');
    $author = Role::load('author');
    $this->assertNotNull($contentTeam);
    $this->assertNotNull($editor);
    $this->assertNotNull($author);

    $contentTeamPermissions = $contentTeam->getPermissions();
    $this->assertContains('view mass redirect normalizer report', $contentTeamPermissions);
    $this->assertContains('export mass redirect normalizer report', $contentTeamPermissions);
    $this->assertContains('clear mass redirect normalizer report', $contentTeamPermissions);
    $this->assertNotContains('view mass redirect normalizer report', $editor->getPermissions());
    $this->assertNotContains('view mass redirect normalizer report', $author->getPermissions());
  }

  /**
   * Tests change log service clear and export operations.
   */
  public function testChangeLogServiceClearAll(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkChangeLog $service */
    $service = \Drupal::service('mass_redirect_normalizer.change_log');
    $service->logChanges('node', 123, 'page', 'drush', [
      [
        'field' => 'body',
        'delta' => 0,
        'kind' => 'text',
        'before' => '<p>/old</p>',
        'after' => '<p>/new</p>',
      ],
    ]);

    $countBeforeClear = (int) \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertGreaterThan(0, $countBeforeClear);

    $service->clearAll();
    $countAfterClear = (int) \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(0, $countAfterClear);
  }

  /**
   * Tests change log status column for succeeded and failed rows.
   */
  public function testChangeLogStatusColumnForSuccessAndFailure(): void {
    $this->ensureChangeLogTableExists();
    \Drupal::database()->truncate('mass_redirect_normalizer_change_log')->execute();

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkChangeLog $service */
    $service = \Drupal::service('mass_redirect_normalizer.change_log');
    $service->logChanges('node', 456, 'page', 'drush', [
      [
        'field' => 'body',
        'delta' => 0,
        'kind' => 'text',
        'before' => '<p>/old</p>',
        'after' => '<p>/new</p>',
      ],
    ]);
    $service->logFailure('node', 789, 'page', 'drush', 'Example failure message.');

    $succeeded = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status', 'error_message'])
      ->condition('entity_id', 456)
      ->execute()
      ->fetchAssoc();
    $this->assertIsArray($succeeded);
    $this->assertSame('succeeded', $succeeded['status']);
    $this->assertNull($succeeded['error_message']);

    $failed = \Drupal::database()->select('mass_redirect_normalizer_change_log', 'l')
      ->fields('l', ['status', 'error_message'])
      ->condition('entity_id', 789)
      ->execute()
      ->fetchAssoc();
    $this->assertIsArray($failed);
    $this->assertSame('failed', $failed['status']);
    $this->assertSame('Example failure message.', $failed['error_message']);
  }

  /**
   * Ensures the change log table exists in ExistingSite tests.
   */
  private function ensureChangeLogTableExists(): void {
    $schema = \Drupal::database()->schema();
    $table = 'mass_redirect_normalizer_change_log';
    if (!$schema->tableExists($table)) {
      $definition = [
        'description' => 'Stores redirect normalization changes written by queue worker.',
        'fields' => [
          'id' => ['type' => 'serial', 'not null' => TRUE],
          'changed_at' => ['type' => 'int', 'not null' => TRUE],
          'source' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
          'entity_type' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE, 'default' => ''],
          'entity_id' => ['type' => 'int', 'not null' => TRUE],
          'bundle' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE, 'default' => ''],
          'field_name' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
          'delta' => ['type' => 'int', 'not null' => TRUE],
          'kind' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
          'before_value' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
          'after_value' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
          'status' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE, 'default' => 'succeeded'],
          'error_message' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'changed_at' => ['changed_at'],
          'entity' => ['entity_type', 'entity_id'],
          'source' => ['source'],
          'status' => ['status'],
        ],
      ];
      $schema->createTable($table, $definition);
      return;
    }

    if (!$schema->fieldExists($table, 'status')) {
      $schema->addField($table, 'status', [
        'type' => 'varchar',
        'length' => 16,
        'not null' => TRUE,
        'default' => 'succeeded',
      ]);
      $schema->addIndex($table, 'status', ['status']);
    }
    if (!$schema->fieldExists($table, 'error_message')) {
      $schema->addField($table, 'error_message', [
        'type' => 'text',
        'size' => 'big',
        'not null' => FALSE,
      ]);
    }
  }

  /**
   * Tests paragraph normalization is not reverted when the host node is saved.
   */
  public function testParagraphNormalizationSurvivesHostNodeRevision(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);
    $targetPath = $target->toUrl()->toString();

    [$node, $paragraphId] = $this->createHowToWithMethodParagraph();
    $this->setParagraphMethodDetailsMarkup($paragraphId, '<p><a href="/' . $sourceStart . '">Need docs</a></p>');

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $paragraph = Paragraph::load($paragraphId);
    $this->assertNotNull($paragraph);
    $result = $manager->normalizeEntity($paragraph, TRUE);
    $this->assertTrue($result['changed']);

    $this->assertParagraphMethodDetailsContains($paragraphId, $targetPath, '/' . $sourceStart);
    $this->assertHostNodeReferencesNormalizedParagraph($node, $paragraphId, $targetPath, '/' . $sourceStart);
  }

  /**
   * Tests doc-to-doc redirect chains on nested paragraphs (real-id style).
   *
   * Redirect entities may point old doc paths at a newer doc path; normalization
   * should stop at the last redirect entity, not a media/file download route.
   */
  public function testParagraphNormalizationWithDocToDocRedirectChain(): void {
    $oldDoc = 'doc/old-checklist-' . $this->randomMachineName() . '/download';
    $newDoc = 'doc/new-checklist-' . $this->randomMachineName() . '/download';
    $this->createRedirect('/' . $oldDoc, '/' . $newDoc);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $resolver */
    $resolver = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $resolver->resolveRedirectTarget('/' . $oldDoc);
    $this->assertTrue($resolved['changed']);
    $this->assertSame('/' . $newDoc, $resolved['target_path']);

    [$node, $paragraphId] = $this->createHowToWithMethodParagraph();
    $this->setParagraphMethodDetailsMarkup($paragraphId, '<p><a href="/' . $oldDoc . '">Need docs</a></p>');

    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $paragraph = Paragraph::load($paragraphId);
    $this->assertNotNull($paragraph);
    $result = $manager->normalizeEntity($paragraph, TRUE);
    $this->assertTrue($result['changed']);

    $this->assertParagraphMethodDetailsContains($paragraphId, '/' . $newDoc, '/' . $oldDoc);
    $this->assertHostNodeReferencesNormalizedParagraph($node, $paragraphId, '/' . $newDoc, '/' . $oldDoc);
  }

  /**
   * Creates a published how-to with one method paragraph.
   *
   * @return array{0: \Drupal\node\NodeInterface, 1: int}
   *   Host node and method paragraph ID.
   */
  private function createHowToWithMethodParagraph(): array {
    $method = Paragraph::create([
      'type' => 'method',
      'field_method_type' => 'online',
      'field_method_details' => [
        'value' => '<p><a href="/placeholder">Need docs</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $contact = $this->createNode([
      'type' => 'contact_information',
      'title' => $this->randomMachineName(),
      'field_display_title' => 'Test contact',
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->cleanupEntities[] = $contact;

    $org = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    $node = $this->createNode([
      'type' => 'how_to_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_how_to_lede' => [
        'value' => 'Test lede',
        'format' => 'plain_text',
      ],
      'field_how_to_link_1' => [
        'uri' => 'https://www.example.com',
        'title' => 'Example',
      ],
      'field_how_to_methods_5' => [$method],
      'field_how_to_contacts_3' => [$contact],
      'field_organizations' => [$org],
    ]);
    $this->cleanupEntities[] = $node;

    return [$node, (int) $method->id()];
  }

  /**
   * Sets method paragraph body markup in storage (bypasses presave normalization).
   */
  private function setParagraphMethodDetailsMarkup(int $paragraphId, string $markup): void {
    $connection = \Drupal::database();
    foreach (['paragraph__field_method_details', 'paragraph_revision__field_method_details'] as $table) {
      $connection->update($table)
        ->fields(['field_method_details_value' => $markup])
        ->condition('entity_id', $paragraphId)
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache([$paragraphId]);
  }

  /**
   * Asserts paragraph field_method_details contains expected path fragments.
   */
  private function assertParagraphMethodDetailsContains(int $paragraphId, string $contains, string $notContains): void {
    $paragraph = Paragraph::load($paragraphId);
    $this->assertNotNull($paragraph);
    $html = (string) $paragraph->get('field_method_details')->value;
    $this->assertStringContainsString($contains, $html);
    $this->assertStringNotContainsString($notContains, $html);
  }

  /**
   * Asserts the host node's paragraph reference stores normalized markup.
   */
  private function assertHostNodeReferencesNormalizedParagraph(
    $node,
    int $paragraphId,
    string $contains,
    string $notContains,
  ): void {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($node);
    $this->assertSame(
      'Revision created to normalize redirected internal links in nested content.',
      (string) $node->getRevisionLogMessage()
    );

    $paragraphRevisionId = NULL;
    foreach ($node->get('field_how_to_methods_5') as $item) {
      if ((int) $item->target_id === $paragraphId) {
        $paragraphRevisionId = (int) $item->target_revision_id;
        break;
      }
    }
    $this->assertNotNull($paragraphRevisionId);

    $stored = \Drupal::database()->select('paragraph_revision__field_method_details', 'p')
      ->fields('p', ['field_method_details_value'])
      ->condition('entity_id', $paragraphId)
      ->condition('revision_id', $paragraphRevisionId)
      ->execute()
      ->fetchField();
    $this->assertIsString($stored);
    $this->assertStringContainsString($contains, $stored);
    $this->assertStringNotContainsString($notContains, $stored);
  }

}
