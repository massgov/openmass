<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests where the SME / Content Owner field shows up, and where it must not.
 *
 * The ticket asks for the field on every supported type, on the Page Info tab,
 * and explicitly not on the published page or in its metadata.
 */
class SmeContentOwnerFieldPlacementTest extends MassExistingSiteBase {

  private const FIELD_NAME = 'field_sme_content_owner';

  private const ANCHOR_FIELD = 'field_content_organization';

  /**
   * Bundles the field is attached to, as [entity type, bundle] pairs.
   *
   * Read from the exported field instances rather than from the field map,
   * because PHPUnit builds the data set before Drupal has a container.
   * testProviderCoversEveryAttachedBundle checks the two agree.
   */
  public static function bundleProvider(): array {
    $cases = [];
    foreach (glob(self::configDirectory() . '/field.field.*.' . self::FIELD_NAME . '.yml') as $file) {
      [, , $entity_type, $bundle] = explode('.', basename($file));
      $cases[$entity_type . ':' . $bundle] = [$entity_type, $bundle];
    }
    return $cases;
  }

  /**
   * The config sync directory holding the exported field instances.
   */
  private static function configDirectory(): string {
    $directory = realpath(__DIR__ . '/../../../../../../../conf/drupal/config');
    if ($directory === FALSE) {
      throw new \RuntimeException('Could not locate the config sync directory.');
    }
    return $directory;
  }

  /**
   * Every bundle the site attaches the field to is covered by the provider.
   *
   * Guards against a field instance that exists on the site but was never
   * exported, and against an exported instance that never got imported.
   */
  public function testProviderCoversEveryAttachedBundle(): void {
    $attached = [];
    foreach (\Drupal::service('entity_field.manager')->getFieldMap() as $entity_type => $fields) {
      foreach ($fields[self::FIELD_NAME]['bundles'] ?? [] as $bundle) {
        $attached[] = $entity_type . ':' . $bundle;
      }
    }
    sort($attached);

    $covered = array_keys(self::bundleProvider());
    sort($covered);

    $this->assertSame($attached, $covered, 'The exported field instances and the live site disagree about which bundles carry the field.');
    $this->assertGreaterThan(30, count($covered), 'The field should be on every supported content type, not a handful.');
  }

  /**
   * Content can be tagged with more than one owner.
   */
  public function testFieldAcceptsMoreThanOneOwner(): void {
    foreach (['node', 'media'] as $entity_type) {
      $storage = \Drupal::service('entity_type.manager')
        ->getStorage('field_storage_config')
        ->load($entity_type . '.' . self::FIELD_NAME);

      $this->assertNotNull($storage, "No field storage for $entity_type.");
      $this->assertSame(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, $storage->getCardinality());
      $this->assertSame('taxonomy_term', $storage->getSetting('target_type'), 'Owners are taxonomy terms.');
    }
  }

  /**
   * Authors can pick an existing owner or add one, and are told how to name it.
   *
   * @dataProvider bundleProvider
   */
  public function testFieldInstanceGuidesAuthors(string $entity_type, string $bundle): void {
    $field = FieldConfig::loadByName($entity_type, $bundle, self::FIELD_NAME);
    $this->assertNotNull($field, "No field instance on $entity_type:$bundle.");

    $settings = $field->getSetting('handler_settings');
    $this->assertSame(['sme_owner' => 'sme_owner'], $settings['target_bundles'], 'Only the owner vocabulary is selectable.');
    $this->assertTrue((bool) $settings['auto_create'], 'Authors must be able to add an owner that does not exist yet.');

    $description = strtolower($field->getDescription());
    $this->assertStringContainsString('abbreviation', $description, 'The help text should ask for an organization abbreviation.');
    $this->assertStringContainsString('duplicate', $description, 'The help text should warn about duplicate names.');
  }

  /**
   * The field is editable on the add form of every supported bundle.
   *
   * @dataProvider bundleProvider
   */
  public function testFieldIsEditableOnTheAddForm(string $entity_type, string $bundle): void {
    $this->drupalLogin($this->createUser([], NULL, TRUE));

    $path = $entity_type === 'media' ? '/media/add/' . $bundle : '/node/add/' . $bundle;
    $this->visit($path);

    $this->assertEquals(200, $this->getSession()->getStatusCode(), "$path did not load.");
    $this->assertSession()->fieldExists(self::FIELD_NAME . '[0][target_id]');
  }

  /**
   * The form display keeps the field visible rather than disabled.
   *
   * @dataProvider bundleProvider
   */
  public function testFormDisplayKeepsTheFieldVisible(string $entity_type, string $bundle): void {
    $display = \Drupal::service('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle);
    $component = $display->getComponent(self::FIELD_NAME);

    $this->assertNotNull($component, "No widget configured for $entity_type:$bundle.");
    $this->assertSame('content', $component['region'] ?? 'content');
    $this->assertSame('entity_reference_autocomplete', $component['type']);

    if ($entity_type !== 'node') {
      return;
    }

    $groups = $display->getThirdPartySettings('field_group');
    $this->assertArrayHasKey('group_page_info', $groups, "No Page Info group on $bundle.");
    $this->assertContains(
      self::FIELD_NAME,
      $groups['group_page_info']['children'],
      "The field is not on the Page Info tab for $bundle."
    );
    $this->assertSame('content', $groups['group_page_info']['region'], "Page Info is disabled in the form display UI for $bundle.");
  }

  /**
   * The field belongs to one group only.
   *
   * Listing it in a parent group as well puts it outside the tab it was meant
   * for, which is what happened on Decision.
   *
   * @dataProvider bundleProvider
   */
  public function testFieldBelongsToExactlyOneGroup(string $entity_type, string $bundle): void {
    $display = \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $bundle);

    $holders = [];
    foreach ($display->getThirdPartySettings('field_group') as $name => $group) {
      if (in_array(self::FIELD_NAME, $group['children'], TRUE)) {
        $holders[] = $name;
      }
    }

    $this->assertSame([$this->expectedGroup($entity_type)], $holders, "The field should sit in exactly one group on $entity_type:$bundle.");
  }

  /**
   * The field reads directly under Permission Groups, or first when absent.
   *
   * @dataProvider bundleProvider
   */
  public function testFieldSitsDirectlyUnderPermissionGroups(string $entity_type, string $bundle): void {
    $display = \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $bundle);
    $group = $display->getThirdPartySetting('field_group', $this->expectedGroup($entity_type));
    $this->assertNotNull($group, "No group holding the field on $entity_type:$bundle.");

    $weights = [];
    foreach ($group['children'] as $child) {
      if ($component = $display->getComponent($child)) {
        $weights[$child] = $component['weight'];
      }
    }
    asort($weights);
    $order = array_keys($weights);
    $position = array_search(self::FIELD_NAME, $order, TRUE);
    $this->assertNotFalse($position, "The field has no widget on $entity_type:$bundle.");

    if (isset($weights[self::ANCHOR_FIELD])) {
      $anchor = array_search(self::ANCHOR_FIELD, $order, TRUE);
      $this->assertSame($anchor + 1, $position, 'The field must read directly under Permission Groups, but the order is: ' . implode(' | ', $order));
    }
    else {
      $this->assertSame(0, $position, 'Without Permission Groups the field must come first, but the order is: ' . implode(' | ', $order));
    }
  }

  /**
   * The group the field is expected to live in for a given entity type.
   */
  private function expectedGroup(string $entity_type): string {
    return $entity_type === 'node' ? 'group_page_info' : 'group_basic';
  }

  /**
   * No view display renders the field, on any view mode.
   *
   * A field absent from both content and hidden is filled in from the field
   * type defaults by EntityDisplayBase::init(), and for entity reference that
   * means it renders. Being missing from content is therefore not enough.
   *
   * @dataProvider bundleProvider
   */
  public function testNoViewModeRendersTheField(string $entity_type, string $bundle): void {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
    $displays = $storage->loadByProperties([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
    ]);
    $this->assertNotEmpty($displays, "No view displays found for $entity_type:$bundle.");

    foreach ($displays as $display) {
      $this->assertNull(
        $display->getComponent(self::FIELD_NAME),
        'The field renders in ' . $display->id() . ', but it must stay internal.'
      );
      $this->assertTrue(
        $display->get('hidden')[self::FIELD_NAME] ?? FALSE,
        'The field is not explicitly hidden in ' . $display->id() . ', so display defaults will render it.'
      );
    }
  }

}
