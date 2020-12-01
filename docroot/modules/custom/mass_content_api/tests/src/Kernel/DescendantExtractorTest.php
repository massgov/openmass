<?php

namespace Drupal\Tests\mass_content_api\ExistingSite;

use Drupal\mass_content_api\DescendantExtractor;
use Drupal\Tests\UnitTestCase;

/**
 * Verify DescendantManager relationship traversal functionality.
 *
 * @group mass_content_api
 */
class DescendantExtractorTest extends UnitTestCase {

  /**
   * Ensure passing an empty empty entity ref field yields an empty array.
   *
   * @see fetchRelations()
   */
  public function testDescendantTraversalEmptyResult() {
    $extractor = new DescendantExtractor();
    $spec = [];
    $spec['parents'] = [
      [
        0 => 'field_1',
        1 => '*',
      ],
    ];

    $entity_info = [
      'fields' => [
        'field_1' => [
          'type' => 'entity_reference',
          'referenced_entities' => [],
        ],
      ],
    ];

    $entity = $this->makeMockContentEntityInterface($entity_info);
    $result = $extractor->fetchRelations($entity, $spec);

    $expected = [
      'parents' => [],
    ];

    $this->assertEquals($expected, $result);

  }

  /**
   * Ensure passing a search key yields only that field in results.
   *
   * @see fetchRelations()
   */
  public function testDescendantTraversalSearchByField() {
    $extractor = new DescendantExtractor();
    $spec = [];
    $spec['parents'] = [
      [
        0 => 'field_1',
        1 => 'field_4',
      ],
    ];

    $entity_info = [
      'fields' => [
        'field_1' => [
          'type' => 'entity_reference',
          'entity_type' => 'paragraph',
          'referenced_entities' => [
            [
              'fields' => [
                'field_2' => [
                  'type' => 'entity_reference',
                  'entity_type' => 'paragraph',
                  'referenced_entities' => [
                    [
                      'fields' => [
                        'field_3' => [
                          'type' => 'list',
                          'list_items' => [
                            'link' => ['entity:node/1', 'entity:node/2'],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
                'field_4' => [
                  'type' => 'list',
                  'list_items' => [
                    'link' => ['entity:node/3', 'entity:node/4'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $entity = $this->makeMockContentEntityInterface($entity_info);

    $result = $extractor->fetchRelations($entity, $spec);

    $expected = [
      'parents' => [
        'field_4' => [
          3 => [
            'id' => 3,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_4',
          ],
          4 => [
            'id' => 4,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_4',
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Ensure we can search on all (*) fields and get all results from the next level.
   *
   * @see fetchRelations()
   */
  public function testDescendantTraversalSearchUnlimited() {
    $extractor = new DescendantExtractor();
    $spec = [
      'parents' => [
        [
          0 => 'field_1',
          1 => '*',
        ],
      ],
      'children' => [
        [
          0 => 'field_2',
          1 => '*',
        ],
      ],
    ];

    $entity_info = [
      'fields' => [
        'field_1' => [
          'type' => 'entity_reference',
          'entity_type' => 'paragraph',
          'referenced_entities' => [
            [
              'fields' => [
                'field_3' => [
                  'type' => 'list',
                  'list_items' => [
                    'link' => ['entity:node/1', 'entity:node/2'],
                  ],
                ],
                'field_5' => [
                  'type' => 'list',
                  'list_items' => [
                    'link' => ['entity:node/3', 'entity:node/4'],
                  ],
                ],
              ],
            ],
          ],
        ],
        'field_2' => [
          'type' => 'entity_reference',
          'entity_type' => 'paragraph',
          'referenced_entities' => [
            [
              'fields' => [
                'field_5' => [
                  'type' => 'entity',
                  'entities' => [
                    'entity_5' => [
                      'id' => 5,
                      'entity_type' => 'node',
                    ],
                  ],
                ],
                'field_6' => [
                  'type' => 'list',
                  'list_items' => [
                    'link' => ['entity:node/6', 'entity:node/7'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $entity = $this->makeMockContentEntityInterface($entity_info);

    $result = $extractor->fetchRelations($entity, $spec);

    $expected = [
      'parents' => [
        'field_3' => [
          1 => [
            'id' => 1,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_3',
          ],
          2 => [
            'id' => 2,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_3',
          ],
        ],
        'field_5' => [
          3 => [
            'id' => 3,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_5',
          ],
          4 => [
            'id' => 4,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_5',
          ],
        ],
      ],
      'children' => [
        'field_5' => [
          5 => [
            'id' => 5,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_5',
          ],
        ],
        'field_6' => [
          6 => [
            'id' => 6,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_6',
          ],
          7 => [
            'id' => 7,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_6',
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Ensure we can traverse multiple levels for a single search key.
   *
   * @see fetchRelations()
   */
  public function testDescendantTraversalSearchOnKeyTraversal() {
    $extractor = new DescendantExtractor();
    $spec = [];
    // Field 4 is our search key we'll bury it 3 layers deep.
    $spec = [
      'parents' => [
        [
          0 => 'field_1',
          1 => 'field_2',
          2 => 'field_3',
          3 => 'field_4',
        ],
      ],
    ];

    $entity_info = [
      'fields' => [
        'field_1' => [
          'type' => 'entity_reference',
          'entity_type' => 'paragraph',
          'referenced_entities' => [
            [
              'fields' => [
                'field_2' => [
                  'type' => 'entity_reference',
                  'entity_type' => 'paragraph',
                  'referenced_entities' => [
                    [
                      'fields' => [
                        'field_3' => [
                          'type' => 'entity_reference',
                          'entity_type' => 'paragraph',
                          'referenced_entities' => [
                            [
                              'fields' => [
                                'field_4' => [
                                  'type' => 'list',
                                  'list_items' => [
                                    'link' => ['entity:node/1', 'entity:node/2'],
                                  ],
                                ],
                              ],
                            ],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $entity = $this->makeMockContentEntityInterface($entity_info);

    $result = $extractor->fetchRelations($entity, $spec);

    $expected = [
      'parents' => [
        'field_4' => [
          1 => [
            'id' => 1,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_4',
          ],
          2 => [
            'id' => 2,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_4',
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Ensure we get all fields on a multi-traversal * key.
   *
   * @see fetchRelations()
   */
  public function testDescendantTraversalSearchAllTraversal() {
    $extractor = new DescendantExtractor();
    $spec = [
      'parents' => [
        [
          0 => 'field_1',
          1 => 'field_2',
          2 => 'field_3',
          3 => '*',
        ],
      ],
    ];

    $entity_info = [
      'fields' => [
        'field_1' => [
          'type' => 'entity_reference',
          'entity_type' => 'paragraph',
          'referenced_entities' => [
            [
              'fields' => [
                'field_2' => [
                  'type' => 'entity_reference',
                  'entity_type' => 'paragraph',
                  'referenced_entities' => [
                    [
                      'fields' => [
                        'field_3' => [
                          'type' => 'entity_reference',
                          'entity_type' => 'paragraph',
                          'referenced_entities' => [
                            [
                              'fields' => [
                                'field_4' => [
                                  'type' => 'list',
                                  'list_items' => [
                                    'link' => ['entity:node/1', 'entity:node/2'],
                                  ],
                                ],
                                'field_5' => [
                                  'type' => 'list',
                                  'list_items' => [
                                    'link' => ['entity:node/3', 'entity:node/4'],
                                  ],
                                ],
                                'field_6' => [
                                  'type' => 'list',
                                  'list_items' => [
                                    'link' => ['entity:node/5', 'entity:node/6'],
                                  ],
                                ],
                              ],
                            ],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $entity = $this->makeMockContentEntityInterface($entity_info);

    $result = $extractor->fetchRelations($entity, $spec);

    $expected = [
      'parents' => [
        'field_4' => [
          1 => [
            'id' => 1,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_4',
          ],
          2 => [
            'id' => 2,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_4',
          ],
        ],
        'field_5' => [
          3 => [
            'id' => 3,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_5',
          ],
          4 => [
            'id' => 4,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_5',
          ],
        ],
        'field_6' => [
          5 => [
            'id' => 5,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_6',
          ],
          6 => [
            'id' => 6,
            'entity' => 'node',
            'field_label' => '',
            'field_name' => 'field_6',
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Build mock FieldStorageDefinitionInterface.
   *
   * @param bool $is_base_field
   *   Return value of the isBaseField() method.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
   *   mock \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  private function getMockFieldStorageDefinitionInterface($is_base_field) {
    $mock = $this->getMockBuilder('\Drupal\Core\Field\FieldStorageDefinitionInterface')
      ->disableOriginalConstructor()
      ->setMethods(['isBaseField'])
      ->getMockForAbstractClass();

    $mock->method('isBaseField')
      ->will($this->returnValue($is_base_field));

    return $mock;
  }

  /**
   * Build mock FieldDefinisionInterface.
   *
   * @param mixed $storage
   *   Return value of the getFieldStorageDefinition() method.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   mock \Drupal\Core\Field\FieldDefinitionInterface.
   */
  private function getMockFieldDefinitionInterface($storage) {
    $mock = $this->getMockBuilder('\Drupal\Core\Field\FieldDefinitionInterface')
      ->disableOriginalConstructor()
      ->setMethods(['getFieldStorageDefinition'])
      ->getMockForAbstractClass();

    $mock->method('getFieldStorageDefinition')
      ->will($this->returnValue($storage));

    return $mock;
  }

  /**
   * Build mock EntityReferenceFieldItemList.
   *
   * @param string $name
   *   Return value of the getName() method.
   * @param array $referenced_entities
   *   Return value of the referencedEntities() method.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemList
   *   mock \Drupal\Core\Field\EntityReferenceFieldItemList.
   */
  private function getMockEntityReferenceFieldItemList($name, array $referenced_entities) {
    $mock = $this->getMockBuilder('\Drupal\Core\Field\EntityReferenceFieldItemList')
      ->disableOriginalConstructor()
      ->setMethods(['getName', 'referencedEntities', 'getSetting'])
      ->getMockForAbstractClass();

    $mock->method('getName')
      ->willReturn($name);

    $mock->method('referencedEntities')
      ->willReturn($referenced_entities);

    return $mock;
  }

  /**
   * Build mock EntityReferenceRevisionsFieldItemList.
   *
   * @param string $name
   *   Return value of the getName() method.
   * @param array $referenced_entities
   *   Return value of the referencedEntities() method.
   *
   * @return \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList
   *   mock \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList.
   */
  private function getMockEntityReferenceRevisionsFieldItemList($name, array $referenced_entities) {
    $mock = $this->getMockBuilder('\Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList')
      ->disableOriginalConstructor()
      ->setMethods(['getName', 'referencedEntities'])
      ->getMockForAbstractClass();

    $mock->method('getName')
      ->willReturn($name);

    $mock->method('referencedEntities')
      ->willReturn($referenced_entities);

    return $mock;
  }

  /**
   * Build mock LinkItem.
   *
   * @param string $value
   *   Value of the uri property.
   *
   * @return \Drupal\link\Plugin\Field\FieldType\LinkItem
   *   mock \Drupal\link\Plugin\Field\FieldType\LinkItem.
   */
  private function getMockLinkItem($value) {
    $mock = $this->getMockBuilder('\Drupal\link\Plugin\Field\FieldType\LinkItem')
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();

    $mock->uri = $value;

    return $mock;
  }

  /**
   * Build mock FieldItemList.
   *
   * @param string $name
   *   Return value of the getName() method.
   * @param array $referenced_entities
   *   Return value of the referencedEntities() method.
   *
   * @return \Drupal\Core\Field\FieldItemList
   *   mock \Drupal\Core\Field\FieldItemList.
   */
  private function getMockFieldItemList($name, array $referenced_entities) {
    $mock = $this->getMockBuilder('\Drupal\Core\Field\FieldItemList')
      ->disableOriginalConstructor()
      ->setMethods(['getName', 'getIterator'])
      ->getMockForAbstractClass();

    $mock->method('getName')
      ->willReturn($name);

    $mock->method('getIterator')
      ->will($this->returnCallback(function () use ($referenced_entities) {
        return new \ArrayIterator($referenced_entities);
      })
    );

    return $mock;
  }

  /**
   * Build mock ContentEntityInterface.
   *
   * @param array $field_definitions
   *   Return value for getFieldDefinition() method.
   * @param array $fields
   *   Return value for getFields() method.
   * @param array $field_info
   *   Return value for getEntityTypeId() method.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   mock \Drupal\Core\Entity\ContentEntityInterface.
   */
  private function getMockContentEntityInterface(array $field_definitions, array $fields, array $field_info) {
    // The keys for both $field_definitions and $fields should be identical...
    $missing_fields = array_diff_key($field_definitions, $fields);
    $missing_definitions = array_diff_key($fields, $field_definitions);

    if (!empty($missing_fields)) {
      $missing_names = implode(", ", array_keys($missing_fields));

      throw new \InvalidArgumentException(
        "Missing field values for fields: " . $missing_names
      );
    }

    if (!empty($missing_definitions)) {
      $missing_names = implode(", ", array_keys($missing_definitions));

      throw new \InvalidArgumentException(
        "Missing field definitions for fields: " . $missing_names
      );
    }

    $mock = $this->getMockBuilder('\Drupal\Core\Entity\ContentEntityInterface')
      ->disableOriginalConstructor()
      ->setMethods([
        'id',
        'getEntityTypeId',
        'getFields',
        'get',
        'getFieldDefinition',
        'hasField',
      ])
      ->getMockForAbstractClass();

    // We're assuming in our tests that this is always going to be called with
    // FALSE, so we should include that in our definition of the method here.
    $mock->method('id')
      ->will($this->returnCallback(function () use ($field_info) {
        if (isset($field_info['id'])) {
          return $field_info['id'];
        }
        else {
          return NULL;
        }
      }));

    $mock->method('getEntityTypeId')
      ->will($this->returnCallback(function () use ($field_info) {
        if (isset($field_info['entity_type'])) {
          return $field_info['entity_type'];
        }
        else {
          return NULL;
        }
      }));

    $mock->method('getFields')
      ->with($this->equalTo(FALSE))
      ->willReturn($fields);

    $mock->method('get')
      ->will(
        $this->returnCallback(function ($name) use ($fields) {
          if (array_key_exists($name, $fields)) {
            return $fields[$name];
          }
          else {
            return NULL;
          }
        })
      );

    $mock->method('getFieldDefinition')
      ->will(
        $this->returnCallback(function ($name) use ($field_definitions) {
          if (array_key_exists($name, $field_definitions)) {
            return $field_definitions[$name];
          }
          else {
            return NULL;
          }
        })
      );

    $mock->method('hasField')
      ->will(
        $this->returnCallback(function ($name) use ($fields) {
          if (array_key_exists($name, $fields)) {
            return $fields[$name];
          }
          else {
            return NULL;
          }
        })
      );

    return $mock;
  }

  /**
   * Setup method for sorting fields array in tests to correct mock classes.
   *
   * @param array $params
   *   Array of parameters defined in tests.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   mock ContentEntityInterface.
   */
  private function makeMockContentEntityInterface(array $params) {
    $fields = [];
    $field_definitions = [];
    $field_info = [];

    if (isset($params['fields'])) {
      foreach ($params['fields'] as $name => $field) {
        if (!array_key_exists('name', $field)) {
          $field['name'] = $name;
        }

        if (isset($field['entity_type'])) {
          $field_info['entity_type'] = $field['entity_type'];
        }

        if (isset($field['definition'])) {
          $definition = $this->makeMockFieldDefinition($field['definition']);
        }
        else {
          $definition = $this->makeMockFieldDefinition([]);
        }

        $value = $this->makeMockField($field);

        $fields[$name] = $value;
        $field_definitions[$name] = $definition;
      }
    }
    else {
      foreach ($params as $name => $entity) {
        $field_info['name'] = $name;
        if (isset($entity['id'])) {
          $field_info['id'] = $entity['id'];
        }
        if (isset($entity['entity_type'])) {
          $field_info['entity_type'] = $entity['entity_type'];
        }
      }
    }

    return $this->getMockContentEntityInterface($field_definitions, $fields, $field_info);
  }

  /**
   * Create base field definition.
   *
   * @param array $params
   *   Parameter array defined in tests.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   mock FieldDefinition.
   */
  private function makeMockFieldDefinition(array $params) {
    if (isset($params['storage'])) {
      $storage = $params['storage'];
    }
    else {
      if (isset($params['is_base_field'])) {
        $is_base_field = $params['is_base_field'];
      }
      else {
        $is_base_field = FALSE;
      }

      $storage = $this->getMockFieldStorageDefinitionInterface($is_base_field);
    }

    return $this->getMockFieldDefinitionInterface($storage);
  }

  /**
   * Create a mock Field given test parameters.
   *
   * @param array $params
   *   Parameter array defined in tests.
   *
   * @return mixed
   *   mock Field.
   */
  private function makeMockField(array $params) {
    switch ($params['type']) {
      case 'entity_reference':
        $referenced_entities = [];

        if (isset($params['referenced_entities'])) {
          foreach ($params['referenced_entities'] as $referenced_entity) {
            $referenced_entities[] = $this->makeMockContentEntityInterface($referenced_entity);
          }
        }

        return $this->getMockEntityReferenceRevisionsFieldItemList($params['name'], $referenced_entities);

      break;

      case 'list':
        $links = [];

        if (isset($params['list_items'])) {
          foreach ($params['list_items'] as $type => $items) {
            foreach ($items as $link) {
              $links[] = $this->getMockLinkItem($link);
            }
          }
        }

        return $this->getMockFieldItemList($params['name'], $links);

      break;

      case 'entity':
        if (isset($params['entities'])) {
          $entity_info = $params['entities'];
          $referenced_entities[] = $this->makeMockContentEntityInterface($entity_info);
        }

        return $this->getMockEntityReferenceFieldItemList($params['name'], $referenced_entities);

      break;

      default:
        return $this->getMockFieldItemList($params['name'], []);

      break;

    }
  }

}
