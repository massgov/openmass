<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Decision metadata tests.
 */
class DecisionMetadataTest extends MetadataTestCase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $decision_type = $this->createTerm(Vocabulary::load('decision_type'), [
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
    $file = File::create([
      'uri' => 'public://test.txt',
    ]);
    $download = Media::create([
      'bundle' => 'document',
      'field_upload_file' => $file,
    ]);
    $file->save();
    $download->save();
    $this->markEntityForCleanup($file);
    $this->markEntityForCleanup($download);
    $node = $this->createNode([
      'type' => 'decision',
      'title' => 'Test Decision',
      'field_date_published' => '2012-12-31',
      'field_decision_ref_type' => [$decision_type],
      'field_decision_overview' => [
        'value' => 'TestOverview',
        'format' => 'basic_html',
      ],
      'field_decision_ref_organization' => [$org_node],
      'field_state_organization_tax' => [$org_term],
      'field_decision_docket_number' => 'ABC123',
      'field_decision_download' => $download,
      'field_decision_participants' => Paragraph::create([
        'type' => 'decision_participants',
        'field_decision_participant_name' => 'John Smith',
        'field_ref_participant_type' => $this->createTerm(Vocabulary::load('decision_participant_type'), [
          'name' => 'Participant Type 1',
        ]),
      ]),
      'field_decision_sources' => [
        'uri' => 'http://decision.source',
        'title' => 'Decision Source',
      ],
      'field_decision_section' => Paragraph::create([
        'type' => 'decision_section',
        'field_decision_section_title' => 'Decision Section Title',
        'field_decision_section_body' => [
          'value' => 'Decision Section Body',
          'format' => 'basic_html',
        ],
      ]),
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
      'description' => 'TestOverview',
      'og:type' => 'article',
      'og:description' => 'TestOverview',
      'twitter:description' => 'TestOverview',
      'mg_date' => '20121231',
      'mg_type' => 'testtype',
      'mg_organization' => 'testorgpage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    return array_merge(parent::getExpectedMetadata($entity), [
      $url . '#decision' => [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        '@id' => $url . '#decision',
        'about' => 'TestOverview',
        'releasedEvent' => 'Mon, 12/31/2012 - 12:00',
        'significantLink' => [
          \Drupal::service('file_url_generator')->generateAbsoluteString('public://test.txt'),
        ],
        'sourceOrganization' => 'Test Org Page',
        'name' => 'Test Decision',
        'identifier' => 'ABC123',
        // @todo This seems broken.
        'reviewedBy' => [NULL],
        'mainContentOfPage' => [
          [
            '@type' => 'WebPageElement',
            // @todo This seems broken.
            'text' => NULL,
          ],
        ],
        'isBasedOn' => ['http://decision.source'],
      ],
    ]);
  }

}
