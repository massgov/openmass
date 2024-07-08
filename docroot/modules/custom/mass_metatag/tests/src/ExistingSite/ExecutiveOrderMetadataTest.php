<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;

/**
 * Executive Order metadata tests.
 */
class ExecutiveOrderMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $issuer_paragraph = Paragraph::create([
      'type' => 'issuer',
      'field_issuer_issuers' => $org_node,
    ]);
    $node = $this->createNode([
      'type' => 'executive_order',
      'body' => $this->createTextField('Test Body'),
      'field_executive_order_mass_regis' => '123',
      'field_executive_title' => 'Example Executive Order',
      'field_date_published' => '2012-12-31',
      'field_executive_order_overview' => $this->createTextField('Test Overview'),
      'field_state_organization_tax' => $org_term,
      'field_executive_order_issuer' => $issuer_paragraph,
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'category' => 'laws-regulations',
      'og:type' => 'article',
      'og:description' => 'Test Overview',
      'twitter:description' => "Test Overview",
      'mg_type' => 'executive-order',
      'mg_date' => '20121231',
      'mg_organization' => 'testorgpage',
      'twitter:card' => 'summary_large_image',
    ]);
  }

}
