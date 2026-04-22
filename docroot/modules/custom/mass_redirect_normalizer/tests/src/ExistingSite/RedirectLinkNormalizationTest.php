<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\mass_redirect_normalizer\Drush\Commands\MassRedirectNormalizerCommands;
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

    // Trigger presave normalization on node save.
    $sourceNode->save();

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($sourceNode->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $body = (string) $reloaded->get('body')->value;
    $this->assertStringContainsString($target->toUrl()->toString(), $body);
    $this->assertStringContainsString('data-entity-type="node"', $body);
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

    $command = new MassRedirectNormalizerCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('mass_redirect_normalizer.manager')
    );
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

    $command = new MassRedirectNormalizerCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('mass_redirect_normalizer.manager')
    );
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

    $command = new MassRedirectNormalizerCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('mass_redirect_normalizer.manager')
    );
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

    $command = new MassRedirectNormalizerCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('mass_redirect_normalizer.manager')
    );
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

}
