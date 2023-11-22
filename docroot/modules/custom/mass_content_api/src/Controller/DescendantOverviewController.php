<?php

namespace Drupal\mass_content_api\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\mass_content_api\DescendantManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DescendantOverviewController.
 *
 * @package Drupal\mass_content_api\Controller
 */
class DescendantOverviewController extends ControllerBase {

  /**
   * EntityFieldManager services object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * EntityTypeManager services object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The descendant manager service.
   *
   * @var \Drupal\mass_content_api\DescendantManager
   */
  protected $descendantManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManager $entityFieldManager, EntityTypeManager $entityTypeManager, DescendantManagerInterface $descendantManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->descendantManager = $descendantManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('descendant_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $entity_type = 'node';
    $form = [];
    $form['caption'] = [
      '#type' => 'item',
      '#title' => $this->t('This page gives an overview of all fields that are configured to show a specific parent or child relationship between nodes, and/or that create a link between nodes, meaning changes to one node may impact the other. Expand each content type to see the list of fields and their configuration details. To make changes, you can click a field name to go directly to the configuration for that field.'),
    ];
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $bundle => $settings) {
      $node_settings = $settings->getThirdPartySettings('mass_content_api');
      $form['bundles'][$entity_type][$bundle]['settings'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Field Name'),
          $this->t('Dependency Status'),
          $this->t('Parent/Child Field'),
          $this->t('Traversal Levels'),
        ],
      ];
      $node_fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
      foreach ($node_settings as $status => $mass_settings) {
        $status = str_replace('dependency_status_', '', $status);
        $field_names = [];
        if (!empty($mass_settings)) {
          foreach ($mass_settings as $setting) {
            $field_names[] = explode('>', $setting);
          }
        }
        foreach ($field_names as $name_list) {
          $primary_field = array_shift($name_list);
          $level_count = count($name_list);
          if (array_key_exists($primary_field, $node_fields)) {
            $field_settings = $node_fields[$primary_field]->getSettings();
            if (isset($field_settings['handler_settings']['target_bundles'])) {
              $para_targets = $field_settings['handler_settings']['target_bundles'];
              foreach ($para_targets as $para_bundle) {
                $para_fields[] = $this->entityFieldManager->getFieldDefinitions('paragraph', $para_bundle);
              }
            }
            $form['bundles'][$entity_type][$bundle]['settings'][$primary_field]['name'][] = [
              '#type' => 'item',
              '#title' => $node_fields[$primary_field]->getLabel() . ' (' . $primary_field . ')',
            ];
            $form['bundles'][$entity_type][$bundle]['settings'][$primary_field]['status'][] = [
              '#type' => 'item',
              '#title' => $status == 'linking_page' ? $this->t('Linking Page') : ucwords($status),
            ];
            if ($name_list) {
              foreach ($name_list as $child_field) {
                foreach ($para_fields as $search_fields) {
                  if (array_key_exists($child_field, $search_fields)) {
                    $form['bundles'][$entity_type][$bundle]['settings'][$primary_field]['field'][] = [
                      '#type' => 'item',
                      '#title' => $search_fields[$child_field]->getLabel() . ' (' . $child_field . ')',
                    ];
                  }
                }
                if ($child_field === '*') {
                  $form['bundles'][$entity_type][$bundle]['settings'][$primary_field]['field'][] = [
                    '#type' => 'item',
                    '#title' => $this->t('No specified field (this field will include all link/entity reference fields from referenced Paragraphs)'),
                  ];
                }
              }
            }
            else {
              $form['bundles'][$entity_type][$bundle]['settings'][$primary_field]['field'][] = [
                '#type' => 'item',
                '#title' => $this->t('None; field is of type %type.', ['%type' => $node_fields[$primary_field]->getType()]),
              ];
            }
            $form['bundles'][$entity_type][$bundle]['settings'][$primary_field]['level'][] = [
              '#type' => 'item',
              '#title' => $this->t('Linking page configuration traverses %count levels', ['%count' => $level_count]),
            ];
          }
        }
      }
    }
    foreach (Element::children($form['bundles'][$entity_type]) as $bundle) {
      $form['bundles'][$entity_type] += [
        '#type' => 'details',
        '#open' => TRUE,
      ];
      foreach (Element::children($form['bundles'][$entity_type][$bundle]) as $form_bundle) {
        $form['bundles'][$entity_type][$bundle] += [
          '#type' => 'details',
          '#title' => Link::fromTextAndUrl($bundle, Url::fromRoute('entity.node_type.edit_form', ['node_type' => $bundle], ['fragment' => 'edit-dependency-settings'])),
          '#open' => FALSE,
        ];
      }
    }

    return $form;
  }

}
