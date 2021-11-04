<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

/**
 * Tests Contact Information creation.
 */
class CreateContactInformationTest extends CreateContentTypeTestBase {

  /**
   * {@inheritdoc}
   */
  protected function info():array {
    return [
      'machineName' => 'contact_information',
      'label' => 'Contact Information',
      'title' => $this->random->word(15),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function customChecks() {
    $this->mainContains('Online');
    $this->mainContains('Phone');
    $this->mainContains('more contact info');
    $this->click('main .js-accordion-link');
    $this->mainContains('Learn more about this organization');
  }

  /**
   * {@inheritdoc}
   */
  protected function fillFields() {
    $this->fillField('Display Title', 'Display title_', TRUE, TRUE);
    $this->fillField('Description', 'Description_', TRUE, FALSE);
    $this->fillField('Organization(s)', 'Economic Development Planning Council (466321) - Organization', FALSE, FALSE);

    // Add link.
    $this->pressButton('Add Link', 1000);
    $this->fillField('URL', 'https://google.com', FALSE, FALSE);
    $this->fillField('Link text', 'Link_', TRUE, TRUE);

    // Add email.
    $this->pressButton('Add Email', 1000);
    $this->fillField('Label', 'Personal Email_', TRUE, TRUE);
    $this->fillField('Email', 'personal@email.com', FALSE, TRUE);

    // Add phone number.
    $this->pressButton('Add Phone Number', 1000);
    $this->fillField('Number', '099-999-999-9999', FALSE, TRUE);

    // Filling nested fields.
    $this->findFieldNested(['More info link', 'URL'])->setValue('https://google.com');
    $this->findFieldNested(['More info link', 'Link text'])->setValue('Some Link');

  }

}
