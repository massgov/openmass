<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use Drupal\Component\Utility\Random;
use Exception;
use weitzman\LoginTrait\LoginTrait;

/**
 * Test the creation of a content type.
 */
abstract class CreateContentTypeTestBase extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * To store field values to be checked when node is rendered.
   *
   * @var array
   */
  protected $fieldsData;

  /**
   * To generate random values.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * To store the machine name of the content type.
   *
   * @var string
   */
  protected $machineName = '';

  /**
   * To store the logged in administrator.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $admin = NULL;

  /**
   * To store the label of the content type.
   *
   * @var string
   */
  protected $label = '';

  /**
   * To store the title of the created node.
   *
   * @var string
   */
  protected $title = '';

  /**
   * To store the URL of the created node.
   *
   * @var string
   */
  private $nodeUrl = '';

  /**
   * Specific checks for a content type.
   */
  abstract protected function customChecks();

  /**
   * To fill fields specific to the content type.
   *
   * Fill all fields specific to a content type, except for the title.
   */
  abstract protected function fillFields();

  /**
   * Returns an array with the information for the properties of this instance.
   *
   * @code
   * return ['machineName' => '', 'label' => '', 'title' => ''];
   * @endcode
   */
  abstract protected function info(): array;

  /**
   * Sets required info in the instance properties.
   */
  private function setUpRequiredInfo() {
    $info = $this->info();
    $required_info = ['machineName', 'label', 'title'];

    foreach ($required_info as $key) {
      if (empty($info[$key])) {
        throw new Exception("The $key can't be empty. Check info() method.");
      }
      $this->{$key} = $info[$key];
    }
  }

  /**
   * Finds a nested field by label.
   *
   * Fields might be nested, and labels can be similar.
   * This function assumes your field is inside fieldsets,
   * nested, and the last field is the "real" field. Example:
   * @code
   * $this->findFieldNested(['More info link', 'URL']
   * @endcode
   */
  protected function findFieldNested(array $hierarchy) {
    $element = $this->getSession()->getPage();
    $final = \array_pop($hierarchy);
    foreach ($hierarchy as $item) {
      $element = $element->find('named', ['fieldset', $item]);
    }
    return $element->findField($final);
  }

  /**
   * Creates an admin account, logs the user, and store it.
   */
  private function setUpAdministrator() {
    // We need a logged in administrator.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);
    $this->admin = $admin;
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->random = new Random();
    $this->fieldsData = [];
    $this->setUpRequiredInfo();
    $this->setUpAdministrator();
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->admin->delete();
    parent::tearDown();
  }

  /**
   * Checks from the filled values if they are inside main.
   */
  private function fieldValueChecks() {
    $text = $this->getMainText();
    foreach ($this->fieldsData as $field => $data) {
      $this->assertStringContainsString($data, $text);
    }
  }

  /**
   * Tests the creation of a node.
   */
  public function testContentTypeCreation() {
    $this->createAndSaveAsUnpublished();
    $this->checkCreationMessage();
    $this->nodeUrl = $this->getUrl();
    $this->checkPrepublishedDraftState();
    $this->fieldValueChecks();
    $this->customChecks();
    $this->checkNodeCannotBeAccessedByAnonymous();
    $this->editAndSaveAsPublished();
    $this->checkNodeCanBeAccessedByAnonymous();
  }

  /**
   * Fills a field.
   *
   * Fills a field with a specific label with $value.
   * If $random is true, it appends a random word to $value.
   * If $check is TRUE, stores the value and check that value
   * is rendered inside the MAIN tag after the node is saved.
   *
   * @param mixed $locator
   *   The label of the field.
   * @param string $value
   *   The default value for the field to fill.
   * @param bool $random
   *   If a random string should be appended.
   * @param bool $check
   *   If this value is checked later when viewing the node.
   */
  protected function fillField($locator, $value = "", $random = FALSE, $check = FALSE) {
    $session = $this->getSession();
    $page = $session->getPage();
    $value = ($value ?: $this->random->word(20)) . ($random ? $this->random->word(20) : '');
    // Store value to check later.
    // @see fieldValueChecks()
    $check ? $this->fieldsData[$locator] = $value : NULL;
    $page->fillField($locator, $value);
  }

  /**
   * Presses a button (and waits if required).
   */
  protected function pressButton($button, int $wait = 0) {
    $this->getSession()->getPage()->findButton($button)->press();
    $wait ? $this->getSession()->wait($wait) : NULL;
  }

  /**
   * Gets the text from the whole page.
   */
  protected function getPageText() {
    return $this->getSession()->getPage()->getText();
  }

  /**
   * Gets the text from the main tag.
   */
  protected function getMainText() {
    return $this->getSession()->getPage()->find('css', 'main')->getText();
  }

  /**
   * Checks that the main contains specific text.
   */
  protected function mainContains($text) {
    $this->assertStringContainsString($text, $this->getMainText());
  }

  /**
   * Checks that the page does not contain specific text.
   */
  protected function pageContains($text) {
    $this->assertStringContainsString($text, $this->getPageText());
  }

  /**
   * Checks that the page does not contain specific text.
   */
  protected function pageNotContains($text) {
    $this->assertStringNotContainsString($text, $this->getPageText());
  }

  /**
   * Checks the node creation message.
   */
  private function checkCreationMessage() {
    $this->pageContains($this->label . ' ' . $this->title . ' has been created.');
  }

  /**
   * Checks if the node is in prepublished_draft state.
   */
  private function checkPrepublishedDraftState() {
    $this->pageContains('Current moderation state: prepublished_draft');
  }

  /**
   * Creates the node and saves it without publishing it.
   */
  private function createAndSaveAsUnpublished() {
    $this->drupalGet('/node/add/' . $this->machineName);

    // Fill title and other fields.
    $this->fillField('title[0][value]', $this->title, FALSE, TRUE);
    $this->fillFields();

    // Save as unpublished.
    $this->fillField('Save as', 'prepublished_draft');
    $this->click('#edit-submit');
  }

  /**
   * Checks nodeUrl CAN be accessed by anonymous users.
   */
  private function checkNodeCanBeAccessedByAnonymous() {
    // Check with anonymous user.
    $this->drupalLogout();
    $this->drupalGet($this->nodeUrl);
    $this->pageNotContains('This page is forbidden');
  }

  /**
   * Checks nodeUrl cannot be accessed by anonymous users.
   */
  private function checkNodeCannotBeAccessedByAnonymous() {
    $this->drupalLogout();
    $this->drupalGet($this->nodeUrl);
    $this->pageContains('This page is forbidden');
  }

  /**
   * Edits and saves the node as published.
   */
  private function editAndSaveAsPublished() {
    $this->getSession()->restart();
    $this->drupalLogin($this->admin);
    $this->drupalGet($this->nodeUrl);
    // Click edit.
    $this->click('.primary-tabs a[href^="/node/"][href$="/edit"]');
    // Save as published.
    $this->fillField('Change to', 'published');
    $this->click('#edit-submit');
  }

}
