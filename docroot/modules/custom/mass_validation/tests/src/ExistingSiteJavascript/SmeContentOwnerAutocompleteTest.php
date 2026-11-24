<?php

namespace Drupal\Tests\mass_validation\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use MassGov\Dtt\MassExistingSiteSelenium2DriverTestBase;

/**
 * Tests entering SME / content owners in a real browser.
 *
 * Owner names end in brackets, "Jane Doe (DPH)", which is also how core's
 * autocomplete encodes an entity ID. Core used to read "DPH" as the ID and
 * refuse every new owner, so these tests go through the widget the way an
 * author does: typing, picking a suggestion, saving.
 */
class SmeContentOwnerAutocompleteTest extends MassExistingSiteSelenium2DriverTestBase {

  private const INPUT = 'input[name="field_sme_content_owner[0][target_id]"]';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createUser([], NULL, TRUE));
  }

  /**
   * A new owner typed with its organization is created and attached.
   *
   * Sloppy spacing is normalized, so it cannot become a second owner.
   */
  public function testTypedNewOwnerIsCreated(): void {
    $suffix = $this->randomMachineName(6);
    $node = $this->openEditForm();

    $this->typeOwner("Typed  Owner $suffix(DPH)");
    $this->save();

    $this->assertSame(["Typed Owner $suffix (DPH)"], $this->ownerNames($node));
    $this->cleanUpOwners($node);
  }

  /**
   * An existing owner typed in full, in another case, is reused.
   */
  public function testTypedExistingOwnerIsReused(): void {
    $owner = $this->createOwner('Typed Existing ' . $this->randomMachineName(6) . ' (EOTSS)');
    $node = $this->openEditForm();

    $this->typeOwner(mb_strtolower($owner->label()));
    $this->save();

    $this->assertSame([(int) $owner->id()], $this->ownerIds($node));
  }

  /**
   * Owner names a suggestion has to cope with.
   *
   * A comma makes the project's matcher wrap the suggestion in quotes.
   */
  public static function pickedNameProvider(): array {
    return [
      'plain name' => ['Picked Owner %s (DPH)'],
      'name with a comma' => ['Sullivan %s, Nancy (OCABR legal)'],
    ];
  }

  /**
   * Picking a suggestion from the dropdown attaches that owner.
   *
   * The suggestion carries the term ID and, from the project's matcher, the
   * vocabulary name after it; both have to parse back to the right owner.
   *
   * @dataProvider pickedNameProvider
   */
  public function testPickedSuggestionIsAttached(string $pattern): void {
    $owner = $this->createOwner(sprintf($pattern, $this->randomMachineName(6)));
    $node = $this->openEditForm();

    // Core's autocomplete.js only searches the text after the last comma, so
    // authors look up a name like this one by its start.
    $needle = mb_substr(strtok($owner->label(), ','), 0, 16);
    $this->pickSuggestion($needle, $owner->label());
    $value = $this->getSession()->evaluateScript(sprintf('document.querySelector(%s).value', json_encode(self::INPUT)));
    $this->assertStringContainsString(
      $owner->label() . ' (' . $owner->id() . ')',
      $value,
      'The picked suggestion should carry the name followed by the term ID.'
    );
    $this->save();

    $this->assertSame([(int) $owner->id()], $this->ownerIds($node));
  }

  /**
   * A new owner without an organization is refused next to the field.
   */
  public function testOwnerWithoutOrganizationIsRefused(): void {
    $node = $this->openEditForm();

    $this->typeOwner('No Agency ' . $this->randomMachineName(6));
    $this->getSession()->getPage()->pressButton('edit-submit');

    $this->assertNotNull(
      $this->assertSession()->waitForText('needs an organization abbreviation in parentheses', 20),
      'The format message should be shown.'
    );
    $this->assertSame([], $this->ownerIds($node));
  }

  /**
   * Creates a page and opens its edit form on the Page Info tab.
   */
  private function openEditForm(): NodeInterface {
    $node = $this->createNode([
      'type' => 'action',
      'title' => 'Owner autocomplete ' . $this->randomMachineName(8),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $tab = $this->assertSession()->waitForElement('css', 'a[href="#edit-group-page-info"]', 20);
    $this->assertNotNull($tab, 'The Page Info tab is missing.');
    $tab->click();
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', self::INPUT, 20), 'The owner field is not visible on Page Info.');
    return $node;
  }

  /**
   * Types into the owner field without picking a suggestion.
   */
  private function typeOwner(string $value): void {
    $this->getSession()->getPage()->find('css', self::INPUT)->setValue($value);
    $this->getSession()->executeScript('jQuery(".ui-autocomplete").hide();');
  }

  /**
   * Searches the autocomplete and clicks the suggestion matching a label.
   */
  private function pickSuggestion(string $needle, string $label): void {
    $session = $this->getSession();
    $session->getPage()->find('css', self::INPUT)->setValue($needle);
    $session->executeScript(sprintf(
      '(function () { var $i = jQuery(%s); $i.focus(); $i.autocomplete("search", %s); })();',
      json_encode(self::INPUT),
      json_encode($needle)
    ));

    $find = sprintf(
      'Array.from(document.querySelectorAll(".ui-autocomplete li")).find(function (li) { return li.textContent.indexOf(%s) !== -1; })',
      json_encode($label)
    );
    $shown = $session->getPage()->waitFor(15, fn() => (bool) $session->evaluateScript('!!' . $find));
    $this->assertTrue($shown, "No suggestion for '$label' appeared.");
    $session->executeScript($find . '.querySelector("a, .ui-menu-item-wrapper").click();');
  }

  /**
   * Saves the form and waits for the redirect away from it.
   */
  private function save(): void {
    $session = $this->getSession();
    $session->getPage()->pressButton('edit-submit');
    $left = $session->getPage()->waitFor(30, fn() => !str_ends_with(parse_url($session->getCurrentUrl(), PHP_URL_PATH), '/edit'));
    if (!$left) {
      $errors = $session->evaluateScript('Array.from(document.querySelectorAll(".form-item--error-message, [role=alert]")).map(function (e) { return e.textContent.trim(); }).join(" | ")');
      $this->fail('The form did not save: ' . $errors);
    }
  }

  /**
   * Owner names on the stored node.
   */
  private function ownerNames(NodeInterface $node): array {
    return array_map(fn($term) => $term->label(), $this->reload($node)->get('field_sme_content_owner')->referencedEntities());
  }

  /**
   * Owner term IDs on the stored node.
   */
  private function ownerIds(NodeInterface $node): array {
    return array_map('intval', array_column($this->reload($node)->get('field_sme_content_owner')->getValue(), 'target_id'));
  }

  /**
   * Loads the node fresh from storage.
   */
  private function reload(NodeInterface $node): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

  /**
   * Creates an owner term.
   */
  private function createOwner(string $name): TermInterface {
    return $this->createTerm(Vocabulary::load('sme_owner'), ['name' => $name, 'langcode' => 'en']);
  }

  /**
   * Removes owners the form created, which the framework does not track.
   */
  private function cleanUpOwners(NodeInterface $node): void {
    foreach ($this->reload($node)->get('field_sme_content_owner')->referencedEntities() as $term) {
      $this->markEntityForCleanup($term);
    }
  }

}
