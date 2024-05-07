<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * News Metadata tests.
 */
class NewsMetadataTest extends MetadataTestCase {

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
    $signees_paragraph = Paragraph::create([
      'type' => 'state_organization',
      'field_state_org_ref_org' => $org_node,
    ]);
    return $this->createNode([
      'type' => 'news',
      'title' => 'Test Content',
      'field_news_type' => 'press_release',
      'field_date_published' => '2012-12-31',
      'field_state_organization_tax' => $org_term,
      'field_news_body' => 'TestDescription',
      'field_news_lede' => 'TestDescription',
      'field_news_signees' => $signees_paragraph,
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'category' => 'news',
      'description' => 'TestDescription',
      'og:type' => 'article',
      'mg_date' => '20121231',
      'mg_type' => 'press-release',
      'mg_organization' => 'testorgpage',
      'twitter:card' => 'summary_large_image',
    ]);
  }

}
