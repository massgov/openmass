<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests redirect normalization eligibility rules.
 *
 * @group existing-site
 */
class EligibilityTest extends MassExistingSiteBase {

  use RedirectNormalizerTestTrait;

  /**
   * Tests nodes with a newer unpublished draft are skipped by bulk processing.
   */
  public function testNewerUnpublishedDraftSkipsNormalization(): void {
    $this->purgeNormalizationQueue();

    $target = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $sourceStart = 'draft-guard-' . $this->randomMachineName();
    $this->createRedirect('/' . $sourceStart, '/node/' . $target->id());
    $targetPath = $target->toUrl()->toString();

    $publishedMarkup = '<p><a href="/' . $sourceStart . '">Published revision</a></p>';
    $page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => [
        'value' => $publishedMarkup,
        'format' => 'full_html',
      ],
    ]);
    $nid = (int) $page->id();
    $publishedRevisionId = (int) $page->getRevisionId();

    $draftMarkup = '<p><a href="/' . $sourceStart . '">Draft revision</a></p>';
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $storage->load($nid);
    $this->assertNotNull($node);
    $node->setNewRevision(TRUE);
    $node->set('moderation_state', 'draft');
    $node->setUnpublished();
    $node->set('body', [
      'value' => $draftMarkup,
      'format' => 'full_html',
    ]);
    $node->save();
    $draftRevisionId = (int) $storage->getLatestRevisionId($nid);
    $this->assertGreaterThan($publishedRevisionId, $draftRevisionId);

    $published = $storage->load($nid);
    $this->assertNotNull($published);
    $this->assertTrue($published->isPublished());
    $this->assertSame($publishedRevisionId, (int) $published->getRevisionId());

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkNormalizationEligibility $eligibility */
    $eligibility = \Drupal::service('mass_redirect_normalizer.eligibility');
    $this->assertFalse($eligibility->isEligible('node', $published));

    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->enqueueById('node', $nid, 'drush');
    $this->drainNormalizationQueue();

    $publishedAfter = $storage->loadRevision($publishedRevisionId);
    $this->assertNotNull($publishedAfter);
    $publishedBody = (string) $publishedAfter->get('body')->value;
    $this->assertStringContainsString('/' . $sourceStart, $publishedBody);
    $this->assertStringNotContainsString($targetPath, $publishedBody);

    $draftAfter = $storage->loadRevision($draftRevisionId);
    $this->assertNotNull($draftAfter);
    $draftBody = (string) $draftAfter->get('body')->value;
    $this->assertStringContainsString('/' . $sourceStart, $draftBody);
    $this->assertStringNotContainsString($targetPath, $draftBody);
  }

}
