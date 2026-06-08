<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\Entity\Redirect;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests the redirect link resolver and normalization manager behaviour.
 *
 * Covers: RedirectLinkResolver, RedirectLinkNormalizationManager,
 * presave hook integration, paragraph normalization, and entity references.
 *
 * @group existing-site
 */
class ResolverTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use RedirectNormalizerTestTrait;

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
   * Tests redirect destination query strings are preserved through resolution.
   */
  public function testResolveRedirectTargetPreservesDestinationQuery(): void {
    $source = 'old-page-' . $this->randomMachineName();
    $targetPath = '/search?category=health';

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect($targetPath);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $source);

    $this->assertTrue($resolved['changed']);
    $this->assertSame('/search?category=health', $resolved['target_path']);

    $text = '<p><a href="/' . $source . '">Search</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($text);
    $this->assertTrue($normalized['changed']);
    $this->assertStringContainsString('/search?category=health', $normalized['text']);
  }

  /**
   * Tests destination query wins over source link query on redirect.
   */
  public function testResolveRedirectTargetDestinationQueryOverridesSourceQuery(): void {
    $source = 'old-page-query-' . $this->randomMachineName();

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect('/search?category=health');
    $redirect->setLanguage('en');
    $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $source . '?foo=1');

    $this->assertTrue($resolved['changed']);
    $this->assertSame('/search?category=health', $resolved['target_path']);
    $this->assertStringNotContainsString('foo=1', $resolved['target_path']);
  }

  /**
   * Tests source link query is kept when redirect destination has none.
   */
  public function testResolveRedirectTargetFallsBackToSourceQuery(): void {
    $source = 'old-page-fallback-' . $this->randomMachineName();

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect('/search');
    $redirect->setLanguage('en');
    $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $source . '?foo=1');

    $this->assertTrue($resolved['changed']);
    $this->assertSame('/search?foo=1', $resolved['target_path']);
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
    // Document targets are not node canonical paths, so node metadata is absent.
    $this->assertStringNotContainsString('data-entity-type="node"', $normalized['text']);
    $this->assertStringNotContainsString('data-entity-uuid=', $normalized['text']);
  }

  /**
   * Tests non-node redirect targets clear stale node metadata attributes.
   */
  public function testDocumentRedirectClearsStaleNodeMetadataInText(): void {
    $source = 'doc-source-stale-' . $this->randomMachineName();
    $target = '/sites/default/files/documents/example-stale.pdf';

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect($target);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $text = '<p><a href="/' . $source . '" data-entity-type="node" data-entity-uuid="stale-uuid" data-entity-substitution="canonical">Doc link</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($text);

    $this->assertTrue($normalized['changed']);
    $this->assertStringContainsString($target, $normalized['text']);
    $this->assertStringNotContainsString('data-entity-type="node"', $normalized['text']);
    $this->assertStringNotContainsString('data-entity-uuid=', $normalized['text']);
    $this->assertStringNotContainsString('data-entity-substitution=', $normalized['text']);
  }

  /**
   * Tests media /download paths stay internal in link fields.
   */
  public function testMediaDownloadRedirectPreservesDownloadPathInLinkField(): void {
    $media = $this->createDocumentMedia('download-' . $this->randomMachineName());
    $source = 'media-download-source-' . $this->randomMachineName();
    $target = '/media/' . $media->id() . '/download';

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect($target);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeRedirectLinkUri('internal:/' . $source);

    $this->assertTrue($normalized['changed']);
    $this->assertSame('internal:' . $target, $normalized['uri']);
    $this->assertStringNotContainsString('entity:media/', $normalized['uri']);
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
   * Tests automated revisions preserve the entity changed timestamp.
   */
  public function testAutomatedRevisionPreservesChangedTimestamp(): void {
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
        'value' => '<p><a href="/' . $sourceStart . '">Needs normalization</a></p>',
        'format' => 'full_html',
      ],
    ]);

    $pastChanged = strtotime('-1 year');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($node);
    $node->setChangedTime($pastChanged);
    $node->setSyncing(TRUE);
    $node->save();

    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($entity);
    $entity->setOriginal(clone $entity);
    $entity->set('body', [
      'value' => '<p><a href="/' . $sourceStart . '">Needs normalization</a></p>',
      'format' => 'full_html',
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($entity, TRUE);
    $this->assertTrue($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($reloaded);
    $this->assertSame($pastChanged, (int) $reloaded->getChangedTime());
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
    $hostRevisionBefore = (int) $node->getRevisionId();
    $this->setParagraphMethodDetailsMarkup(
      $paragraphId,
      '<p><a href="/' . $sourceStart . '">Need docs</a></p>'
    );

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $paragraph = Paragraph::load($paragraphId);
    $this->assertNotNull($paragraph);
    $result = $manager->normalizeEntity($paragraph, TRUE);
    $this->assertTrue($result['changed']);

    $this->assertParagraphMethodDetailsContains($paragraphId, $targetPath, '/' . $sourceStart);
    $this->assertHostNodeReferencesNormalizedParagraph($node, $paragraphId, $targetPath, '/' . $sourceStart);

    $hostNode = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertNotNull($hostNode);
    $this->assertGreaterThan(
      $hostRevisionBefore,
      (int) $hostNode->getRevisionId(),
      'Host node revision should advance when paragraph save chain completes.'
    );
  }

  /**
   * Tests doc-to-doc redirect chains on nested paragraphs (real-id style).
   *
   * Redirect entities may point old doc paths at a newer doc path;
   * normalization should stop at the last redirect entity, not a
   * media/file download route.
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
    $this->setParagraphMethodDetailsMarkup(
      $paragraphId,
      '<p><a href="/' . $oldDoc . '">Need docs</a></p>'
    );

    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $paragraph = Paragraph::load($paragraphId);
    $this->assertNotNull($paragraph);
    $result = $manager->normalizeEntity($paragraph, TRUE);
    $this->assertTrue($result['changed']);

    $this->assertParagraphMethodDetailsContains($paragraphId, '/' . $newDoc, '/' . $oldDoc);
    $this->assertHostNodeReferencesNormalizedParagraph($node, $paragraphId, '/' . $newDoc, '/' . $oldDoc);
  }

  /**
   * Tests paragraph save chain fails when no host node can be resolved.
   */
  public function testParagraphSaveChainThrowsWhenHostNodeCannotBeResolved(): void {
    $paragraph = Paragraph::create([
      'type' => 'method',
      'field_method_type' => 'online',
      'field_method_details' => [
        'value' => '<p><a href="/placeholder">Orphan</a></p>',
        'format' => 'full_html',
      ],
    ]);
    $paragraph->save();
    $this->cleanupEntities[] = $paragraph;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $method = new \ReflectionMethod($manager, 'saveNormalizedParagraphAndAncestorsWithinTransaction');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Failed to resolve host node');
    $method->invoke($manager, $paragraph);
  }

  /**
   * Tests paragraph save chain fails when host node lacks the paragraph embed.
   */
  public function testParagraphSaveChainThrowsWhenHostNodeMissingParagraphReference(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    [$node, $paragraphId] = $this->createHowToWithMethodParagraph();
    $this->setParagraphMethodDetailsMarkup(
      $paragraphId,
      '<p><a href="/' . $sourceStart . '">Need docs</a></p>'
    );
    $this->corruptNodeParagraphFieldTarget((int) $node->id(), 'field_how_to_methods_5');
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache([$paragraphId]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $paragraph = Paragraph::load($paragraphId);
    $this->assertNotNull($paragraph);

    $method = new \ReflectionMethod($manager, 'saveNormalizedParagraphAndAncestorsWithinTransaction');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Failed to update host node');
    $method->invoke($manager, $paragraph);
  }

  // -------------------------------------------------------------------------
  // Paragraph test helpers.
  // -------------------------------------------------------------------------

  /**
   * Asserts paragraph field_method_details contains expected path fragments.
   */
  private function assertParagraphMethodDetailsContains(
    int $paragraphId,
    string $contains,
    string $notContains,
  ): void {
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
