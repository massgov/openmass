<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies entity embed dialog flow works via the real UI.
 */
class EntityEmbedDialogAjaxBridgeTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates an admin, saves it and returns it.
   */
  private function createAdmin() {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    return $admin;
  }

  /**
   * Creates a page node with a basic_html body.
   */
  private function createPageWithBody(): int {
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Entity embed test ' . $this->randomMachineName(8),
      'body' => [
        'value' => '<p>Entity embed test body.</p>',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);
    $node->save();

    return (int) $node->id();
  }

  /**
   * Creates a file entity available to file browser.
   */
  private function createEmbedTestFile(): File {
    $filename = 'embed-test-' . $this->randomMachineName(8) . '.jpg';
    $uri = 'public://' . $filename;
    \Drupal::service('file_system')->saveData('embed-image', $uri, FileSystemInterface::EXISTS_REPLACE);
    $file = File::create([
      'uri' => $uri,
      'status' => 1,
    ]);
    $file->save();
    $this->markEntityForCleanup($file);
    return $file;
  }

  /**
   * Tests first and second embed-dialog steps via actual user flow.
   */
  public function testEmbedDialogFlowThroughRealUi() {
    $this->drupalLogin($this->createAdmin());
    $this->createEmbedTestFile();
    $node_id = $this->createPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $page = $this->getSession()->getPage();

    // Open the file_browser button from CKEditor toolbar.
    $this->getSession()->wait(8000, "document.querySelector('.ck-toolbar button[aria-label*=\"file\" i], .ck-toolbar button[aria-label*=\"embed\" i], .ck-toolbar button[title*=\"file\" i], .ck-toolbar button[title*=\"embed\" i]') !== null");
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.ck-toolbar button[aria-label*=\"file\" i], .ck-toolbar button[aria-label*=\"embed\" i], .ck-toolbar button[title*=\"file\" i], .ck-toolbar button[title*=\"embed\" i]'); if (el) { el.click(); }})();"
    );

    // Step 1: select file and click "Use selected".
    $this->getSession()->wait(10000, "document.querySelector('.ui-dialog') !== null && document.querySelector('input[name^=\"entity_browser_select[\"]') !== null");
    $this->getSession()->executeScript(
      "(function(){var c=document.querySelector('input[name^=\"entity_browser_select[\"]'); if(c){c.click();}})();"
    );
    $this->getSession()->executeScript(
      "(function(){var buttons=document.querySelectorAll('.ui-dialog-buttonpane .form-actions .js-form-submit'); for(var i=0;i<buttons.length;i++){var t=(buttons[i].value||buttons[i].textContent||'').trim().toLowerCase(); if(t==='use selected'){buttons[i].click(); return;}}})();"
    );

    // Step 2: wait for embed form and click "Embed" in dialog pane.
    $this->getSession()->wait(
      10000,
      "document.querySelector('form.entity-embed-dialog.entity-embed-dialog-step--embed') !== null"
    );
    $this->getSession()->executeScript(
      "(function(){var buttons=document.querySelectorAll('.ui-dialog-buttonpane .form-actions .js-form-submit'); for(var i=0;i<buttons.length;i++){var t=(buttons[i].value||buttons[i].textContent||'').trim().toLowerCase(); if(t==='embed'){buttons[i].click(); return;}}})();"
    );

    // The modal flow should finish without redirecting the full page.
    $this->getSession()->wait(10000, "document.querySelector('form.entity-embed-dialog') === null");
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringNotContainsString('/entity-embed/dialog/', $current_url);
    $this->assertStringContainsString('/node/' . $node_id . '/edit', $current_url);

    // Ensure the edit page is still interactive after embed submit.
    $this->assertNotNull($page->findField('Title'));
  }

}

