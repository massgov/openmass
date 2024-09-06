<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests restrictions on Topic Pages.
 */
class TopicPageRestrictionTest extends MassExistingSiteBase {

  use LoginTrait;

  const RESTRICTED = TRUE;
  const UNRESTRICTED = FALSE;

  private $user;

  /**
   * Returns selectors of locked fields on topic pages.
   */
  private function lockedFields() {
    return [
      '#edit-title-0-value',
      '#edit-field-topic-lede-0-value',
      '#edit-field-topic-ref-related-topics-0-target-id',
    ];
  }

  /**
   * Returns selectors of unlocked fields on topic pages.
   */
  private function unlockedFields() {
    return [
      '#edit-field-organizations-0-target-id',
      '#edit-field-intended-audience-none',
      '#edit-field-reusable-label-0-target-id',
      '#edit-field-topic-content-cards-add-more-add-more-button-content-card-group',
    ];
  }

  /**
   * Provides data to test with testTopicPageEditForm.
   */
  public function provider() {

    $editor = [
      'role' => 'editor',
      'unlocked_fields' => $this->unlockedFields(),
      'locked_fields' => $this->lockedFields(),
      'available_states' => ['draft', 'needs_review', 'published'],
    ];

    $author = [
      'role' => 'author',
      'unlocked_fields' => $this->unlockedFields(),
      'locked_fields' => $this->lockedFields(),
      'available_states' => ['draft', 'needs_review'],
    ];

    $content_administrator = [
      'role' => 'content_team',
      // This role hasn't any locked fields.
      'unlocked_fields' => array_merge($this->unlockedFields(), $this->lockedFields()),
      'locked_fields' => [],
      'available_states' => [
        'draft',
        'needs_review',
        'published',
        'trash',
        'unpublished',
      ],
    ];

    return [
      [$editor],
      [$author],
      [$content_administrator],
    ];
  }

  /**
   * Creates a restricted/unrestricted topic page.
   */
  public function createTopicPage($restricted) {
    // Test a new Topic page can be saved.
    $newOrgNode = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
    ]);

    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);

    $this->markEntityForCleanup($image);

    $data = [
      'type' => 'topic_page',
      'title' => $this->randomMachineName(),
      'field_restrict_link_management' => $restricted,
      'field_organizations' => $newOrgNode->id(),
      'field_topic_lede' => $this->randomString(20),
      'field_topic_content_cards' => [
        Paragraph::create([
          'type' => 'content_card_group',
          'field_content_card_link_cards' => [
            'uri' => 'http://test.card',
            'title' => 'Test Card',
          ],
        ]),
      ],
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $topic_page = $this->createNode($data);
    $topic_page->save();
    return $topic_page;
  }

  /**
   * Creates a Topic Page with and without a feedback form component.
   */
  private function createTopicPageShowHideFeedbackForm(bool $feedback_form_hidden) {
    // Test a new Topic page can be saved.
    $newOrgNode = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
    ]);

    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);

    $this->markEntityForCleanup($image);

    $data = [
      'type' => 'topic_page',
      'title' => $this->randomMachineName(),
      'field_hide_feedback_component' => $feedback_form_hidden,
      'field_organizations' => $newOrgNode->id(),
      'field_topic_lede' => $this->randomString(20),
      'field_topic_content_cards' => [
        Paragraph::create([
          'type' => 'content_card_group',
          'field_content_card_link_cards' => [
            'uri' => 'http://test.card',
            'title' => 'Test Card',
          ],
        ]),
      ],
      'status' => 1,
      'moderation_state' => 'published',
    ];

    $topic_page = $this->createNode($data);
    $topic_page->save();
    return $topic_page;
  }

  /**
   * Checks topic page edit form locked and unlocked fields.
   */
  public function checkTopicPageEditForm($locked, $unlocked) {
    // Checks locked fields.
    foreach ($locked as $selector) {
      $elem = $this->getCurrentPage()->find('css', $selector);
      $error_msg = "$selector is not disabled.";
      $this->assertTrue($elem->hasAttribute('disabled'), $error_msg);
    }
    // Checks locked fields.
    foreach ($unlocked as $selector) {
      $elem = $this->getCurrentPage()->find('css', $selector);
      $error_msg = "$selector is not enabled.";
      $this->assertTrue(!$elem->hasAttribute('disabled'), $error_msg);
    }
  }

  /**
   * Checks moderation state options.
   */
  private function checkWorkflowStates($available) {
    // Check each state.
    foreach ($available as $state) {
      $selector = "#edit-moderation-state-0-state [value=$state]";
      $msg = "$selector not available";
      $elem = $this->getCurrentPage()->find('css', $selector);
      $this->assertNotNull($elem, $msg);
    }
    // Check the number of states.
    $options_selector = '#edit-moderation-state-0-state option';
    $options = $this->getCurrentPage()->findAll('css', $options_selector);
    $available_count = count($available);
    $msg = "The moderation options should be $available_count";
    $this->assertCount(count($available), $options, $msg);
  }

  /**
   * Tests topic page form.
   *
   * @dataProvider provider
   */
  public function testTopicPageEditForm($data) {

    // Create user.
    $user = $this->createUser();
    $user->addRole($data['role']);
    // Content administrators also require permissions from the editor role.
    if ($data['role'] == 'content_team') {
      $user->addRole('editor');
    }
    $user->activate();
    $user->save();
    $this->user = $user;
    $this->drupalLogin($this->user);

    // Create topic page not restricted to content administrators.
    $topic_page = $this->createTopicPage(self::UNRESTRICTED);
    $this->drupalGet('/node/' . $topic_page->id() . '/edit');

    $this->checkTopicPageEditForm($data['locked_fields'], $data['unlocked_fields']);

    $this->getCurrentPage()->findButton('Save')->click();
    $this->htmlOutput();
    $this->assertSession()->pageTextContains('Topic Page ' . $topic_page->label() . ' has been updated.');

    // Create topic page restricted to content administrators.
    $topic_page = $this->createTopicPage(self::RESTRICTED);
    $this->drupalGet('/node/' . $topic_page->id() . '/edit');

    // All fields become locked, except for content administrators.
    if ($data['role'] != 'content_team') {
      $data['locked_fields'] = array_merge($data['locked_fields'], $data['unlocked_fields']);
      $data['unlocked_fields'] = [];
    }

    $this->checkTopicPageEditForm($data['locked_fields'], $data['unlocked_fields']);

    $this->checkWorkflowStates($data['available_states']);
  }

  /**
   * Tests organization feedback form show/hide functionality.
   */
  public function testTopicPageShowHideFeedbackForm() {

    // Create user.
    $user = $this->createUser();
    $role = 'content_team';
    $user->addRole('content_team');
    // Content administrators also require permissions from the editor role.
    if ($role) {
      $user->addRole('editor');
    }

    $user->activate();
    $user->save();
    $this->user = $user;
    $this->drupalLogin($this->user);

    // Create topic page without a feedback form.
    $topic_page = $this->createTopicPageShowHideFeedbackForm(TRUE);
    $this->drupalGet($topic_page->toUrl()->toString());
    $this->assertFalse($this->getCurrentPage()->hasContent('Help Us Improve Mass.gov'), 'The feedback form is visible, while it must be hidden.');

    // Create topic page with a feedback form.
    $topic_page = $this->createTopicPageShowHideFeedbackForm(FALSE);
    $this->drupalGet($topic_page->toUrl()->toString());
    $this->assertTrue($this->getCurrentPage()->hasContent('Help Us Improve Mass.gov'), 'The feedback form is hidden, while it must be visible.');
  }

  /**
   * Provides data to test with testTopicPageOrganizationFieldControlsVisibility.
   */
  public function nonAdminRoles() {
    return [['editor'], ['author']];
  }

  /**
   * Tests access to organizations field on topic page edit form.
   *
   * @dataProvider nonAdminRoles
   */
  public function testTopicPageOrganizationFieldControlsVisibility(string $role) {
    // Create user.
    $user = $this->createUser();
    $user->addRole($role);
    $user->activate();
    $user->save();
    $this->user = $user;

    $this->drupalLogin($this->user);

    $topic_page = $this->createTopicPage(FALSE);
    $this->drupalGet($topic_page->toUrl('edit-form')->toString());
    $page = $this->getCurrentPage();
    // Assert field visibility.
    $field_names = ['Disable organization(s) field and make it optional', 'Hide sitewide feedback component'];
    foreach ($field_names as $field_name) {
      $this->assertFalse($page->hasField($field_name), "\"$field_name\" field should not be visible for "  . $role);
    }
  }

}
