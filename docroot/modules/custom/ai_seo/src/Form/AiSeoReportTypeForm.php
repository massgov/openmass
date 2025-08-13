<?php

namespace Drupal\ai_seo\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AiSeoReportTypeForm.
 *
 * @package Drupal\ai_seo\Form
 */
class AiSeoReportTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ai_seo\Entity\AiSeoReportType $ai_seo_report_type */
    $ai_seo_report_type = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $ai_seo_report_type->label(),
      '#description' => $this->t('Label for the AI SEO Report Type.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $ai_seo_report_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ai_seo\Entity\AiSeoReportType::load',
      ],
      '#disabled' => !$ai_seo_report_type->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $ai_seo_report_type->getDescription(),
      '#description' => $this->t('A brief description of what this report type analyzes.'),
      '#rows' => 3,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI Prompt'),
      '#default_value' => $ai_seo_report_type->getPrompt(),
      '#description' => $this->t('The prompt that will be used for AI analysis.'),
      '#required' => TRUE,
      '#rows' => 10,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $ai_seo_report_type->status(),
      '#description' => $this->t('Check to enable this report type.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\ai_seo\Entity\AiSeoReportType $ai_seo_report_type */
    $ai_seo_report_type = $this->entity;

    $ai_seo_report_type->setDescription($form_state->getValue('description'));
    $ai_seo_report_type->setPrompt($form_state->getValue('prompt'));

    $status = $ai_seo_report_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label AI SEO Report Type.', [
          '%label' => $ai_seo_report_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label AI SEO Report Type.', [
          '%label' => $ai_seo_report_type->label(),
        ]));
    }

    $form_state->setRedirectUrl($ai_seo_report_type->toUrl('collection'));
  }

}
