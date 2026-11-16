<?php

declare(strict_types=1);

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\PathautoState;
use Drupal\redirect\Entity\Redirect;
use Drupal\trashbin\TrashbinPurgeRedirects;
use Drupal\trashbin\UnsafePurgeRedirectException;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Covers deletion hooks, alias removal, and permanent-purge redirect transfer.
 *
 * All deletes target entities created by this test, never purge query results.
 *
 * @group trashbin
 */
class TrashbinPurgeRedirectsTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use TrashbinFixturesTrait;

  /**
   * Redirect source paths created by the purge, for teardown cleanup.
   *
   * @var string[]
   */
  private array $bridgeSources = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->bridgeSources as $source) {
      $storage = \Drupal::entityTypeManager()->getStorage('redirect');
      $storage->delete($storage->loadByProperties(['redirect_source__path' => $source]));
    }
    parent::tearDown();
  }

  /**
   * DP-49254 incoming links stay attached until permanent deletion, then work.
   */
  public function testNodePublicAliasReplacementIsTransferredOnlyAtPurge(): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $public = '/trashbin-public-' . strtolower($this->randomMachineName());
    $this->alias($node, $public . '---unpublished');
    $target = $this->targetNode();
    $middle = '/trashbin-middle-' . strtolower($this->randomMachineName());
    $middle_redirect = $this->redirect($middle, 'internal:/node/' . $target->id() . '?replacement=1#target');
    $outgoing = $this->redirect($public, 'internal:' . $middle);
    $middle_original = $middle_redirect->toArray();
    $outgoing_original = $outgoing->toArray();
    $incoming = [
      $this->redirect($this->source(), 'internal:' . $base . '?campaign=old#original', 302, ['attributes' => ['class' => ['keep']]]),
      $this->redirect($this->source(), 'entity:node/' . $node->id()),
      $this->redirect($this->source(), 'internal:' . $public . '---unpublished'),
    ];
    $friendly = $this->redirect($this->source(), 'internal:/' . $incoming[1]->getSource()['path']);
    $friendly_original = $friendly->toArray();
    $originals = array_map(fn($redirect) => $redirect->toArray(), $incoming);
    $service = $this->service();
    $plan = $service->prepare($node);

    foreach ($incoming as $index => $redirect) {
      $this->assertSame($originals[$index], $this->reload($redirect)->toArray(), 'Planning must preserve the republish model without saving incoming redirects.');
    }
    $this->assertNotEmpty($plan['create']);
    $this->markBridgesForCleanup($plan);
    $service->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertEmpty(\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['path' => $base]));
    foreach ($incoming as $index => $redirect) {
      $saved = $this->reload($redirect);
      $this->assertNotNull($saved, 'Incoming redirect must survive Redirect entity deletion cleanup.');
      $this->assertSame('internal:' . $middle, $saved->getRedirect()['uri']);
      foreach (['uuid', 'redirect_source', 'language', 'status_code'] as $field) {
        $this->assertSame($originals[$index][$field], $saved->toArray()[$field]);
      }
    }
    $destination = $this->reload($incoming[0])->getRedirect();
    $this->assertSame(['campaign' => 'old'], $destination['options']['query']);
    $this->assertSame('original', $destination['options']['fragment']);
    $this->assertSame(['keep'], $destination['options']['attributes']['class']);
    $this->assertSame(302, (int) $this->reload($incoming[0])->getStatusCode());
    $this->assertSame($outgoing_original, $this->reload($outgoing)->toArray(), 'Keep the surviving intermediate destination instead of flattening it.');
    $this->assertSame($middle_original, $this->reload($middle_redirect)->toArray());
    $this->assertSame($friendly_original, $this->reload($friendly)->toArray());
    foreach ([$base, $public, $public . '---unpublished'] as $path) {
      $matching = \Drupal::entityTypeManager()->getStorage('redirect')->loadByProperties(['redirect_source__path' => ltrim($path, '/')]);
      $this->assertNotEmpty($matching, 'The deleted page URL must retain a redirect: ' . $path);
      $this->assertSame('internal:' . $middle, reset($matching)->getRedirect()['uri']);
    }
    // Exercise the real Redirect request subscriber after the original entity
    // and aliases are gone, including query passthrough on the public request.
    $this->drupalGet($friendly->getSource()['path']);
    $this->assertSession()->addressEquals($target->toUrl()->toString() . '?replacement=1#target');
  }

  /**
   * Media download chains survive independently from the media page URL.
   */
  public function testMediaDownloadAliasAndEntityRedirectsSurvive(): void {
    $mid = $this->createTrashedDocumentMediaWithActivity(time());
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    $alias = '/doc/trashbin-' . strtolower($this->randomMachineName());
    $this->alias($media, $alias);
    $target = $this->targetNode();
    $page = $this->redirect($alias, 'internal:/node/' . $target->id());
    $download = $this->redirect($alias . '/download', 'https://example.com/replacement.pdf?version=2#page=3');
    $incoming = $this->redirect($this->source(), 'internal:/media/' . $mid . '/download?campaign=old');
    $entity_incoming = $this->redirect($this->source(), 'entity:media/' . $mid);
    $alias_incoming = $this->redirect($this->source(), 'internal:' . $alias . '/download');

    $plan = $this->service()->prepare($media);
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($media, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('media')->load($mid));
    $this->assertSame($download->getRedirect()['uri'], $this->reload($incoming)->getRedirect()['uri']);
    $this->assertSame('old', $this->reload($incoming)->getRedirect()['options']['query']['campaign']);
    $this->assertSame($download->getRedirect()['uri'], $this->reload($alias_incoming)->getRedirect()['uri']);
    $this->assertSame($page->getRedirect()['uri'], $this->reload($entity_incoming)->getRedirect()['uri']);
    foreach (['/media/' . $mid, '/media/' . $mid . '/download'] as $path) {
      $this->assertNotEmpty(\Drupal::entityTypeManager()->getStorage('redirect')->loadByProperties(['redirect_source__path' => ltrim($path, '/')]));
    }
  }

  /**
   * An explicit replacement takes precedence over retaining an automatic link.
   */
  public function testAutomaticRedirectUsesOnwardReplacementWhenAvailable(): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $public = '/trashbin-public-' . strtolower($this->randomMachineName());
    $this->alias($node, $public . '---unpublished');
    $automatic = $this->redirect($public, 'internal:' . $base);
    $target = $this->targetNode();
    $this->redirect($public . '---unpublished', 'internal:/node/' . $target->id());
    $incoming = $this->redirect($this->source(), 'internal:' . $base);

    $plan = $this->service()->prepare($node);
    $this->assertEmpty($plan['preserve'], 'A real replacement must take precedence over the self-reference exception.');
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame('internal:/node/' . $target->id(), $this->reload($automatic)->getRedirect()['uri']);
    $this->assertSame('internal:/node/' . $target->id(), $this->reload($incoming)->getRedirect()['uri']);
  }

  /**
   * Conflicting fallback choices do not overwrite existing source mappings.
   */
  public function testConflictingFallbacksRemoveOnlyAmbiguousIncomingLinks(): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $first = $this->source();
    $second = $this->source();
    $third = $this->source();
    foreach ([$first, $second, $third] as $public) {
      $this->alias($node, $public . '---unpublished');
    }
    $target_one = $this->targetNode();
    $target_two = $this->targetNode();
    $destination_one = 'internal:/node/' . $target_one->id();
    $destination_two = 'internal:/node/' . $target_two->id();
    $one = $this->redirect($first, $destination_one, 302);
    $two = $this->redirect($second, $destination_two);
    $one_original = $one->toArray();
    $two_original = $two->toArray();
    $exact_one = $this->redirect($this->source(), 'internal:' . $first);
    $exact_two = $this->redirect($this->source(), 'internal:' . $second);
    $ambiguous = $this->redirect($this->source(), 'internal:' . $base);
    $middle = $this->redirect($this->source(), 'internal:' . $base);
    $chain = $this->redirect($third, 'internal:/' . $middle->getSource()['path']);
    $upstream = $this->redirect($this->source(), 'internal:/' . $chain->getSource()['path']);

    $plan = $this->service()->prepare($node);
    $this->assertStringContainsString('Conflicting replacements', $plan['remove'][$ambiguous->id()]);
    $this->assertNotEmpty($plan['warnings'], 'Report missing URL bridges with ambiguous destinations.');
    $this->assertEmpty($plan['create'], 'Do not choose the newest conflicting destination for a missing URL.');
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame($one_original, $this->reload($one)->toArray());
    $this->assertSame($two_original, $this->reload($two)->toArray());
    $this->assertSame($destination_one, $this->reload($exact_one)->getRedirect()['uri']);
    $this->assertSame($destination_two, $this->reload($exact_two)->getRedirect()['uri']);
    foreach ([$ambiguous, $middle, $chain, $upstream] as $redirect) {
      $this->assertNull($this->reload($redirect));
    }
    $this->drupalGet(ltrim($first, '/'));
    $this->assertSession()->addressEquals($target_one->toUrl()->toString());
    $this->drupalGet(ltrim($second, '/'));
    $this->assertSession()->addressEquals($target_two->toUrl()->toString());
  }

  /**
   * A broken branch cannot delete a valid redirect on another node address.
   */
  public function testLoopCleanupPreservesIndependentWorkingReplacement(): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $alias = $this->source();
    $this->alias($node, $alias);
    $working = $this->redirect($base, 'https://example.com/working');
    $working_original = $working->toArray();
    $first = $this->source();
    $second = $this->source();
    $loop = [
      $this->redirect($alias, 'internal:' . $first),
      $this->redirect($first, 'internal:' . $second),
      $this->redirect($second, 'internal:' . $first),
      $this->redirect($this->source(), 'internal:' . $alias),
    ];
    $upstream = $this->redirect($this->source(), 'internal:/' . $loop[3]->getSource()['path']);
    $valid_incoming = $this->redirect($this->source(), 'internal:' . $base);

    $plan = $this->service()->prepare($node);
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    foreach (array_merge($loop, [$upstream]) as $redirect) {
      $this->assertNull($this->reload($redirect));
    }
    $this->assertSame($working_original, $this->reload($working)->toArray());
    $this->assertSame('https://example.com/working', $this->reload($valid_incoming)->getRedirect()['uri']);
  }

  /**
   * Removing an invalid redirect must not remove a usable underlying route.
   */
  public function testUpstreamLinkToSurvivingPageRemainsAfterBrokenRedirectRemoval(): void {
    $node = $this->trashedNode();
    $alias = $this->source();
    $this->alias($node, $alias);
    $target = $this->targetNode();
    $target_path = '/node/' . $target->id();
    $broken = $this->redirect($target_path, 'internal:' . $this->source());
    $outgoing = $this->redirect($alias, 'internal:' . $target_path);
    $upstream = $this->redirect($this->source(), 'internal:' . $target_path);
    $original = $upstream->toArray();

    $plan = $this->service()->prepare($node);
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertNull($this->reload($broken));
    $this->assertNull($this->reload($outgoing));
    $this->assertSame($original, $this->reload($upstream)->toArray(), 'The existing target page remains usable once its broken redirect is removed.');
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('node')->load($target->id()));
  }

  /**
   * A media page loop is removed without affecting its working download URL.
   */
  public function testMediaPageLoopDoesNotRemoveWorkingDownloadRedirects(): void {
    $mid = $this->createTrashedDocumentMediaWithActivity(time());
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    $base = '/media/' . $mid;
    $alias = $this->source();
    $this->alias($media, $alias);
    $middle = $this->source();
    $broken = [
      $this->redirect($alias, 'internal:' . $middle),
      $this->redirect($middle, 'internal:' . $alias),
      $this->redirect($this->source(), 'entity:media/' . $mid),
    ];
    $download = $this->redirect($alias . '/download', 'https://example.com/working.pdf');
    $incoming = $this->redirect($this->source(), 'internal:' . $base . '/download');

    $plan = $this->service()->prepare($media);
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($media, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('media')->load($mid));
    foreach ($broken as $redirect) {
      $this->assertNull($this->reload($redirect));
    }
    $this->assertSame('https://example.com/working.pdf', $this->reload($download)->getRedirect()['uri']);
    $this->assertSame('https://example.com/working.pdf', $this->reload($incoming)->getRedirect()['uri']);
  }

  /**
   * Routing variations on a surviving path can remain without being flattened.
   */
  public function testSurvivingQueryDependentChainIsPreserved(): void {
    $node = $this->trashedNode();
    $alias = $this->source();
    $this->alias($node, $alias);
    $middle = $this->source();
    $general = $this->redirect($middle, 'https://example.com/general');
    $specific = $this->redirect($middle, 'https://example.com/specific', 301, [], ['campaign' => 'special']);
    $outgoing = $this->redirect($alias, 'internal:' . $middle);
    $incoming = $this->redirect($this->source(), 'internal:/node/' . $node->id());

    $plan = $this->service()->prepare($node);
    $this->assertEmpty($plan['remove']);
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame('internal:' . $middle, $this->reload($outgoing)->getRedirect()['uri']);
    $this->assertSame('internal:' . $middle, $this->reload($incoming)->getRedirect()['uri']);
    $this->assertSame('https://example.com/general', $this->reload($general)->getRedirect()['uri']);
    $this->assertSame('https://example.com/specific', $this->reload($specific)->getRedirect()['uri']);
  }

  /**
   * Different healthy routes to the same target are not conflicting choices.
   */
  public function testConvergingFriendlyChainsKeepTheirIntermediateDestinations(): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $first = $this->source();
    $second = $this->source();
    $this->alias($node, $first . '---unpublished');
    $this->alias($node, $second . '---unpublished');
    $target = $this->targetNode();
    $middle_path = $this->source();
    $middle = $this->redirect($middle_path, 'internal:/node/' . $target->id());
    $one = $this->redirect($first, 'internal:' . $middle_path);
    $two = $this->redirect($second, 'internal:/node/' . $target->id());
    $friendly = $this->redirect($this->source(), 'internal:' . $base);
    $originals = array_map(fn($redirect) => $redirect->toArray(), [$middle, $one, $two]);

    $plan = $this->service()->prepare($node);
    $this->assertEmpty($plan['remove'], 'Both choices reach the same target; the public friendly URL must remain usable.');
    $this->assertEmpty($plan['warnings']);
    $this->assertNotEmpty($plan['create'], 'Retain direct access to former addresses through URL bridges.');
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($node, $plan);

    foreach ([$middle, $one, $two] as $index => $redirect) {
      $this->assertSame($originals[$index], $this->reload($redirect)->toArray());
    }
    $this->drupalGet($friendly->getSource()['path']);
    $this->assertSession()->addressEquals($target->toUrl()->toString());
  }

  /**
   * Loop detection depends on repeated records rather than chain length.
   *
   * @dataProvider longChainProvider
   */
  public function testLongChainIsRemovedOnlyWhenItLoops(bool $loop): void {
    $node = $this->trashedNode();
    $alias = $this->source();
    $this->alias($node, $alias);
    $paths = [];
    for ($index = 0; $index < 22; $index++) {
      $paths[] = $this->source();
    }
    $redirects = [];
    foreach ($paths as $index => $path) {
      $destination = isset($paths[$index + 1])
        ? 'internal:' . $paths[$index + 1]
        : ($loop ? 'internal:' . $paths[0] : 'https://example.com/final');
      $redirects[] = $this->redirect($path, $destination);
    }
    $outgoing = $this->redirect($alias, 'internal:' . $paths[0]);
    $plan = $this->service()->prepare($node);
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    if ($loop) {
      foreach (array_merge($redirects, [$outgoing]) as $redirect) {
        $this->assertNull($this->reload($redirect));
      }
    }
    else {
      $this->assertSame('internal:' . $paths[0], $this->reload($outgoing)->getRedirect()['uri']);
      foreach ($redirects as $redirect) {
        $this->assertSame($redirect->getRedirect(), $this->reload($redirect)->getRedirect());
      }
    }
  }

  public static function longChainProvider(): array {
    return [
      'valid chain beyond twenty hops' => [FALSE],
      'loop beyond twenty hops' => [TRUE],
    ];
  }

  /**
   * Redirect cleanup never blocks purge and preserves independent valid rules.
   *
   * @dataProvider cleanupGraphProvider
   */
  public function testRedirectProblemsDoNotBlockPurge(string $kind, ?string $reason, bool $with_automatic = FALSE): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $alias = '/trashbin-unsafe-' . strtolower($this->randomMachineName());
    $this->alias($node, $with_automatic ? $alias . '---unpublished' : $alias);
    if ($with_automatic) {
      $automatic = $this->redirect($alias, 'internal:' . $base);
      $automatic_original = $automatic->toArray();
      $alias .= '---unpublished';
    }
    $incoming = $this->redirect($this->source(), 'internal:' . $base);
    $original = $incoming->toArray();
    $unrelated = $this->redirect($this->source(), 'https://example.com/unrelated');
    $unrelated_original = $unrelated->toArray();
    $outgoing = [];
    $middle = '/trashbin-middle-' . strtolower($this->randomMachineName());
    if ($kind === 'missing') {
      $outgoing[] = $this->redirect($alias, 'internal:/trashbin-missing-' . $this->randomMachineName());
    }
    elseif ($kind === 'loop') {
      $outgoing[] = $this->redirect($alias, 'internal:' . $middle);
      $outgoing[] = $this->redirect($middle, 'internal:' . $alias);
    }
    elseif ($kind === 'conflict') {
      $outgoing[] = $this->redirect($alias, 'https://example.com/one');
      $outgoing[] = $this->redirect($base, 'https://example.com/two');
    }
    elseif ($kind === 'query') {
      $outgoing[] = $this->redirect($alias, 'https://example.com/one', 301, [], ['campaign' => 'special']);
    }
    elseif ($kind === 'language') {
      $incoming->setLanguage('und');
      $incoming->save();
      $incoming = $this->reload($incoming);
      $original = $incoming->toArray();
      $outgoing[] = $this->redirect($alias, 'https://example.com/one');
    }
    elseif ($kind === 'unpublished') {
      $target = $this->targetNode();
      $target->set('moderation_state', 'unpublished');
      $target->save();
      $outgoing[] = $this->redirect($alias, 'internal:/node/' . $target->id());
    }
    $plan = $this->service()->prepare($node);
    if ($reason !== NULL) {
      $this->assertStringContainsString($reason, $plan['remove'][$incoming->id()]);
    }
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame($original, $this->reload($incoming)->toArray());
    if ($with_automatic) {
      $this->assertSame($automatic_original, $this->reload($automatic)->toArray());
    }
    $this->markBridgesForCleanup($plan);
    $this->service()->delete($node, $plan);

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    if ($reason !== NULL) {
      $this->assertNull($this->reload($incoming));
    }
    else {
      $expected = $kind === 'conflict' ? 'https://example.com/two' : 'internal:/node/' . $target->id();
      $this->assertSame($expected, $this->reload($incoming)->getRedirect()['uri']);
    }
    foreach ($outgoing as $redirect) {
      if (in_array($kind, ['missing', 'loop'], TRUE)) {
        $this->assertNull($this->reload($redirect), 'Remove the broken downstream records as well as incoming links.');
      }
      else {
        $this->assertSame($redirect->getRedirect()['uri'], $this->reload($redirect)->getRedirect()['uri'], 'Keep each existing valid source and destination.');
      }
    }
    if ($with_automatic) {
      if (in_array($kind, ['missing', 'loop', 'query'], TRUE)) {
        $this->assertNull($this->reload($automatic));
      }
      else {
        $expected = match ($kind) {
          'conflict' => 'https://example.com/two',
          'language' => 'https://example.com/one',
          'unpublished' => 'internal:/node/' . $target->id(),
          default => 'internal:' . $base,
        };
        $this->assertSame($expected, $this->reload($automatic)->getRedirect()['uri']);
      }
    }
    $this->assertSame($unrelated_original, $this->reload($unrelated)->toArray());
  }

  public static function cleanupGraphProvider(): array {
    return [
      'no replacement' => ['none', 'No onward replacement'],
      'missing target' => ['missing', 'Missing local replacement'],
      'loop' => ['loop', 'Redirect loop'],
      'different targets selected by exact path' => ['conflict', NULL],
      'query-dependent target' => ['query', 'Query-dependent replacement'],
      'language-dependent target' => ['language', 'Language-dependent replacement'],
      'unpublished target retained' => ['unpublished', NULL],
      'incoming without replacement alongside automatic redirect' => ['none', 'No onward replacement', TRUE],
      'missing target alongside automatic redirect' => ['missing', 'Missing local replacement', TRUE],
      'loop alongside automatic redirect' => ['loop', 'Redirect loop', TRUE],
      'different targets alongside automatic redirect' => ['conflict', NULL, TRUE],
      'query-dependent target alongside automatic redirect' => ['query', 'Query-dependent replacement', TRUE],
      'language-dependent target alongside automatic redirect' => ['language', 'Language-dependent replacement', TRUE],
      'unpublished target alongside automatic redirect' => ['unpublished', NULL, TRUE],
    ];
  }

  /**
   * A redirect save failure rolls back deletion and earlier redirect updates.
   */
  public function testFailureRollsBackRedirectChangesAndEntityDeletion(): void {
    $node = $this->trashedNode();
    $alias = '/trashbin-rollback-' . strtolower($this->randomMachineName());
    $this->alias($node, $alias);
    $this->redirect($alias, 'https://example.com/replacement');
    $incoming = $this->redirect($this->source(), 'internal:/node/' . $node->id());
    $original = $incoming->toArray();
    $broken_alias = $alias . '-broken';
    $this->alias($node, $broken_alias);
    $broken = $this->redirect($broken_alias, 'internal:' . $this->source());
    $broken_original = $broken->toArray();
    $plan = $this->service()->prepare($node);
    $this->assertArrayHasKey($broken->id(), $plan['remove']);
    $failing = $this->createMock(EntityInterface::class);
    $failing->method('getEntityTypeId')->willReturn('node');
    $failing->method('id')->willReturn($node->id());
    $failing->method('delete')->willReturnCallback(function () use ($node): void {
      $node->delete();
      throw new \RuntimeException('Simulated failure after entity deletion.');
    });
    $exception = NULL;
    try {
      $this->service()->delete($failing, $plan);
    }
    catch (\Exception $e) {
      $exception = $e;
    }
    $this->assertNotNull($exception);
    $this->assertSame('Simulated failure after entity deletion.', $exception->getMessage());
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame($original, $this->reload($incoming)->toArray());
    $this->assertSame($broken_original, $this->reload($broken)->toArray(), 'A failed purge must also roll back redirect removals.');
    $this->assertNotEmpty(\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['path' => '/node/' . $node->id()]));
  }

  /**
   * A redirect edited after planning cannot be overwritten by the purge.
   */
  public function testConcurrentRedirectEditBlocksDeletion(): void {
    $node = $this->trashedNode();
    $this->redirect('/node/' . $node->id(), 'https://example.com/one');
    $incoming = $this->redirect($this->source(), 'entity:node/' . $node->id());
    $service = $this->service();
    $plan = $service->prepare($node);
    $incoming->set('redirect_redirect', ['uri' => 'https://example.com/editor-change']);
    $incoming->save();
    try {
      $service->delete($node, $plan);
      $this->fail('A concurrent redirect edit must block deletion.');
    }
    catch (UnsafePurgeRedirectException $e) {
      $this->assertStringContainsString('changed during purge', $e->getMessage());
    }
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame('https://example.com/editor-change', $this->reload($incoming)->getRedirect()['uri']);
  }

  /**
   * Records planned for removal are protected from concurrent edits as well.
   */
  public function testConcurrentEditOfBrokenRedirectBlocksDeletion(): void {
    $node = $this->trashedNode();
    $incoming = $this->redirect($this->source(), 'internal:/node/' . $node->id());
    $service = $this->service();
    $plan = $service->prepare($node);
    $this->assertArrayHasKey($incoming->id(), $plan['remove']);
    $incoming->set('redirect_redirect', ['uri' => 'https://example.com/editor-fix']);
    $incoming->save();
    try {
      $service->delete($node, $plan);
      $this->fail('Do not remove a redirect fixed by an editor after planning.');
    }
    catch (UnsafePurgeRedirectException $e) {
      $this->assertStringContainsString('changed during purge', $e->getMessage());
    }
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame('https://example.com/editor-fix', $this->reload($incoming)->getRedirect()['uri']);
  }

  private function service(): TrashbinPurgeRedirects {
    return new TrashbinPurgeRedirects(
      \Drupal::database(),
      \Drupal::entityTypeManager(),
      \Drupal::service('path_alias.manager'),
      \Drupal::service('path.validator'),
      \Drupal::configFactory(),
    );
  }

  private function trashedNode(): EntityInterface {
    return \Drupal::entityTypeManager()->getStorage('node')->load($this->createTrashedOrgPage());
  }

  private function targetNode(): EntityInterface {
    return $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'moderation_state' => 'published',
      'path' => ['pathauto' => PathautoState::SKIP],
    ]);
  }

  private function alias(EntityInterface $entity, string $path): void {
    $alias = PathAlias::create([
      'path' => '/' . $entity->getEntityTypeId() . '/' . $entity->id(),
      'alias' => $path,
      'langcode' => 'en',
    ]);
    $alias->save();
    $this->markEntityForCleanup($alias);
  }

  private function source(): string {
    return '/trashbin-source-' . strtolower($this->randomMachineName());
  }

  private function redirect(string $source, string $uri, int $status = 301, array $options = [], array $query = []): Redirect {
    $redirect = Redirect::create([
      'redirect_source' => ['path' => ltrim($source, '/'), 'query' => $query],
      'redirect_redirect' => ['uri' => $uri, 'options' => $options],
      'status_code' => $status,
      'language' => 'en',
    ]);
    $redirect->save();
    $this->markEntityForCleanup($redirect);
    return $this->reload($redirect);
  }

  private function reload(Redirect $redirect): ?Redirect {
    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $storage->resetCache([$redirect->id()]);
    return $storage->load($redirect->id());
  }

  private function markBridgesForCleanup(array $plan): void {
    // Track sources before deletion so teardown also cleans up bridges if a
    // subsequent assertion fails.
    foreach ($plan['create'] as $values) {
      $this->bridgeSources[] = $values['redirect_source']['path'];
    }
  }

}
