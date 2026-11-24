<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_flagging\Unit\Service;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\diff\DiffBuilderManager;
use Drupal\diff\DiffEntityParser;
use Drupal\diff\DiffFormatter;
use Drupal\diff\FieldDiffBuilderInterface;
use Drupal\diff\FieldReferenceInterface;
use Drupal\mass_flagging\Service\MassFlaggingEntityComparison;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\workflows\WorkflowTypeInterface;

/**
 * Tests the Diff methods restored for Diff 2.1.
 *
 * @coversDefaultClass \Drupal\mass_flagging\Service\MassFlaggingEntityComparison
 * @group mass_flagging
 */
class MassFlaggingEntityComparisonTest extends UnitTestCase {

  /**
   * The comparison service under test.
   */
  private MassFlaggingEntityComparison $comparison;

  /**
   * Diff builder manager used by summary().
   */
  private DiffBuilderManager $diffBuilderManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->diffBuilderManager = $this->createMock(DiffBuilderManager::class);
    $plugins = $this->createMock(ImmutableConfig::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('diff.plugins')->willReturn($plugins);
    $field_types = $this->createMock(FieldTypePluginManagerInterface::class);
    $field_types->method('getDefinitions')->willReturn([]);

    $this->comparison = new MassFlaggingEntityComparison(
      $config_factory,
      $this->createMock(DiffFormatter::class),
      $field_types,
      $this->createMock(DiffEntityParser::class),
      $this->diffBuilderManager,
    );
  }

  /**
   * A shown field is stored under the Diff 1.x summary key.
   *
   * @covers ::summary
   */
  public function testSummaryIncludesShownFieldValueAndLabel(): void {
    $items = $this->fieldItems('title', 'Title', [['value' => 'Hello']]);
    $this->expectBuilder($items->getFieldDefinition(), $this->scalarBuilder(), TRUE);
    $entity = $this->entity('node', 15, ['title' => $items]);

    $summary = $this->summary($entity);

    $this->assertSame([
      '15:node.title' => [
        'value' => [['value' => 'Hello']],
        'label' => 'Title',
      ],
    ], $summary);
  }

  /**
   * Fields the diff builder hides are left out of the summary.
   *
   * @covers ::summary
   */
  public function testSummarySkipsHiddenFields(): void {
    $items = $this->fieldItems('nid', 'ID', [['value' => 15]]);
    $this->expectBuilder($items->getFieldDefinition(), $this->scalarBuilder(), FALSE);
    $entity = $this->entity('node', 15, ['nid' => $items]);

    $this->assertSame([], $this->summary($entity));
  }

  /**
   * A referenced paragraph field keeps its key and prefixes the label.
   *
   * @covers ::summary
   */
  public function testSummaryPrefixesReferencedFieldLabels(): void {
    $image = $this->fieldItems('field_image', 'Image', [['target_id' => 4]]);
    $paragraph = $this->entity('paragraph', 7, ['field_image' => $image]);
    $reference = $this->fieldItems('field_sections', 'Section', [], 1);
    $builder = $this->referenceBuilder([$paragraph]);

    $this->diffBuilderManager->method('createInstanceForFieldDefinition')
      ->willReturnCallback(function ($definition) use ($image, $reference, $builder) {
        if ($definition === $reference->getFieldDefinition()) {
          return $builder;
        }
        return $this->scalarBuilder();
      });
    $this->diffBuilderManager->method('showDiff')->willReturn(TRUE);

    $entity = $this->entity('node', 15, ['field_sections' => $reference]);
    $summary = $this->summary($entity);

    $this->assertSame([['target_id' => 4]], $summary['7:paragraph.field_image']['value']);
    $this->assertSame('Section - Image', $summary['7:paragraph.field_image']['label']);
  }

  /**
   * Multi-value references number the nested field label.
   *
   * @covers ::summary
   */
  public function testSummaryNumbersMultiValueReferences(): void {
    $image = $this->fieldItems('field_image', 'Image', [['target_id' => 4]]);
    $paragraph = $this->entity('paragraph', 7, ['field_image' => $image]);
    $reference = $this->fieldItems('field_sections', 'Section', [], -1);
    $builder = $this->referenceBuilder([0 => $paragraph]);

    $this->diffBuilderManager->method('createInstanceForFieldDefinition')
      ->willReturnCallback(function ($definition) use ($image, $reference, $builder) {
        if ($definition === $reference->getFieldDefinition()) {
          return $builder;
        }
        return $this->scalarBuilder();
      });
    $this->diffBuilderManager->method('showDiff')->willReturn(TRUE);

    $entity = $this->entity('node', 15, ['field_sections' => $reference]);
    $summary = $this->summary($entity);

    $this->assertSame('Section 1 Image', $summary['7:paragraph.field_image']['label']);
  }

  /**
   * Moderation state is omitted when the service has no moderation info.
   *
   * @covers ::getModerationState
   */
  public function testModerationStateIsFalseWithoutModerationInformation(): void {
    $entity = $this->entity('node', 15, []);

    $this->assertFalse($this->moderationState($entity));
  }

  /**
   * An unmoderated entity does not get a state label.
   *
   * @covers ::getModerationState
   */
  public function testModerationStateIsFalseWhenEntityIsNotModerated(): void {
    $moderation = $this->createMock(ModerationInformationInterface::class);
    $moderation->method('isModeratedEntity')->willReturn(FALSE);
    $this->setModerationInformation($moderation);

    $this->assertFalse($this->moderationState($this->entity('node', 15, [])));
  }

  /**
   * A moderated entity returns the workflow state's label.
   *
   * @covers ::getModerationState
   */
  public function testModerationStateReturnsWorkflowLabel(): void {
    $state = $this->createMock(StateInterface::class);
    $state->method('label')->willReturn('Published');
    $workflow_type = $this->createMock(WorkflowTypeInterface::class);
    $workflow_type->method('getState')->with('published')->willReturn($state);
    $workflow = $this->createMock(WorkflowInterface::class);
    $workflow->method('getTypePlugin')->willReturn($workflow_type);
    $moderation = $this->createMock(ModerationInformationInterface::class);
    $moderation->method('isModeratedEntity')->willReturn(TRUE);
    $moderation->method('getWorkflowForEntity')->willReturn($workflow);
    $this->setModerationInformation($moderation);

    $items = new \stdClass();
    $items->value = 'published';
    $entity = $this->entity('node', 15, []);
    $entity->method('get')->with('moderation_state')->willReturn($items);

    $this->assertSame('Published', $this->moderationState($entity));
  }

  /**
   * Builds a content entity whose fields are the given item lists.
   *
   * @param array<string, \Drupal\Core\Field\FieldItemListInterface> $fields
   *   Fields yielded when the entity is iterated.
   */
  private function entity(string $entity_type_id, int $id, array $fields): ContentEntityBase {
    $entity = $this->getMockBuilder(ContentEntityBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getEntityTypeId', 'id', 'getIterator', 'get'])
      ->getMock();
    $entity->method('getEntityTypeId')->willReturn($entity_type_id);
    $entity->method('id')->willReturn($id);
    $entity->method('getIterator')->willReturn(new \ArrayIterator($fields));
    return $entity;
  }

  /**
   * Builds a field item list with the label and raw value summary() stores.
   */
  private function fieldItems(string $name, string $label, array $value, int $cardinality = 1): FieldItemListInterface {
    $storage = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage->method('getCardinality')->willReturn($cardinality);
    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getLabel')->willReturn($label);
    $definition->method('getFieldStorageDefinition')->willReturn($storage);
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getName')->willReturn($name);
    $items->method('getFieldDefinition')->willReturn($definition);
    $items->method('getValue')->willReturn($value);
    return $items;
  }

  /**
   * A diff builder that summary() treats as a plain field.
   */
  private function scalarBuilder(): FieldDiffBuilderInterface {
    return $this->createMock(FieldDiffBuilderInterface::class);
  }

  /**
   * A diff builder that summary() recurses into.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   Referenced entities, keyed by delta.
   */
  private function referenceBuilder(array $entities): FieldReferenceInterface {
    return new class($entities) implements FieldDiffBuilderInterface, FieldReferenceInterface {

      public function __construct(private array $entities) {}

      public function getEntitiesToDiff(FieldItemListInterface $field_items): array {
        return $this->entities;
      }

      public function build(FieldItemListInterface $field_items): mixed {
        return [];
      }

      public static function isApplicable(FieldStorageDefinitionInterface $field_definition): bool {
        return TRUE;
      }

      public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        return [];
      }

      public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

      public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

      public function getConfiguration() {
        return [];
      }

      public function setConfiguration(array $configuration) {}

      public function defaultConfiguration() {
        return [];
      }

    };
  }

  /**
   * Stubs the builder lookup for a single field.
   */
  private function expectBuilder(FieldDefinitionInterface $definition, ?FieldDiffBuilderInterface $builder, bool $show): void {
    $this->diffBuilderManager->method('createInstanceForFieldDefinition')
      ->with($definition)
      ->willReturn($builder);
    $this->diffBuilderManager->method('showDiff')->willReturn($show);
  }

  /**
   * Calls the protected summary() restored from Diff 1.x.
   */
  private function summary(ContentEntityInterface $revision): array {
    $method = new \ReflectionMethod(MassFlaggingEntityComparison::class, 'summary');
    return $method->invoke($this->comparison, $revision);
  }

  /**
   * Calls the protected getModerationState() restored from Diff 1.x.
   */
  private function moderationState(ContentEntityInterface $entity): string|false {
    $method = new \ReflectionMethod(MassFlaggingEntityComparison::class, 'getModerationState');
    return $method->invoke($this->comparison, $entity);
  }

  /**
   * Sets the moderation service the production container never injects.
   */
  private function setModerationInformation(ModerationInformationInterface $moderation): void {
    $property = new \ReflectionProperty(MassFlaggingEntityComparison::class, 'moderationInformation');
    $property->setValue($this->comparison, $moderation);
  }

}
