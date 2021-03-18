<?php

namespace Drupal\mass_utility\Plugin\CKEditorPlugin;

use Drupal\media_entity_download\Plugin\CKEditorPlugin\MediaEntityDownload;
use  Drupal\ckeditor\CKEditorPluginButtonsInterface;

/**
 * Overrides the "mediaentitydownload" plugin.
 */
class MassMediaEntityDownload extends MediaEntityDownload implements CKEditorPluginButtonsInterface{

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    // Because of composer patch binary issues we're adding the icon here.
    // This method is only for the button organizaiton admin screen.
    $path = drupal_get_path('module', 'mass_utility') . '/js/massmediaentitydownload';
    return [
      'MediaEntityDownload' => [
        'label' => $this->t('Download Link'),
        'image' => $path . '/icons/mediaentitydownload.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'mass_utility') . '/js/massmediaentitydownload/plugin.js';
  }

}
