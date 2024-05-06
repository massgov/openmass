<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Advisory metadata tests.
 */
class RulesMetadataTest extends MetadataTestCase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $rule_type = $this->createTerm(Vocabulary::load('rules_of_court_type'), [
      'name' => 'TestType',
    ]);
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $node = $this->createNode([
      'type' => 'rules',
      'title' => 'Test Rules',
      'field_state_organization_tax' => [$org_term],
      'field_date_published' => '2012-12-31',
      'field_rules_overview' => 'TestOverview',
      'field_rules_type' => $rule_type,
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'description' => 'TestOverview',
      'twitter:description' => 'TestOverview',
      'og:description' => "TestOverview",
      'mg_date' => '20121231',
      'mg_type' => 'testtype',
      'mg_organization' => 'testorgpage',
      'twitter:card' => 'summary_large_image',
    ]);
  }

}
