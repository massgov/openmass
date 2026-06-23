<?php

namespace Drupal\Tests\mass_map\ExistingSite;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\mass_metatag\Traits\TestContentTrait;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies contact address geocoding runs in CI without OpenCage usage.
 */
class GeocodingRandomProviderTest extends MassExistingSiteBase {

  use TestContentTrait;

  /**
   * Ensures geocoding populates contact address geofield.
   */
  public function testContactAddressGeocodesWithoutExternalProvider(): void {
    $providers = \Drupal::config('field.field.paragraph.address.field_geofield')
      ->get('third_party_settings.geocoder_field.providers') ?? [];

    // Guard against accidental OpenCage quota usage if someone runs this test
    // in an environment that doesn't load the dev/CI override.
    if (in_array('opencage', $providers, TRUE)) {
      $this->markTestSkipped('Geocoder providers include opencage; refusing to run external geocoding in this environment.');
    }

    $this->assertContains('random', $providers, 'Expected random provider for geocoding in tests.');

    $contact = $this->createContact([
      'title' => 'Geocoding test contact',
      'field_display_title' => 'Geocoding test contact',
      'field_ref_address' => [
        \Drupal\paragraphs\Entity\Paragraph::create([
          'type' => 'address',
          'field_label' => 'Address 1',
          'field_address_address' => [
            'address_line1' => '21-27 Batchelder Street',
            'locality' => 'Roxbury',
            'administrative_area' => 'MA',
            'postal_code' => '02119',
            'country_code' => 'US',
          ],
        ]),
      ],
    ]);

    /** @var \Drupal\paragraphs\ParagraphInterface|null $address */
    $address = $contact->get('field_ref_address')->entity;
    $this->assertInstanceOf(ParagraphInterface::class, $address);

    // Geocoder runs on entity presave. Re-load the paragraph to ensure we see
    // persisted geofield values.
    $address = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->load($address->id());

    $this->assertNotNull($address, 'Expected address paragraph to be saved.');
    $this->assertFalse($address->get('field_geofield')->isEmpty(), 'Expected geofield to be populated by geocoding.');

    $item = $address->get('field_geofield')->first();
    $this->assertNotNull($item->lat, 'Expected geofield latitude to be set.');
    $this->assertNotNull($item->lon, 'Expected geofield longitude to be set.');

    // If geocoding fails on save, mass.gov surfaces a warning on node view.
    $this->drupalGet($contact->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Unable to geocode');
  }

}
