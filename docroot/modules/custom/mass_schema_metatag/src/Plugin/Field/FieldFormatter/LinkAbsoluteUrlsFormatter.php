<?php

namespace Drupal\mass_schema_metatag\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Outputs link field items as a JSON array of absolute URLs.
 *
 * @FieldFormatter(
 *   id = "mass_link_absolute_urls",
 *   label = @Translation("Absolute URLs (JSON array)"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
final class LinkAbsoluteUrlsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'absolute' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Absolute URLs'),
      '#default_value' => (bool) $this->getSetting('absolute'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Outputs a JSON array of URLs for link field items.'),
      (bool) $this->getSetting('absolute')
        ? $this->t('Absolute URLs: yes')
        : $this->t('Absolute URLs: no'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $bubbleable = new BubbleableMetadata();
    $absolute = (bool) $this->getSetting('absolute');

    $urls = [];
    foreach ($items as $item) {
      $uri = (string) ($item->uri ?? '');
      if ($uri === '') {
        continue;
      }

      try {
        $url = Url::fromUri($uri, ['absolute' => $absolute])->toString();
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
