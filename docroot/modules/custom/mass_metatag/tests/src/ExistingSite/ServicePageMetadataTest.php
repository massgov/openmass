<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Service Page metadata tests.
 */
class ServicePageMetadataTest extends MetadataTestCase {

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
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $image->save();
    $this->markEntityForCleanup($image);
    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page',
      'field_state_organization_tax' => [$org_term],
      'field_service_lede' => 'Test Lede',
      'field_service_bg_wide' => $image,
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    $style = ImageStyle::load('social_media');
    $file_uri = $style->buildUrl($entity->field_service_bg_wide->entity->getFileUri());
    return array_merge(parent::getExpectedMetatags($entity), [
      'description' => 'Test Lede',
      'og:image' => $file_uri,
      'twitter:card' => 'summary_large_image',
      'twitter:image' => $file_uri,
      'mg_organization' => 'testorgpage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $uri = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    return array_merge(parent::getExpectedMetadata($entity), [
      $uri . '#services' => [
        '@context' => 'https://schema.org',
        '@type' => 'GovernmentService',
        '@id' => $uri . '#services',
        'name' => 'Test Service Page',
        'description' => 'Test Lede',
        'potentialAction' => [],
        'areaServed' => [
          '@type' => 'AdministrativeArea',
          'name' => 'Massachusetts',
        ],
      ],
    ]);
  }

}
