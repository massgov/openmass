<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Org Page metadata tests.
 */
class OrgPageMetadataTest extends MetadataTestCase {

  use TestContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTerm',
    ]);

    $location = $this->createNode([
      'type' => 'location',
      'title' => 'Test Location',
      'moderation_state' => 'published',
    ]);
    $org_locations = Paragraph::create([
      'type' => 'org_locations',
      'field_org_ref_locations' => [$location],
    ]);
    $org_locations->save();
    $location_org_section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [$org_locations],
    ]);
    $location_org_section->save();
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $image->save();
    $this->markEntityForCleanup($image);
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org',
      'field_bg_wide' => $image,
      'field_sub_title' => 'Test Subtitle',
      'field_ref_contact_info_1' => [$this->createContact()],
      'field_organization_sections' => [$location_org_section],
      'field_sub_brand' => [$image],
      'field_state_organization_tax' => [$org_term],
      'moderation_state' => 'published',
    ]);
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedMetatags(ContentEntityInterface $entity) {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $style = ImageStyle::load('large');
    $large = $style->buildUrl('public://test.jpg');
    return array_merge(parent::getExpectedMetatags($entity), [
      'description' => 'Test Subtitle',
      'og:description' => "Test Subtitle",
      'twitter:card' => 'summary_large_image',
      'twitter:description' => 'Test Subtitle',
      'twitter:image' => $large,
      'mg_phone_number' => '123-456-7890',
      'mg_online_contact_url' => '[{"name":"foo@bar.com","url":"foo@bar.com"},{"name":"Link Contact Label","url":"http:\/\/contact.com"}]',
      'mg_location_listing_url' => '[{"name":"Test Org Locations","url":' . json_encode($url . '/locations') . '}]',
      'mg_contact_details' => 'Contact Caption',
      'mg_organization' => 'testorg',
    ]);
  }

}
