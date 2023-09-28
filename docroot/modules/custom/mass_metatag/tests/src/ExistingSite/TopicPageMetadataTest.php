<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Topic Page metadata tests.
 */
class TopicPageMetadataTest extends MetadataTestCase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);

    $node = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Test Topic Page',
      'field_topic_lede' => 'Test Lede',
      'field_state_organization_tax' => [$org_term],
      'field_topic_content_cards' => [
        Paragraph::create([
          'type' => 'content_card_group',
          'field_content_card_link_cards' => [
            'uri' => 'http://test.card',
            'title' => 'Test Card',
          ],
        ]),
      ],
      'field_topic_ref_related_topics' => [
        $this->createNode([
          'type' => 'topic_page',
          'title' => 'Test Topic',
        ]),
      ],
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    return array_merge(parent::getExpectedMetatags($entity), [
      'og:description' => 'Test Lede',
      'twitter:card' => 'summary_large_image',
      'twitter:description' => 'Test Lede',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $bare = \Drupal::service('file_url_generator')->generateAbsoluteString('public://test.jpg');
    return array_merge(parent::getExpectedMetadata($entity), [
      $url . '#topic_page' => [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        '@id' => $url . '#topic_page',
        'name' => $entity->label(),
        'description' => 'Test Lede',
        'relatedLink' => [
          $entity->field_topic_ref_related_topics->entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
        ],
        'mainEntity' => [],
      ],
    ]);
  }

}
