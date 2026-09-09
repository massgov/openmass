<?php

namespace Drupal\Tests\mass_views\ExistingSiteJavascript;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use MassGov\Dtt\MassExistingSiteSelenium2DriverTestBase;

/**
 * Tests "All Content" view requires input to show content to speed up login.
 */
class AllContentViewTest extends MassExistingSiteSelenium2DriverTestBase {

  private NodeElement $view;
  private DocumentElement $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();
    /** @var \Drupal\Tests\DocumentElement */
    $this->page = $this->getSession()->getPage();

    // An admin is needed.
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);
  }

  /**
   * Ensure view content has no results if the Apply button is not clicked.
   */
  public function testView() {
    $this->drupalGet('admin/content');
    $this->view = $this->page->find('css', '.view.view-content');
    $view_results_selector = '.view-content .views-view-table';
    $this->assertSession()->elementNotExists('css', $view_results_selector);
    $this->getCurrentPage()->pressButton('Apply');
    $this->assertSession()->elementExists('css', $view_results_selector);
  }

}
