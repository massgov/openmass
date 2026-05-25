<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies Message box works in Layout Paragraphs Rich text on service pages.
 *
 * Covers nested LP modal dialog open, Ajax save (no full-page redirect), and
 * stored markup in the Rich text CKEditor instance.
 */
class InlineMessageLayoutParagraphsTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Clicks the LPB Save button in the active dialog via JavaScript.
   */
  private function clickLayoutParagraphDialogSave(): void {
    $this->getSession()->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();"
    );
  }

  /**
   * Clicks the Message box configuration dialog Save button.
   */
  private function clickMessageBoxDialogSave(): void {
    $this->getSession()->executeScript(
      "(function(){
        var buttons = document.querySelectorAll('.ui-dialog-buttonpane .form-actions .js-form-submit, .ui-dialog-buttonpane .form-actions input[type=\"submit\"]');
        for (var i = 0; i < buttons.length; i++) {
          var label = (buttons[i].value || buttons[i].textContent || '').trim().toLowerCase();
          if (label === 'save') {
            buttons[i].click();
            return;
          }
        }
        var fallback = document.querySelector('#mass-inline-message-dialog-form input[type=\"submit\"]');
        if (fallback) { fallback.click(); }
      })();"
    );
  }

  /**
   * Opens Content tab and adds Service Section + Rich text LP components.
   */
  private function openServiceRichTextEditorInLayoutParagraph(): void {
    $page = $this->getSession()->getPage();
    $session = $this->getSession();

    $session->executeScript(
      "document.querySelector('a[href^=\"#edit-group-content\"]').scrollIntoView({block: 'center'});"
    );
    $page->find('css', '.horizontal-tab-button a[href="#edit-group-content"]')->click();

    $session->wait(1500);
    $addSection = $page->find(
      'css',
      '[data-drupal-selector="edit-field-service-sections-layout-paragraphs-builder"] a.lpb-btn.use-ajax.center.js-lpb-ui[href*="/choose-component"]'
    );
    $this->assertNotNull($addSection);
    $addSection->click();

    $session->wait(5000, "document.querySelector('.ui-dialog.lpb-dialog') !== null");
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .ui-dialog-content a.use-ajax[href*=\"/insert/service_section\"]');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();"
    );

    $session->wait(
      8000,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.indexOf('Create new Service Section') !== -1"
    );
    $this->clickLayoutParagraphDialogSave();
    $session->wait(8000, "document.querySelector('.ui-dialog.lpb-dialog') === null");

    $session->wait(
      8000,
      "document.querySelector('.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax[href*=\"choose-component?parent_uuid\"]') !== null"
    );
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax[href*=\"choose-component?parent_uuid\"]');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();"
    );

    $session->wait(3000, "document.querySelector('.ui-dialog .lpb-component-list__item.type-service_rich_text a.use-ajax') !== null");
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .lpb-component-list__item.type-service_rich_text a.use-ajax');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();"
    );

    $session->wait(
      10000,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.toLowerCase().indexOf('rich text') !== -1"
    );

    $session->wait(
      15000,
      "document.querySelector('.ui-dialog .ck-editor [contenteditable=true]') !== null"
    );
  }

  /**
   * Clicks the Message box toolbar button in the LP Rich text CKEditor.
   */
  private function clickMessageBoxToolbarButton(): void {
    $this->getSession()->executeScript(
      "(function(){
        var textarea = document.querySelector('.ui-dialog textarea[data-ckeditor5-id]');
        if (!textarea) { return; }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        if (!editor) { return; }
        editor.model.change(function(writer) {
          var root = editor.model.document.getRoot();
          writer.setSelection(writer.createPositionAt(root, 'end'));
        });
        editor.editing.view.focus();
        var button = editor.ui.componentFactory.create('messageBox');
        if (button) {
          button.fire('execute');
        }
      })();"
    );
  }

  /**
   * Returns CKEditor data from the Rich text field inside the LP modal.
   */
  private function getRichTextEditorData(): string {
    return (string) $this->getSession()->evaluateScript(
      "(function(){
        var textarea = document.querySelector('.ui-dialog textarea[data-ckeditor5-id]');
        if (!textarea) { return ''; }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        return editor ? editor.getData() : '';
      })();"
    );
  }

  /**
   * Tests Message box dialog opens from Rich text inside a Service Section LP modal.
   */
  public function testMessageBoxDialogOpensInLayoutParagraphRichText(): void {
    $user = $this->createUser();
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();

    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP dialog test ' . $this->randomMachineName(8),
      'uid' => $user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($user);
    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();
    $session = $this->getSession();

    $this->clickMessageBoxToolbarButton();

    $session->wait(
      20000,
      "(function(){
        var dialogs = document.querySelectorAll('.ui-dialog');
        for (var i = 0; i < dialogs.length; i++) {
          if (dialogs[i].querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')) {
            return true;
          }
        }
        return false;
      })()"
    );

    $title_field = $session->getPage()->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-title]"]')
      ?: $session->getPage()->find('css', '.ui-dialog input[name="attributes[data-title]"]');
    $this->assertNotNull($title_field, 'Message title field should appear in nested Message box dialog.');

    // Message box dialog should stack above the Rich text LP modal (two dialogs).
    $dialog_count = (int) $session->evaluateScript(
      "document.querySelectorAll('.ui-dialog').length"
    );
    $this->assertGreaterThanOrEqual(2, $dialog_count, 'Message box dialog should open on top of the Layout Paragraphs modal.');
  }

  /**
   * Tests inserting a Message box via dialog save stays in the LP Ajax flow.
   */
  public function testMessageBoxInsertAndSaveInLayoutParagraphRichText(): void {
    $user = $this->createUser();
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();

    $title = 'LP section alert ' . $this->randomMachineName(6);
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP insert test ' . $this->randomMachineName(8),
      'uid' => $user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($user);
    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();
    $session = $this->getSession();
    $page = $session->getPage();

    $this->clickMessageBoxToolbarButton();

    $session->wait(
      20000,
      "document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]') !== null"
    );

    $page->fillField('attributes[data-title]', $title);
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio);
    $warning_radio->click();

    $this->clickMessageBoxDialogSave();

    $session->wait(
      20000,
      "(function(){
        if (window.location.href.indexOf('/mass-inline-message/dialog/') !== -1) {
          return false;
        }
        return document.querySelector('#mass-inline-message-dialog-form') === null;
      })()"
    );

    $current_url = $session->getCurrentUrl();
    $this->assertStringNotContainsString('/mass-inline-message/dialog/', $current_url);
    $this->assertStringContainsString('/edit', $current_url, 'Save should keep the user on the node edit form (alias or /node path).');

    // Rich text LP modal should still be open after Message box save.
    $session->wait(5000, "document.querySelector('.ui-dialog .ck-editor') !== null");
    $rich_text_title = $session->evaluateScript(
      "document.querySelector('.ui-dialog .ui-dialog-title') ? document.querySelector('.ui-dialog .ui-dialog-title').textContent : ''"
    );
    $this->assertStringContainsStringIgnoringCase('rich text', (string) $rich_text_title);

    $editor_data = $this->getRichTextEditorData();
    $this->assertStringContainsString('data-title="' . $title . '"', $editor_data);
    $this->assertStringContainsString('data-type="warning"', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);
  }

}
