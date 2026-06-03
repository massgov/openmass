<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\Tests\mass_inline_message\Traits\InlineMessageMarkupTestTrait;

/**
 * Tests saving Message box markup on info_details Overview field.
 */
class InlineMessageInfoDetailsOverviewTest extends MassInlineMessageJavascriptTestBase {

  use InlineMessageMarkupTestTrait;

  /**
   * Overview field CKEditor textarea selector.
   */
  private const OVERVIEW_EDITOR_SELECTOR = '[name="field_info_detail_overview[0][value]"][data-ckeditor5-id]';

  /**
   * Tests overview field with CKEditor-exported div wrapper saves without validation errors.
   */
  public function testOverviewFieldSavesMessageBoxWithBody(): void {
    $this->drupalLogin($this->createAdministrator());

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Overview message box save ' . $this->randomMachineName(8),
      'field_info_detail_overview' => [
        'value' => self::OVERVIEW_WITH_MESSAGE_BOX,
        'format' => 'basic_html',
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $session = $this->inlineMessageSession();
    $session->wait(15000, 'document.querySelector(' . json_encode(self::OVERVIEW_EDITOR_SELECTOR) . ') !== null');

    $page = $session->getPage();
    $page->pressButton('Save');
    $session->wait(10000, "document.body.classList.contains('path-node') && !document.querySelector('.messages--error')");

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
    $this->drupalLogin($this->createAdministrator());

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Overview message box load ' . $this->randomMachineName(8),
      'field_info_detail_overview' => [
        'value' => self::OVERVIEW_WITH_MESSAGE_BOX,
        'format' => 'basic_html',
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $session = $this->inlineMessageSession();
    $session->wait(15000, 'document.querySelector(' . json_encode(self::OVERVIEW_EDITOR_SELECTOR) . ') !== null');
    $session->wait(15000, "(function(){
      return document.querySelector('.ck-content .ma__inline-message') !== null;
    })()");
    $session->wait(10000, "(function(){
      var textarea = document.querySelector(" . json_encode(self::OVERVIEW_EDITOR_SELECTOR) . ");
      if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
        return false;
      }
      var data = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      return data.indexOf('data-title=\"Payment options\"') !== -1;
    })()");

    $editor_data = $this->getCKEditorData(self::OVERVIEW_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Payment options"', $editor_data);
    $this->assertStringContainsString('mass.gov/pay', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);

    $has_mayflower_preview = $session->evaluateScript(
      "document.querySelector('.ck-content .ma__inline-message') !== null",
    );
    $this->assertTrue((bool) $has_mayflower_preview, 'Overview CKEditor should show Mayflower inline-message preview.');
  }

}
