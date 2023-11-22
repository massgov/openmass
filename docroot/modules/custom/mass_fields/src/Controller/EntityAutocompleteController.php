<?php

namespace Drupal\mass_fields\Controller;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\mass_fields\EntityAutocompleteMatcher;
use Drupal\system\Controller\EntityAutocompleteController as DefaultEntityAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide an updated controller for entity autocomplete.
 *
 * @package Drupal\mass_fields\Controller
 */
class EntityAutocompleteController extends DefaultEntityAutocompleteController {

  /**
   * The autocomplete matcher for entity references.
   *
   * @var \Drupal\mass_fields\EntityAutocompleteMatcher
   */
  protected $matcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityAutocompleteMatcher $matcher, KeyValueStoreInterface $key_value) {
    $this->matcher = $matcher;
    $this->keyValue = $key_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_fields.autocomplete_matcher'),
      $container->get('keyvalue')->get('entity_autocomplete')
    );
  }

}
