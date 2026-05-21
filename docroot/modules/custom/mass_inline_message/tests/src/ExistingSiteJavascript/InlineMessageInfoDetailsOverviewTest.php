<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests saving Message box markup on info_details Overview field.
 */
class InlineMessageInfoDetailsOverviewTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests overview field with CKEditor-exported div wrapper saves without validation errors.
   */
  public function testOverviewFieldSavesMessageBoxWithBody(): void {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    $overview = '<p>Overview intro.</p>'
      . '<mass-inline-message data-title="Payment options" data-type="info">'
      . '<div><p>Visit <a href="https://www.mass.gov/pay">mass.gov/pay</a>.</p></div>'
      . '</mass-inline-message>';

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Overview message box save ' . $this->randomMachineName(8),
      'field_info_detail_overview' => [
        'value' => $overview,
        'format' => 'basic_html',
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $session = $this->getSession();
    $session->wait(15000, "document.querySelector('[name=\"field_info_detail_overview[0][value]\"][data-ckeditor5-id]') !== null");

    $page = $session->getPage();
    $this->assertStringNotContainsString(
      'Message box text may only use these HTML tags',
      $page->getText(),
      'Overview field should not show message box tag validation errors on edit form load.'
    );

    $page->pressButton('Save');
    $session->wait(10000, "document.body.classList.contains('path-node') && !document.querySelector('.messages--error')");

    $this->assertStringNotContainsString(
      'Message box text may only use these HTML tags',
      $page->getText(),
      'Saving info_details with message box body should not fail tag validation.'
    );

    $node = $this->container->get('entity_type.manager')->getStorage('node')->load($node->id());
    $stored = $node->get('field_info_detail_overview')->value;
    $this->assertStringContainsString('Payment options', $stored);
    $this->assertStringContainsString('mass.gov/pay', $stored);
    $this->assertStringContainsString('<mass-inline-message', $stored);
  }

  /**
   * Tests saved overview markup loads into CKEditor without upcast errors.
   */
  public function testOverviewFieldLoadsSavedMessageBoxInEditor(): void {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    $overview = '<p>Overview intro.</p>'
      . '<mass-inline-message data-title="Payment options" data-type="info">'
      . '<div><p>Visit <a href="https://www.mass.gov/pay">mass.gov/pay</a>.</p></div>'
      . '</mass-inline-message>';

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Overview message box load ' . $this->randomMachineName(8),
      'field_info_detail_overview' => [
        'value' => $overview,
        'format' => 'basic_html',
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $session = $this->getSession();
    $session->wait(15000, "document.querySelector('[name=\"field_info_detail_overview[0][value]\"][data-ckeditor5-id]') !== null");
    $session->wait(15000, "(function(){
      return document.querySelector('.ck-content .ma__inline-message') !== null;
    })()");
    $session->wait(10000, "(function(){
      var textarea = document.querySelector('[name=\"field_info_detail_overview[0][value]\"][data-ckeditor5-id]');
      if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
        return false;
      }
      var data = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      return data.indexOf('data-title=\"Payment options\"') !== -1;
    })()");

    $editor_data = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('[name=\"field_info_detail_overview[0][value]\"][data-ckeditor5-id]');
        return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      })();"
    );

    $this->assertStringContainsString('data-title="Payment options"', (string) $editor_data);
    $this->assertStringContainsString('mass.gov/pay', (string) $editor_data);
    $this->assertStringContainsString('<mass-inline-message', (string) $editor_data);

    $has_mayflower_preview = $session->evaluateScript(
      "document.querySelector('.ck-content .ma__inline-message') !== null"
    );
    $this->assertTrue((bool) $has_mayflower_preview, 'Overview CKEditor should show Mayflower inline-message preview.');
  }

}
