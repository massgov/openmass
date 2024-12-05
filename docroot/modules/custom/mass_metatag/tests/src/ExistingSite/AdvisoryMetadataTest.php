<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Advisory metadata tests.
 */
class AdvisoryMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $advisory_type = $this->createTerm(Vocabulary::load('advisory_type'), [
      'name' => 'TestType',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $org_node2 = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page Two',
    ]);
    $issuer_paragraph = Paragraph::create([
      'type' => 'issuer',
      'field_issuer_issuers' => $org_node,
    ]);
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $node = $this->createNode([
      'type' => 'advisory',
      'title' => 'Test Advisory',
      'field_date_published' => '2012-12-31',
      'field_advisory_overview' => 'TestOverview',
      'field_advisory_type_tax' => [$advisory_type],
      'field_state_organization_tax' => [$org_term],
      'field_advisory_issuer' => [$issuer_paragraph],
      'field_advisory_section' => Paragraph::create([
        'type' => 'advisory_section',
        'field_advisory_section_title' => 'Section Title',
        'field_advisory_section_body' => 'Section Body',
      ]),
      'field_organizations' => [$org_node, $org_node2],
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
      'category' => 'laws-regulations',
      'og:description' => "TestOverview",
      'twitter:description' => 'TestOverview',
      'mg_date' => '20121231',
      'mg_organization' => 'testorgpage,testorgpagetwo',
      'mg_type' => 'testtype',
      'twitter:card' => 'summary_large_image',
    ]);
  }

}
