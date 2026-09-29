<?php

namespace Drupal\Tests\mass_utility\ExistingSite;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormState;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Issuer autocomplete AJAX callbacks on advisory and executive order forms.
 */
#[Group('mass_utility')]
class IssuerAjaxCallbackTest extends MassExistingSiteBase {

  /**
   * Callback name and issuer field, per host bundle.
   */
  public static function callbackProvider(): array {
    return [
      'executive_order' => ['mass_utility_get_issuer_executive_ajax_callback', 'field_executive_order_issuer'],
      'advisory' => ['mass_utility_get_issuer_advisory_ajax_callback', 'field_advisory_issuer'],
    ];
  }

  /**
   * Choosing an organization page as issuer fills the content id.
   *
   * Organization pages have no field_person_ref_org, which used to throw and
   * turn the AJAX request into a 500.
   */
  #[DataProvider('callbackProvider')]
  public function testOrganizationIssuer(string $callback, string $field): void {
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Issuer org ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $response = $this->invoke($callback, $field, (int) $org->id());
    $this->assertInstanceOf(AjaxResponse::class, $response);
    $this->assertStringContainsString((string) $org->id(), json_encode($response->getCommands()));
  }

  /**
   * Choosing a person as issuer resolves to the person's organization.
   */
  #[DataProvider('callbackProvider')]
  public function testPersonIssuer(string $callback, string $field): void {
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Person org ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $person = $this->createNode([
      'type' => 'person',
      'title' => 'Issuer person ' . $this->randomMachineName(),
      'field_person_ref_org' => [$org->id()],
    ]);
    $response = $this->invoke($callback, $field, (int) $person->id());
    $this->assertInstanceOf(AjaxResponse::class, $response);
    $this->assertStringContainsString('"' . $org->id() . '"', json_encode($response->getCommands()));
  }

  /**
   * An empty selection returns an empty AJAX response, not FALSE.
   *
   * Core hands anything that is not an AjaxResponse to the AJAX renderer,
   * which requires an array, so FALSE turned clearing the field into a 500.
   */
  #[DataProvider('callbackProvider')]
  public function testEmptySelection(string $callback, string $field): void {
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#field_parents' => [$field, 0, 'subform']]);
    $form = [];
    $response = $callback($form, $form_state);
    $this->assertInstanceOf(AjaxResponse::class, $response);
    $this->assertSame([], $response->getCommands());
  }

  /**
   * Runs an issuer callback with the given node selected at delta 0.
   */
  private function invoke(string $callback, string $field, int $nid) {
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#field_parents' => [$field, 0, 'subform']]);
    $form_state->setValue([$field, 0, 'subform', 'field_issuer_issuers', 0, 'target_id'], $nid);
    $form = [];
    return $callback($form, $form_state);
  }

}
