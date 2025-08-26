<?php

namespace Drupal\ai_content_advisor\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the AI Content Advisor Report Type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "ai_content_advisor_report_type",
 *   label = @Translation("AI Content Advisor Report Type"),
 *   label_collection = @Translation("AI Content Advisor Report Types"),
 *   label_singular = @Translation("report type"),
 *   label_plural = @Translation("report types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count report type",
 *     plural = "@count report types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_content_advisor\AiContentAdvisorReportTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_content_advisor\Form\AiContentAdvisorReportTypeForm",
 *       "edit" = "Drupal\ai_content_advisor\Form\AiContentAdvisorReportTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "report_type",
 *   admin_permission = "administer ai content advisor report types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "prompt",
 *     "status"
 *   },
 *   links = {
 *     "collection" = "/admin/config/ai-content-advisor/report-types",
 *     "add-form" = "/admin/config/ai-content-advisor/report-types/add",
 *     "edit-form" = "/admin/config/ai-content-advisor/report-types/{ai_content_advisor_report_type}/edit",
 *     "delete-form" = "/admin/config/ai-content-advisor/report-types/{ai_content_advisor_report_type}/delete"
 *   }
 * )
 */
class AiContentAdvisorReportType extends ConfigEntityBase {

  /**
   * The AI Content Advisor report type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The AI Content Advisor report type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The AI Content Advisor report type description.
   *
   * @var string
   */
  protected $description;

  /**
   * The AI Content Advisor report type prompt.
   *
   * @var string
   */
  protected $prompt;

  /**
   * The AI Content Advisor report type status.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrompt() {
    return $this->prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrompt($prompt) {
    $this->prompt = $prompt;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure machine name is valid.
    $this->id = preg_replace('/[^a-z0-9_]/', '_', strtolower($this->id));
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityBase|\Drupal\Core\Config\Entity\ConfigEntityInterface $a, ConfigEntityBase|\Drupal\Core\Config\Entity\ConfigEntityInterface $b) {
    // Sort by label.
    return strnatcasecmp($a->label(), $b->label());
  }

}
