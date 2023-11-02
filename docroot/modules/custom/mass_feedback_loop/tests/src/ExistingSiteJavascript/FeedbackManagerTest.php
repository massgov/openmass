<?php

namespace Drupal\Tests\mass_feedback_loop\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Exception;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests "Feedback Manager" page at admin/ma-dash/feedback.
 */
class FeedbackMgrTest extends ExistingSiteSelenium2DriverTestBase {

  use LoginTrait;

  /**
   * The element for the entire document.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\Tests\DocumentElement */
    $this->page = $this->getSession()->getPage();

    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // Visiting the view.
    $this->drupalGet('admin/ma-dash/feedback');
    $this->view = $this->page->find('css', '#mass-feedback-loop-author-interface-form');
  }

}
