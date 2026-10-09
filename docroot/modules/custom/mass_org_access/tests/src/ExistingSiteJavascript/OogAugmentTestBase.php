<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSiteJavascript;

use Drupal\Core\Entity\EntityInterface;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Vocabulary;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use MassGov\Dtt\MassExistingSiteSelenium2DriverTestBase;

/**
 * Shared setup and helpers for the Owner Groups augmentation JS tests.
 */
abstract class OogAugmentTestBase extends MassExistingSiteSelenium2DriverTestBase {

  use TaxonomyCreationTrait;
  use MediaCreationTrait;

  protected const OOG_INPUT_JS = 'document.querySelector(\'input[name="field_content_organization[target_id]"]\').value || ""';

  protected const ORG_INPUT_JS = 'document.querySelector(\'input[name="field_organizations[0][target_id]"],input[name="field_binder_ref_organization[0][target_id]"],input[name="field_decision_ref_organization[0][target_id]"],input[name="field_person_ref_org[0][target_id]"]\')';

  protected function setUp(): void {
    parent::setUp();
    \Drupal::state()->delete('mass_org_access.enforce');
  }

  protected function tearDown(): void {
    // Never leave enforcement on for a shared environment (a test may flip it).
    \Drupal::state()->delete('mass_org_access.enforce');
    parent::tearDown();
  }

  /**
   * Clicks the form's primary Save button via JS.
   *
   * Avoids Selenium's coordinate-based click, which CI intermittently reports
   * as "intercepted" when the sticky footer overlaps the button. A
   * programmatic click still fires the capture-phase listener that gates the
   * lockout confirmation and still submits the form.
   */
  protected function clickSaveButton(): void {
    $clicked = $this->getSession()->evaluateScript(
      "(function(){var b=Array.prototype.slice.call(document.querySelectorAll("
      . "'input[type=\"submit\"][name=\"op\"], button[type=\"submit\"][name=\"op\"]'"
      . ")).find(function(x){return (x.value||x.textContent||'').trim()==='Save';});"
      . "if(b){b.click();return true;}return false;})()"
    );
    $this->assertTrue((bool) $clicked, 'The Save button must be present on the form.');
  }

  /**
   * Builds the org_page + mapping term + editable entity + admin login.
   *
   * @return array{orgPage: \Drupal\node\NodeInterface, term: \Drupal\taxonomy\TermInterface, entity: \Drupal\Core\Entity\EntityInterface}
   *   Test context with the created org_page, mapped term, and entity
   *   whose edit form is now loaded in the browser session.
   */
  protected function setupEditForm(string $entityType, string $bundle, array $extraEntityFields = []): array {
    $orgPage = $this->createNode([
      'type' => 'org_page',
      'title' => 'OOG Augment OrgPage ' . $this->randomMachineName(6),
      'status' => 1,
    ]);
    $term = $this->createTerm(
      Vocabulary::load('user_organization'),
      [
        'name' => 'OOG Augment Term ' . $this->randomMachineName(6),
      ]
    );
    // The org_page's own Permission Groups are the direct source the widget
    // pulls from when the author adds this org to field_organizations.
    $orgPage->set('field_content_organization', [['target_id' => $term->id()]]);
    $orgPage->setSyncing(TRUE);
    $orgPage->save();
    $entity = $this->createEntityForBundle($entityType, $bundle, $extraEntityFields);
    $user = $this->createUser(['bypass node access']);
    $user->addRole('administrator');
    $user->activate();
    $user->save();
    $this->drupalLogin($user);
    $this->drupalGet(sprintf('%s/%d/edit', $entityType, $entity->id()));
    // Open every <details> so the org/oog inputs are interactable.
    $this->getSession()->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );
    return [
      'orgPage' => $orgPage,
      'term' => $term,
      'entity' => $entity,
    ];
  }

  /**
   * Returns an entity whose edit form we can drive.
   *
   * Several node bundles (advisory, decision, person…) have mass_validation
   * hooks that derive field_organizations on presave from other required
   * fields, so a node freshly created without those fields renders an
   * edit form with no organizations widget at all. We prefer an existing
   * node of the bundle since its form is guaranteed to render. createNode
   * is only used as a fallback when the environment has no fixture, and
   * for the manual-OOG-term scenario where we need to seed values.
   */
  protected function createEntityForBundle(string $entityType, string $bundle, array $extra): EntityInterface {
    if ($entityType === 'node') {
      if (empty($extra)) {
        $existing = \Drupal::entityQuery('node')
          ->accessCheck(FALSE)
          ->condition('type', $bundle)
          ->range(0, 1)
          ->execute();
        if (!empty($existing)) {
          return \Drupal\node\Entity\Node::load((int) reset($existing));
        }
      }
      $node = $this->createNode([
        'type' => $bundle,
        'title' => 'OOG Augment ' . $bundle . ' ' . $this->randomMachineName(6),
        'status' => 1,
      ] + $extra);
      // The reconcile presave derives field_content_organization from the
      // entity's organizations on save, wiping any manually-seeded Permission
      // Groups a test stages here. Re-apply them with a syncing save (which the
      // presave skips) so the edit form starts in the intended fixture state.
      if (array_key_exists('field_content_organization', $extra)) {
        $node->set('field_content_organization', $extra['field_content_organization']);
        $node->setSyncing(TRUE);
        $node->save();
      }
      return $node;
    }
    $existing = \Drupal::entityQuery('media')
      ->accessCheck(FALSE)
      ->condition('bundle', $bundle)
      ->range(0, 1)
      ->execute();
    if (empty($existing)) {
      $this->markTestSkipped(sprintf('No existing media:%s to edit in this environment.', $bundle));
    }
    return Media::load((int) reset($existing));
  }

  /**
   * Picks a published org_page that has a mapped user_organization term.
   *
   * The dataProvider-based tests can fake any mapping via createNode +
   * createTerm; the typing test cannot, because Drupal's view-based
   * autocomplete only returns indexed/published nodes. We reuse an
   * already-mapped pair so the suggestion is present in the index.
   *
   * @return array{0:\Drupal\node\NodeInterface,1:\Drupal\taxonomy\TermInterface}
   *   Tuple of the org_page node and the mapped user_organization term.
   */
  protected function pickPublishedOrgPageWithMappedTerm(): array {
    $termIds = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', 'user_organization')
      ->exists('field_state_organization')
      ->range(0, 50)
      ->execute();
    foreach ($termIds as $tid) {
      $term = \Drupal\taxonomy\Entity\Term::load((int) $tid);
      $orgPageId = (int) $term->get('field_state_organization')->target_id;
      $orgPage = \Drupal\node\Entity\Node::load($orgPageId);
      if (!$orgPage || !$orgPage->isPublished()) {
        continue;
      }
      // The widget reads the org_page's own Permission Groups. Seed this term
      // as the direct source if the org_page has none yet, then return the
      // term that actually sits on the field so the assertion matches the
      // lookup result.
      if ($orgPage->get('field_content_organization')->isEmpty()) {
        $orgPage->set('field_content_organization', [['target_id' => $term->id()]]);
        $orgPage->setSyncing(TRUE);
        $orgPage->save();
      }
      $curated = $orgPage->get('field_content_organization')->referencedEntities();
      return [$orgPage, reset($curated)];
    }
    $this->markTestSkipped('No published org_page with a mapped user_organization term available.');
  }

  /**
   * Provides every entity bundle that needs the OOG augmentation feature.
   *
   * All 28 node bundles carrying both field_organizations and
   * field_content_organization, plus media.document.
   */
  public static function entityProvider(): array {
    $nodeBundles = [
      'action',
      'advisory',
      'alert',
      'binder',
      'campaign_landing',
      'contact_information',
      'curated_list',
      'decision',
      'decision_tree',
      'decision_tree_branch',
      'decision_tree_conclusion',
      'event',
      'external_data_resource',
      'fee',
      'form_page',
      'glossary',
      'guide_page',
      'how_to_page',
      'info_details',
      'location',
      'location_details',
      'news',
      'org_page',
      'person',
      'regulation',
      'rules',
      'service_page',
      'topic_page',
    ];
    $cases = [];
    foreach ($nodeBundles as $bundle) {
      $cases['node:' . $bundle] = ['node', $bundle];
    }
    $cases['media:document'] = ['media', 'document'];
    return $cases;
  }

}
