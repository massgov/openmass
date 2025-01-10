<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Guide Page metadata tests.
 */
class GuidePageMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $image->save();
    $this->markEntityForCleanup($image);
    $related = $this->createNode([
      'type' => 'guide_page',
      'title' => 'Test Related',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $node = $this->createNode([
      'type' => 'guide_page',
      'title' => 'Test Guide',
      'field_guide_page_lede' => $this->createTextField('Test Lede'),
      'field_state_organization_tax' => $org_term,
      'field_guide_page_related_guides' => [$related],
      'field_guide_page_bg_wide' => $image,
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    $style = ImageStyle::load('action_banner_large');
    $file_uri = $style->buildUrl($entity->field_guide_page_bg_wide->entity->getFileUri());
    return array_merge(parent::getExpectedMetatags($entity), [
      'category' => 'services',
      // 'description' => 'Test Lede',  // @todo: Not showing up - not sure why.
      'robots' => 'index, follow',
       // 'og:description' => 'Test Lede', // @todo: also not showing up.
       // 'og:image' => '', // @todo: Also not showing up.
      'twitter:card' => 'summary_large_image',
      'twitter:description' => 'Test Lede',
      'twitter:image' => $file_uri,
      'mg_organization' => 'testorgpage',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetadata(ContentEntityInterface $entity) {
    $uri = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $related = $entity->field_guide_page_related_guides->first()->entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    return array_merge(parent::getExpectedMetadata($entity), [
      $uri . '#guide' => [
        '@id' => $uri . '#guide',
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $entity->label(),
        'relatedLink' => [$related],
        'description' => 'Test Lede',
      ],
    ]);
  }

}
