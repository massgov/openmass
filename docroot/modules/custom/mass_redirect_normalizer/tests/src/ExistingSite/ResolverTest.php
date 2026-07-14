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
   * Tests Html::load()/serialize() round-trip preserves non-link rich text.
   *
   * Redirect rewriting re-serializes the entire field value. Accented text,
   * Cyrillic, &nbsp;, and embedded <drupal-media> must survive byte-for-byte
   * outside the rewritten anchor.
   */
  public function testRichTextNormalizationPreservesNonAsciiContentAndMediaEmbed(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    [$sourceStart] = $this->createRedirectChain($target);

    $media = $this->createDocumentMedia('rich-text-' . $this->randomMachineName());
    $mediaTag = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" data-view-mode="default"></drupal-media>';
    $originalLink = '<a href="/' . $sourceStart . '">redirecting link</a>';
    $before = '<p>Café résumé — Москва&nbsp;and ' . $mediaTag . ' before ' . $originalLink . ' after.</p>';

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $normalized = $service->normalizeRedirectLinksInText($before);

    $this->assertTrue($normalized['changed']);

    $parts = explode($originalLink, $before, 2);
    $this->assertCount(2, $parts, 'Fixture must contain a single redirecting link.');
    $this->assertSame(
      $parts[0],
      substr($normalized['text'], 0, strlen($parts[0])),
      'Non-link prefix must survive byte-for-byte through DOM round-trip.'
    );
    $this->assertSame(
      $parts[1],
      substr($normalized['text'], -strlen($parts[1])),
      'Non-link suffix must survive byte-for-byte through DOM round-trip.'
    );

    $linkStart = strlen($parts[0]);
    $linkEnd = strlen($normalized['text']) - strlen($parts[1]);
    $rewrittenLink = substr($normalized['text'], $linkStart, $linkEnd - $linkStart);
    $targetPath = $target->toUrl()->toString();
    $this->assertStringContainsString('href="' . $targetPath . '"', $rewrittenLink);
    $this->assertStringContainsString('data-entity-type="node"', $rewrittenLink);
    $this->assertStringContainsString('data-entity-substitution="canonical"', $rewrittenLink);
    $this->assertStringContainsString('data-entity-uuid="' . $target->uuid() . '"', $rewrittenLink);
    $this->assertStringContainsString('>redirecting link</a>', $rewrittenLink);
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
   * Tests redirects to unpublished nodes are ignored for URL rewriting.
   */
  public function testRedirectToUnpublishedNodeIsIgnored(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $source = 'to-unpublished-' . $this->randomMachineName();
    $this->createRedirect($source, '/node/' . $target->id());

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $href = '/' . $source . '?foo=1#bar';

    $resolved = $service->resolveRedirectTarget($href);
    $this->assertFalse($resolved['changed']);

    $text = '<p><a href="' . $href . '">Unpublished</a></p>';
    $normalizedText = $service->normalizeRedirectLinksInText($text);
    $this->assertFalse($normalizedText['changed']);
    $this->assertStringContainsString($href, $normalizedText['text']);

    $originalUri = 'internal:' . $href;
    $normalizedUri = $service->normalizeRedirectLinkUri($originalUri);
    $this->assertFalse($normalizedUri['changed']);
    $this->assertSame($originalUri, $normalizedUri['uri']);
  }

  /**
   * Tests redirects to trashed nodes are ignored for URL rewriting.
   */
  public function testRedirectToTrashedNodeIsIgnored(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $target->set('moderation_state', 'trash');
    $target->save();

    $source = 'to-trashed-' . $this->randomMachineName();
    $this->createRedirect($source, '/node/' . $target->id());

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $source);
    $this->assertFalse($resolved['changed']);
  }

  /**
   * Tests a published node with a newer draft is still a valid target.
   *
   * The published default revision is what visitors see, so normalization
   * must not be blocked by the existence of a forward (draft) revision.
   */
  public function testRedirectToPublishedNodeWithNewerDraftIsNormalized(): void {
    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $target->setNewRevision(TRUE);
    $target->set('moderation_state', 'draft');
    $target->setTitle($target->getTitle() . ' draft');
    $target->save();

    $source = 'to-published-with-draft-' . $this->randomMachineName();
    $this->createRedirect($source, '/node/' . $target->id());

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $resolved = $service->resolveRedirectTarget('/' . $source);
    $this->assertTrue($resolved['changed']);
    $this->assertNotEmpty($resolved['node']);
    $this->assertEquals($target->id(), $resolved['node']->id());
  }

  /**
   * Tests aliased document download redirects are ignored for unpublished media.
   */
  public function testRedirectToUnpublishedAliasedDocumentIsIgnored(): void {
    $media = $this->createDocumentMedia('unpublished-' . $this->randomMachineName(), [
      'status' => 0,
      'moderation_state' => 'draft',
    ]);

    $aliasPath = '/doc/' . $this->randomMachineName() . '/download';
    $alias = PathAlias::create([
      'path' => '/media/' . $media->id() . '/download',
      'alias' => $aliasPath,
      'langcode' => 'en',
      'status' => 1,
    ]);
    $alias->save();
    $this->cleanupEntities[] = $alias;

    $source = 'to-unpublished-doc-' . $this->randomMachineName();
    $this->createRedirect($source, $aliasPath);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $href = '/' . $source . '?foo=1#bar';

    $resolved = $service->resolveRedirectTarget($href);
    $this->assertFalse($resolved['changed']);

    $text = '<p><a href="' . $href . '">Unpublished doc</a></p>';
    $normalizedText = $service->normalizeRedirectLinksInText($text);
    $this->assertFalse($normalizedText['changed']);
    $this->assertStringContainsString($href, $normalizedText['text']);

    $originalUri = 'internal:' . $href;
    $normalizedUri = $service->normalizeRedirectLinkUri($originalUri);
    $this->assertFalse($normalizedUri['changed']);
    $this->assertSame($originalUri, $normalizedUri['uri']);
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
   * Tests ambiguous source-path redirects are not applied to text or link fields.
   */
  public function testAmbiguousSourcePathRedirectIsIgnored(): void {
    $targetA = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $targetB = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $source = 'ambiguous-source-' . $this->randomMachineName();

    $redirectA = Redirect::create();
    $redirectA->setRedirect('node/' . $targetA->id());
    $redirectA->setSource($source, ['variant' => 'a']);
    $redirectA->setLanguage('en');
    $redirectA->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirectA->save();
    $this->cleanupEntities[] = $redirectA;

    $redirectB = Redirect::create();
    $redirectB->setRedirect('node/' . $targetB->id());
    $redirectB->setSource($source, ['variant' => 'b']);
    $redirectB->setLanguage('en');
    $redirectB->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
    $redirectB->save();
    $this->cleanupEntities[] = $redirectB;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $href = '/' . $source . '?foo=1';
    $resolved = $service->resolveRedirectTarget($href);
    $this->assertFalse($resolved['changed']);

    $text = '<p><a href="' . $href . '">Ambiguous</a></p>';
    $normalized = $service->normalizeRedirectLinksInText($text);
    $this->assertFalse($normalized['changed']);
    $this->assertStringContainsString($href, $normalized['text']);

    $uriNormalized = $service->normalizeRedirectLinkUri('internal:' . $href);
    $this->assertFalse($uriNormalized['changed']);
    $this->assertSame('internal:' . $href, $uriNormalized['uri']);
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
   * Tests redirected document links are left unchanged in link fields.
   */
  public function testDocumentRedirectIsIgnoredInLinkField(): void {
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
    $original = 'internal:/' . $source . '?download=1#part';
    $normalized = $service->normalizeRedirectLinkUri($original);

    $this->assertFalse($normalized['changed']);
    $this->assertSame($original, $normalized['uri']);
  }

  /**
   * Tests collection View redirects are not rewritten in link fields.
   *
   * Untitled card links rely on ComputedLinkTitle, which cannot resolve
   * /collections/* routes (route param "collection" is not an entity type).
   */
  public function testCollectionRedirectIsIgnoredInLinkField(): void {
    $collectionPath = '/collections/massachusetts-labor-and-workforce-blog';
    $source = 'collection-link-source-' . $this->randomMachineName();

    $redirect = Redirect::create();
    $redirect->setSource($source);
    $redirect->setRedirect('https://www.mass.gov' . $collectionPath);
    $redirect->setLanguage('en');
    $redirect->setStatusCode(301);
    $redirect->save();
    $this->cleanupEntities[] = $redirect;

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkResolver $service */
    $service = \Drupal::service('mass_redirect_normalizer.resolver');
    $original = 'http://mass.gov/' . $source;

    $resolved = $service->resolveRedirectTarget($original);
    $this->assertTrue($resolved['changed']);
    $this->assertSame($collectionPath, $resolved['target_path']);
    $this->assertNull($resolved['entity']);

    $normalized = $service->normalizeRedirectLinkUri($original);
    $this->assertFalse($normalized['changed']);
    $this->assertSame($original, $normalized['uri']);

    $text = '<p><a href="' . $original . '">Labor blog</a></p>';
    $textNormalized = $service->normalizeRedirectLinksInText($text);
    $this->assertTrue($textNormalized['changed']);
    $this->assertStringContainsString($collectionPath, $textNormalized['text']);
  }

  /**
   * Tests entityFromUrl safely ignores non-entity collection routes.
   */
  public function testEntityFromUrlIgnoresCollectionRoute(): void {
    $url = \Drupal\Core\Url::fromUri('internal:/collections/massachusetts-labor-and-workforce-blog');
    $this->assertNull(\Drupal\mayflower\Helper::entityFromUrl($url));
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
   * Tests entity reference rewrite skips unpublished node targets.
   */
  public function testEntityReferenceRewriteSkipsUnpublishedNodeTarget(): void {
    $sourcePerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $targetPerson = $this->createNode([
      'type' => 'person',
      'title' => $this->randomMachineName(),
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $this->createRedirect('/node/' . $sourcePerson->id(), '/node/' . $targetPerson->id());

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
   * Tests entity reference rewrite skips unpublished media targets.
   */
  public function testEntityReferenceRewriteSkipsUnpublishedMediaTarget(): void {
    $sourceMedia = $this->createDocumentMedia('source-unpublished-' . $this->randomMachineName());
    $targetMedia = $this->createDocumentMedia('target-unpublished-' . $this->randomMachineName(), [
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $this->createRedirect('/media/' . $sourceMedia->id(), '/media/' . $targetMedia->id());

    $binder = $this->createNode([
      'type' => 'binder',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_downloads' => [
        ['target_id' => $sourceMedia->id()],
      ],
    ]);
    $binder->set('field_downloads', [
      ['target_id' => $sourceMedia->id()],
    ]);

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager $manager */
    $manager = \Drupal::service('mass_redirect_normalizer.manager');
    $result = $manager->normalizeEntity($binder, TRUE);
    $this->assertFalse($result['changed']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->load($binder->id());
    $this->assertNotNull($reloaded);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $this->assertSame((int) $sourceMedia->id(), (int) $reloaded->get('field_downloads')->target_id);
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
