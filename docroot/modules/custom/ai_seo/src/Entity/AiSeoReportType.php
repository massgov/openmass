<?php

namespace Drupal\ai_seo\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the AI SEO Report Type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "ai_seo_report_type",
 *   label = @Translation("AI SEO Report Type"),
 *   label_collection = @Translation("AI SEO Report Types"),
 *   label_singular = @Translation("report type"),
 *   label_plural = @Translation("report types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count report type",
 *     plural = "@count report types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_seo\AiSeoReportTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_seo\Form\AiSeoReportTypeForm",
 *       "edit" = "Drupal\ai_seo\Form\AiSeoReportTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "report_type",
 *   admin_permission = "administer ai seo report types",
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
 *     "collection" = "/admin/config/ai-seo/report-types",
 *     "add-form" = "/admin/config/ai-seo/report-types/add",
 *     "edit-form" = "/admin/config/ai-seo/report-types/{ai_seo_report_type}/edit",
 *     "delete-form" = "/admin/config/ai-seo/report-types/{ai_seo_report_type}/delete"
 *   }
 * )
 */
class AiSeoReportType extends ConfigEntityBase {

  /**
   * The AI SEO report type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The AI SEO report type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The AI SEO report type description.
   *
   * @var string
   */
  protected $description;

  /**
   * The AI SEO report type prompt.
   *
   * @var string
   */
  protected $prompt;

  /**
   * The AI SEO report type status.
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
