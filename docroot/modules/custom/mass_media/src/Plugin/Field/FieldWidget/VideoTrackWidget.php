<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * Adds language and label inputs to Drupal's managed file upload widget.
 */
#[FieldWidget(
  id: 'mass_media_video_track',
  label: new TranslatableMarkup('Video text track upload'),
  field_types: ['mass_media_video_track'],
)]
final class VideoTrackWidget extends FileWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#default_value']['track_language'] = $items[$delta]->track_language ?: $items->getEntity()->language()->getId();
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    if (!empty($element['#value']['fids'])) {
      $languages = [];
      foreach (LanguageManager::getStandardLanguageList() as $code => $names) {
        $languages[$code] = $names[0];
      }
      asort($languages);
      $element['track_language'] = [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('Track language'),
        '#options' => $languages,
        '#default_value' => $element['#value']['track_language'] ?? $element['#default_value']['track_language'],
        '#required' => TRUE,
        '#weight' => 5,
      ];
      $element['description']['#title'] = new TranslatableMarkup('Track label');
      $element['description']['#description'] = new TranslatableMarkup('Optional name shown in the player. Defaults to the selected language.');
      $element['description']['#weight'] = 6;
    }
    return $element;
  }

}
