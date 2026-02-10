<?php

namespace Drupal\mass_schema_metatag\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Outputs referenced entities as a JSON array of absolute URLs.
 *
 * @FieldFormatter(
 *   id = "mass_entity_reference_absolute_urls",
 *   label = @Translation("Absolute URLs (JSON array)"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions"
 *   }
 * )
 */
final class EntityReferenceAbsoluteUrlsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
        'link_template' => 'canonical',
        'absolute' => TRUE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['link_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link template'),
      '#default_value' => $this->getSetting('link_template'),
      '#description' => $this->t('Entity link template to use (e.g. canonical).'),
      '#required' => TRUE,
    ];

    $elements['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Absolute URLs'),
      '#default_value' => $this->getSetting('absolute'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Outputs a JSON array of absolute URLs'),
      $this->t('Template: @t', ['@t' => $this->getSetting('link_template')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $bubbleable = new BubbleableMetadata();
    $urls = [];

    $template = $this->getSetting('link_template');
    $absolute = (bool) $this->getSetting('absolute');

    foreach ($items as $item) {
      $entity = $item->entity ?? NULL;
      if (!$entity) {
        continue;
      }

      try {
        if ($template && $entity->hasLinkTemplate($template)) {
          $url = $entity->toUrl($template, ['absolute' => $absolute])->toString();
        }
        else {
          $url_obj = $entity->toUrl();
          if ($absolute) {
            $url_obj->setAbsolute();
          }
          $url = $url_obj->toString();
        }
      }
      catch (\Throwable $e) {
        continue;
      }

      if ($url !== '') {
        $urls[] = $url;
      }
    }

    $json = json_encode($urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $build = [
      [
        '#plain_text' => $json,
      ],
    ];

    $bubbleable->applyTo($build);
    return $build;
  }

}
