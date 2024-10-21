<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\Core\Render\Markup;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\Tests\user\Traits\UserCreationTrait;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Class TableHeaderConstraintValidationTest.
 */
class TableHeaderConstraintValidationTest extends MassExistingSiteBase {
  use UserCreationTrait;

  /**
   * Creates and logs in a user with a specific role.
   */
  private function createAndLoginUser($role) {
    $user = $this->createUser();
    $user->addRole($role);
    $user->save();
    $this->drupalLogin($user);
  }

  /**
   * Assert that the validation works properly.
   */
  public function testTableHeaderConstraintValidation() {
    $table_markup = "<table>
        <tbody>
            <tr>
                <td>John</td>
                <td>Doe</td>
                <td>30</td>
            </tr>
            <tr>
                <td>Jane</td>
                <td>Smith</td>
                <td>25</td>
            </tr>
            <tr>
                <td>Mike</td>
                <td>Johnson</td>
                <td>45</td>
            </tr>
        </tbody>
    </table>";
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test info details',
      'field_info_detail_overview' => Markup::create($table_markup),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createAndLoginUser('administrator');
    $this->visit($node->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'Authors must define a header row for each table. Bold text alone does not create a header. Instructions: ';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message not found.');

  }

}
