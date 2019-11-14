<?php

namespace Drupal\Tests\mass_metatag\Traits;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Extracted common content creation methods.
 *
 * This trait can be used in tests to help set up dummy content for complex
 * content types.  We assume we can rely on the default values that are entered
 * here in our tests. Unless otherwise noted, you may change things in these
 * methods if needed, but you may need to fix other tests that break as a
 * result.
 *
 * WARNING: Do not let this trait grow beyond 1 method per bundle.
 */
trait TestContentTrait {

  /**
   * Inherited method to create content.
   */
  abstract public function createNode(array $settings = []);

  /**
   * Return a text field input value when you don't care about format.
   *
   * This can be used for formatted text fields or plain text fields, and is
   * particularly useful when you don't know or care whether a field is
   * formatted or not.
   *
   * @param string $content
   *   The test string.
   *
   * @return array
   *   The array including a value and format key.
   */
  protected function createTextField(string $content) {
    return [
      'value' => $content,
      'format' => 'basic_html',
    ];
  }

  /**
   * Create a contact node.
   *
   * @param array $overrides
   *   Overrides of default values.
   *
   * @return \Drupal\node\NodeInterface
   *   The contact node, saved and ready for use.
   */
  protected function createContact(array $overrides = []) {
    return $this->createNode([
      'type' => 'contact_information',
      'field_display_title' => 'Test Contact',
      'title' => 'Test Contact - admin',
      'field_ref_links' => [
        Paragraph::create([
          'type' => 'online_email',
          'field_label' => 'Email Contact',
          'field_email' => 'foo@bar.com',
        ]),
        Paragraph::create([
          'type' => 'links',
          'field_label' => 'Link Contact',
          'field_link_single' => [
            'uri' => 'http://contact.com',
            'title' => 'Link Contact Label',
          ],
        ]),
      ],
      'field_ref_phone_number' => [
        Paragraph::create([
          'type' => 'phone_number',
          'field_caption' => 'Contact Caption',
          'field_phone' => '123-456-7890',
        ]),
      ],
      'field_ref_address' => [
        Paragraph::create([
          'type' => 'address',
          'field_label' => 'Address 1',
          'field_address_address' => [
            'address_line1' => '123 Test Way',
            'locality' => 'Boston',
            'administrative_area' => 'MA',
            'postal_code' => '12345',
            'country_code' => 'US',
          ],
        ]),
      ],
      'moderation_state' => 'published',
    ] + $overrides);
  }

}
