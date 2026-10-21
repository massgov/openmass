<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Only the Executive Orders role may create, edit or schedule executive orders.
 */
#[Group('mass_content')]
class ExecutiveOrderAccessTest extends MassExistingSiteBase {

  private const SCHEDULE_PERMISSIONS = [
    'add scheduled transitions node executive_order',
    'reschedule scheduled transitions node executive_order',
  ];

  /**
   * A published executive order.
   */
  private NodeInterface $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->order = $this->createNode([
      'type' => 'executive_order',
      'title' => 'EO access ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Creates an active user with the given roles.
   */
  private function userWithRoles(array $roles): UserInterface {
    $user = $this->createUser();
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * An editor without the Executive Orders role cannot touch executive orders.
   */
  public function testEditorCannotEditExecutiveOrders(): void {
    $editor = $this->userWithRoles(['editor']);
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');

    $this->assertFalse($this->order->access('update', $editor), 'Editor must not edit an executive order.');
    $this->assertFalse($this->order->access('delete', $editor), 'Editor must not delete an executive order.');
    $this->assertFalse($handler->createAccess('executive_order', $editor), 'Editor must not create an executive order.');
    foreach (self::SCHEDULE_PERMISSIONS as $permission) {
      $this->assertFalse($editor->hasPermission($permission), "Editor must not have '$permission'.");
    }
    $this->assertTrue($this->order->access('view', $editor), 'Editor can still view an executive order.');
  }

  /**
   * An editor with the Executive Orders role can create, edit and schedule.
   */
  public function testExecutiveOrdersRoleCanEditExecutiveOrders(): void {
    $user = $this->userWithRoles(['editor', 'executive_orders']);
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');

    $this->assertTrue($this->order->access('update', $user), 'Executive Orders role must edit an executive order.');
    $this->assertTrue($handler->createAccess('executive_order', $user), 'Executive Orders role must create an executive order.');
    foreach (self::SCHEDULE_PERMISSIONS as $permission) {
      $this->assertTrue($user->hasPermission($permission), "Executive Orders role must have '$permission'.");
    }
  }

}
