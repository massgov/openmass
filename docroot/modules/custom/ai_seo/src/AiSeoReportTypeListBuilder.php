<?php

namespace Drupal\ai_seo;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a listing of AI SEO Report Type entities.
 */
class AiSeoReportTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['description'] = $this->t('Description');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ai_seo\Entity\AiSeoReportType $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['description'] = $entity->getDescription();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if ($entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => $entity->toUrl('edit-form'),
      ];
    }

    if ($entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $entity->toUrl('delete-form'),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Add a link to create a new report type.
    if ($this->entityType->hasLinkTemplate('add-form')) {
      $add_url = Url::fromRoute('entity.ai_seo_report_type.add_form');
      $build['table']['#empty'] = $this->t('No AI SEO report types available. <a href=":link">Add a new report type</a>.', [
        ':link' => $add_url->toString(),
      ]);
      
      // Add the "Add new" link at the top.
      $build['add_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Add AI SEO Report Type'),
        '#url' => $add_url,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
        '#weight' => -10,
      ];
    }

    return $build;
  }

}