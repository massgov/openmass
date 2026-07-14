<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\path_alias\Entity\PathAlias;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Bulk-style normalization run over 100 nodes through the real queue.
 *
 * Mirrors the drush sweep: nodes are enqueued with the bulk enqueuer
 * (batched queue items, source "drush") and processed by the real queue
 * worker. Categories:
 * - rewrite: link behind a redirect to a published page — must be rewritten.
 * - unpublished: redirect target is a draft — must stay untouched.
 * - trashed: redirect target is in the trash state — must stay untouched.
 * - shadow: redirect source URL still serves a live published page — must
 *   stay untouched.
 * - plain: no redirect involved — body must stay byte-identical.
 *
 * Untouched nodes must also keep their revision ID (no silent resaves).
 *
 * @group existing-site
 */
class ScaleNormalizationTest extends MassExistingSiteBase {

  use RedirectNormalizerTestTrait;

  private const NODES_PER_CATEGORY = 20;

  /**
   * Tests a 100-node bulk run produces rewrites only where expected.
   */
  public function testBulkRunOverHundredNodes(): void {
    $this->purgeNormalizationQueue();
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');

    // Shared fixtures: one target + redirect per category, linked from
    // NODES_PER_CATEGORY source nodes each (many pages linking the same
    // redirected URL is the realistic shape of the prod data).
    $publishedTarget = $this->createTargetNode('published');
    $rewriteSource = 'scale-rewrite-' . strtolower($this->randomMachineName());
    $this->createRedirect($rewriteSource, '/node/' . $publishedTarget->id());

    $draftTarget = $this->createTargetNode('draft');
    $unpublishedSource = 'scale-unpublished-' . strtolower($this->randomMachineName());
    $this->createRedirect($unpublishedSource, '/node/' . $draftTarget->id());

    $trashedTarget = $this->createTargetNode('published');
    $trashedTarget->set('moderation_state', 'trash');
    $trashedTarget->save();
    $trashedSource = 'scale-trashed-' . strtolower($this->randomMachineName());
    $this->createRedirect($trashedSource, '/node/' . $trashedTarget->id());

    // Shadow: the redirect source path is also the alias of a live page.
    $livePage = $this->createTargetNode('published');
    $shadowSource = 'scale-shadow-' . strtolower($this->randomMachineName());
    $alias = PathAlias::create([
      'path' => '/node/' . $livePage->id(),
      'alias' => '/' . $shadowSource,
      'langcode' => 'en',
      'status' => 1,
    ]);
    $alias->save();
    $this->cleanupEntities[] = $alias;
    $shadowTarget = $this->createTargetNode('published');
    $this->createRedirect($shadowSource, '/node/' . $shadowTarget->id());

    $categories = [
      'rewrite' => '/' . $rewriteSource,
      'unpublished' => '/' . $unpublishedSource,
      'trashed' => '/' . $trashedSource,
      'shadow' => '/' . $shadowSource,
      'plain' => '/no-redirect-here-' . strtolower($this->randomMachineName()),
    ];

    $nodes = [];
    $originalBodies = [];
    $originalVids = [];
    foreach ($categories as $category => $href) {
      for ($i = 0; $i < self::NODES_PER_CATEGORY; $i++) {
        $body = '<p>Node ' . $i . ' <a href="' . $href . '">' . $category . ' link</a> and <a href="https://www.irs.gov/">external</a>.</p>';
        $node = $this->createNode([
          'type' => 'page',
          'title' => $this->randomMachineName(),
          'status' => 1,
          'moderation_state' => 'published',
          'body' => ['value' => $body, 'format' => 'full_html'],
        ]);
        $nodes[$category][] = $node;
        $originalBodies[$node->id()] = $body;
        $originalVids[$node->id()] = (int) $node->getRevisionId();
      }
    }
    $this->assertCount(5 * self::NODES_PER_CATEGORY, $originalBodies);

    // Node saves above auto-enqueue via presave; start from a clean queue so
    // the run below exercises only the bulk path.
    $this->purgeNormalizationQueue();
    \Drupal::state()->delete('mass_redirect_normalizer.queue_pending_keys');

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    foreach ($nodes as $categoryNodes) {
      foreach ($categoryNodes as $node) {
        $enqueuer->enqueueIdBulk('node', (int) $node->id());
      }
    }
    $enqueuer->flushEnqueueBuffers();
    $this->drainNormalizationQueue();

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $targetPath = $publishedTarget->toUrl()->toString();

    foreach ($nodes['rewrite'] as $node) {
      $reloaded = $storage->load($node->id());
      $body = (string) $reloaded->get('body')->value;
      $this->assertStringContainsString($targetPath, $body, "rewrite node {$node->id()} must point at the published target");
      $this->assertStringNotContainsString($categories['rewrite'], $body, "rewrite node {$node->id()} must not keep the redirect source URL");
      $this->assertStringContainsString('https://www.irs.gov/', $body, 'external link must survive');
      $this->assertGreaterThan($originalVids[$node->id()], (int) $reloaded->getRevisionId(), "rewrite node {$node->id()} must get a new revision");
    }

    foreach (['unpublished', 'trashed', 'shadow', 'plain'] as $category) {
      foreach ($nodes[$category] as $node) {
        $reloaded = $storage->load($node->id());
        $this->assertSame(
          $originalBodies[$node->id()],
          (string) $reloaded->get('body')->value,
          "$category node {$node->id()} body must stay byte-identical"
        );
        $this->assertSame(
          $originalVids[$node->id()],
          (int) $reloaded->getRevisionId(),
          "$category node {$node->id()} must not get a new revision"
        );
      }
    }

    $this->assertSame(0, \Drupal::queue('mass_redirect_normalizer_link_normalization')->numberOfItems());
  }

  /**
   * Creates an org_page target in the given moderation state.
   */
  private function createTargetNode(string $state) {
    return $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => $state === 'published' ? 1 : 0,
      'moderation_state' => $state,
    ]);
  }

}
