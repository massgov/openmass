<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Binder metadata tests.
 */
class BinderMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $binder_type = $this->createTerm(Vocabulary::load('binder_type'), [
      'name' => 'TestType',
    ]);
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
      'moderation_state' => 'published',
    ]);
    $node = $this->createNode([
      'type' => 'binder',
      'title' => 'Test Binder',
      'field_binder_ref_organization' => $org_node,
      'field_binder_short_desc' => 'Test Short Desc',
      'field_binder_last_updated' => '2012-12-31',
      'field_binder_binder_type' => [$binder_type],
      'field_state_organization_tax' => [$org_term],
      'field_contact' => $this->createContact(),
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'description' => 'Test Short Desc',
      'og:description' => 'Test Short Desc',
      'twitter:description' => 'Test Short Desc',
      'mg_date' => '20121231',
      'mg_organization' => 'testorgpage',
      // @todo This doesn't seem like the correct value.
      'mg_contact_details' => 'Test Contact - admin',
      'twitter:card' => 'summary_large_image',
    ]);
  }

}
