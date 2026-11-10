<?php

declare(strict_types=1);

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\path_alias\Entity\PathAlias;
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
   * DP-49254 incoming links stay attached until permanent deletion, then work.
   */
  public function testNodePublicAliasReplacementIsTransferredOnlyAtPurge(): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $public = '/trashbin-public-' . strtolower($this->randomMachineName());
    $this->alias($node, $public . '---unpublished');
    $target = $this->targetNode();
    $middle = '/trashbin-middle-' . strtolower($this->randomMachineName());
    $this->redirect($middle, 'internal:/node/' . $target->id() . '?replacement=1#target');
    $outgoing = $this->redirect($public, 'internal:' . $middle);
    $incoming = [
      $this->redirect($this->source(), 'internal:' . $base . '?campaign=old#original', 302, ['attributes' => ['class' => ['keep']]]),
      $this->redirect($this->source(), 'entity:node/' . $node->id()),
      $this->redirect($this->source(), 'internal:' . $public . '---unpublished'),
    ];
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
      $this->assertSame('internal:/node/' . $target->id(), $saved->getRedirect()['uri']);
      foreach (['uuid', 'redirect_source', 'language', 'status_code'] as $field) {
        $this->assertSame($originals[$index][$field], $saved->toArray()[$field]);
      }
    }
    $destination = $this->reload($incoming[0])->getRedirect();
    $this->assertSame(['replacement' => '1', 'campaign' => 'old'], $destination['options']['query']);
    $this->assertSame('target', $destination['options']['fragment']);
    $this->assertSame(['keep'], $destination['options']['attributes']['class']);
    $this->assertSame(302, (int) $this->reload($incoming[0])->getStatusCode());
    $this->assertSame('internal:/node/' . $target->id(), $this->reload($outgoing)->getRedirect()['uri']);
    foreach ([$base, $public, $public . '---unpublished'] as $path) {
      $matching = \Drupal::entityTypeManager()->getStorage('redirect')->loadByProperties(['redirect_source__path' => ltrim($path, '/')]);
      $this->assertNotEmpty($matching, 'The deleted page URL must retain a redirect: ' . $path);
      $this->assertSame('internal:/node/' . $target->id(), reset($matching)->getRedirect()['uri']);
    }
    // Exercise the real Redirect request subscriber after the original entity
    // and aliases are gone, including query passthrough on the public request.
    $this->drupalGet($incoming[1]->getSource()['path']);
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
   * Unsafe graphs leave the content and all redirects unchanged.
   *
   * @dataProvider unsafeGraphProvider
   */
  public function testUnsafeReplacementDoesNotChangeAnything(string $kind, string $reason): void {
    $node = $this->trashedNode();
    $base = '/node/' . $node->id();
    $alias = '/trashbin-unsafe-' . strtolower($this->randomMachineName());
    $this->alias($node, $alias);
    $incoming = $this->redirect($this->source(), 'internal:' . $base);
    $original = $incoming->toArray();
    $middle = '/trashbin-middle-' . strtolower($this->randomMachineName());
    if ($kind === 'missing') {
      $this->redirect($alias, 'internal:/trashbin-missing-' . $this->randomMachineName());
    }
    elseif ($kind === 'loop') {
      $this->redirect($alias, 'internal:' . $middle);
      $this->redirect($middle, 'internal:' . $alias);
    }
    elseif ($kind === 'conflict') {
      $this->redirect($alias, 'https://example.com/one');
      $this->redirect($base, 'https://example.com/two');
    }
    elseif ($kind === 'query') {
      $this->redirect($alias, 'https://example.com/one', 301, [], ['campaign' => 'special']);
    }
    elseif ($kind === 'language') {
      $incoming->setLanguage('und');
      $incoming->save();
      $incoming = $this->reload($incoming);
      $original = $incoming->toArray();
      $this->redirect($alias, 'https://example.com/one');
    }
    elseif ($kind === 'unpublished') {
      $target = $this->targetNode();
      $target->set('moderation_state', 'unpublished');
      $target->save();
      $this->redirect($alias, 'internal:/node/' . $target->id());
    }
    try {
      $this->service()->prepare($node);
      $this->fail('An unsafe redirect graph must block permanent deletion.');
    }
    catch (UnsafePurgeRedirectException $e) {
      $this->assertStringContainsString($reason, $e->getMessage());
    }
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('node')->load($node->id()));
    $this->assertSame($original, $this->reload($incoming)->toArray());
  }

  public static function unsafeGraphProvider(): array {
    return [
      'no replacement' => ['none', 'No onward replacement'],
      'missing target' => ['missing', 'Missing local replacement'],
      'loop' => ['loop', 'Redirect loop'],
      'conflicting targets' => ['conflict', 'Conflicting replacements'],
      'query-dependent target' => ['query', 'Query-dependent replacement'],
      'language-dependent target' => ['language', 'Language-dependent replacement'],
      'unpublished target' => ['unpublished', 'unpublished replacement'],
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
    $plan = $this->service()->prepare($node);
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

  private function service(): TrashbinPurgeRedirects {
    return new TrashbinPurgeRedirects(\Drupal::database(), \Drupal::entityTypeManager(), \Drupal::service('path_alias.manager'), \Drupal::service('path.validator'), \Drupal::configFactory());
  }

  private function trashedNode(): EntityInterface {
    return \Drupal::entityTypeManager()->getStorage('node')->load($this->createTrashedOrgPage());
  }

  private function targetNode(): EntityInterface {
    return $this->createNode(['type' => 'org_page', 'title' => $this->randomMachineName(), 'moderation_state' => 'published', 'path' => ['pathauto' => 0]]);
  }

  private function alias(EntityInterface $entity, string $path): void {
    $alias = PathAlias::create(['path' => '/' . $entity->getEntityTypeId() . '/' . $entity->id(), 'alias' => $path, 'langcode' => 'en']);
    $alias->save();
    $this->markEntityForCleanup($alias);
  }

  private function source(): string {
    return '/trashbin-source-' . strtolower($this->randomMachineName());
  }

  private function redirect(string $source, string $uri, int $status = 301, array $options = [], array $query = []): Redirect {
    $redirect = Redirect::create(['redirect_source' => ['path' => ltrim($source, '/'), 'query' => $query], 'redirect_redirect' => ['uri' => $uri, 'options' => $options], 'status_code' => $status, 'language' => 'en']);
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
    // Register the new records after the purge inserts them. A teardown hook
    // also cleans up bridges after an assertion failure below.
    foreach ($plan['create'] as $values) {
      $this->bridgeSources[] = $values['redirect_source']['path'];
    }
  }

  private array $bridgeSources = [];

  protected function tearDown(): void {
    foreach ($this->bridgeSources as $source) {
      $storage = \Drupal::entityTypeManager()->getStorage('redirect');
      $storage->delete($storage->loadByProperties(['redirect_source__path' => $source]));
    }
    parent::tearDown();
  }

}
