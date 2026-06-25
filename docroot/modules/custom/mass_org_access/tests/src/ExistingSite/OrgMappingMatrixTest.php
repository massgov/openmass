<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\mass_org_access\Controller\OrgMappingImportController;
use Drupal\mass_org_access\Form\OrgMappingMatrixForm;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies the org mapping matrix editor (State save + CSV export).
 *
 * @group mass_org_access
 */
class OrgMappingMatrixTest extends MassExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::state()->delete(OrgMappingMatrixForm::STATE_KEY);
    parent::tearDown();
  }

  /**
   * Save merges the current page's selections into the State matrix.
   */
  public function testSaveMergesIntoStateMatrix(): void {
    // A node saved on a previous page must be preserved.
    \Drupal::state()->set(OrgMappingMatrixForm::STATE_KEY, [999 => [1, 2]]);

    $form = OrgMappingMatrixForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    // Select2 entity autocomplete submits the [['target_id' => tid], …] shape.
    $form_state->setValue('orgs', [
      111 => [['target_id' => 22], ['target_id' => 33]],
      222 => [],
    ]);
    $built = [];
    $form->saveSubmit($built, $form_state);

    $this->assertSame(
      [999 => [1, 2], 111 => [22, 33], 222 => []],
      \Drupal::state()->get(OrgMappingMatrixForm::STATE_KEY),
      'Save keeps other pages and records the current page (including cleared nodes).'
    );
  }

  /**
   * The CSV export streams the saved matrix in nodeid,termid order.
   */
  public function testMatrixCsvExportsSavedState(): void {
    \Drupal::state()->set(OrgMappingMatrixForm::STATE_KEY, [200 => [5, 6], 100 => [7]]);

    $response = OrgMappingImportController::create(\Drupal::getContainer())->matrixCsv();

    $this->assertSame("nodeid,termid\n100,7\n200,5\n200,6\n", $response->getContent());
    $this->assertStringContainsString(
      'attachment',
      $response->headers->get('Content-Disposition')
    );
  }

}
