<?php

namespace Drupal\mass_schema_metatag\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Outputs link field items as a JSON array of {name,url} objects.
 *
 * @FieldFormatter(
 *   id = "mass_link_name_url_objects",
 *   label = @Translation("Link objects (JSON array: {name,url})"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
final class LinkNameUrlObjectsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'absolute' => TRUE,
      'name_source' => 'title',
      'skip_empty_name' => FALSE,
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

    $elements['name_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Name source'),
      '#options' => [
        'title' => $this->t('Use link title (fallback to URL)'),
        'url' => $this->t('Always use URL as name'),
      ],
      '#default_value' => (string) $this->getSetting('name_source'),
    ];

    $elements['skip_empty_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip items with empty title'),
      '#default_value' => (bool) $this->getSetting('skip_empty_name'),
      '#states' => [
        'visible' => [
          ':input[name="fields[settings_edit_form][settings][name_source]"]' => ['value' => 'title'],
        ],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Outputs JSON array of objects: {name,url}.'),
      (bool) $this->getSetting('absolute')
        ? $this->t('Absolute URLs: yes')
        : $this->t('Absolute URLs: no'),
      $this->t('Name source: @s', ['@s' => (string) $this->getSetting('name_source')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $bubbleable = new BubbleableMetadata();
    $absolute = (bool) $this->getSetting('absolute');
    $name_source = (string) $this->getSetting('name_source');
    $skip_empty_name = (bool) $this->getSetting('skip_empty_name');

    $out = [];

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

      if ($url === '') {
        continue;
      }

      $title = '';
      if (isset($item->title) && is_string($item->title)) {
        $title = trim($item->title);
      }

      if ($name_source === 'url') {
        $name = $url;
      }
      else {
        // Title mode: use title, fallback to URL.
        if ($title === '' && $skip_empty_name) {
          continue;
        }
        $name = $title !== '' ? $title : $url;
      }

      $out[] = [
        'name' => $name,
        'url' => $url,
      ];
    }

    $json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $build = [
      [
        '#plain_text' => $json,
      ],
    ];

    $bubbleable->applyTo($build);
    return $build;
  }

}
