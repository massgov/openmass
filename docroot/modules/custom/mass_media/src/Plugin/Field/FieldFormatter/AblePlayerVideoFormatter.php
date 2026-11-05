<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\mass_media\RemoteVideoUrl;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses Able Player with the existing Video Embed Field media source.
 */
#[FieldFormatter(
  id: 'mass_media_ableplayer',
  label: new TranslatableMarkup('Able Player Remote Video'),
  field_types: ['video_embed_field'],
)]
final class AblePlayerVideoFormatter extends FormatterBase {

  /**
   * Generates public or private URLs for the managed text track files.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $url = trim((string) $item->value);
      $source = RemoteVideoUrl::parse($url);
      if (!$source) {
        continue;
      }

      $attributes = [
        'id' => Html::getUniqueId('ableplayer-video'),
        // Initialize through a Drupal behavior, including AJAX additions.
        'data-mass-able-player' => '',
        'playsinline' => TRUE,
        'preload' => 'none',
        'width' => 854,
        'height' => 480,
        'aria-label' => $items->getEntity()->label(),
      ];
      $libraries = ['mass_media/ableplayer'];
      $attributes['data-' . $source['provider'] . '-id'] = $source['id'];
      if ($source['provider'] === 'vimeo') {
        $libraries[] = 'ableplayer/ableplayer-vimeo';
      }

      $media = $items->getEntity();
      $cache = CacheableMetadata::createFromObject($media);
      foreach (['field_video_audio_description' => 'desc-id', 'field_video_sign_language' => 'sign-src'] as $field => $attribute) {
        if (!$media->hasField($field)) {
          continue;
        }
        $access = $media->get($field)->access('view', NULL, TRUE);
        $cache->addCacheableDependency($access);
        $alternate = RemoteVideoUrl::parse((string) $media->get($field)->uri);
        if ($access->isAllowed() && $alternate && $alternate['provider'] === $source['provider'] && ($attribute !== 'sign-src' || $source['provider'] === 'youtube')) {
          $attributes['data-' . $source['provider'] . '-' . $attribute] = $alternate['id'];
        }
      }

      $tracks = [];
      $languages = LanguageManager::getStandardLanguageList();
      foreach (['field_video_captions' => 'captions', 'field_video_chapters' => 'chapters'] as $field => $kind) {
        if (!$media->hasField($field)) {
          continue;
        }
        $access = $media->get($field)->access('view', NULL, TRUE);
        $cache->addCacheableDependency($access);
        if (!$access->isAllowed()) {
          continue;
        }
        $default_set = FALSE;
        foreach ($media->get($field) as $track) {
          if (!$file = $track->entity) {
            continue;
          }
          $cache->addCacheableDependency($file);
          $file_access = $file->access('download', NULL, TRUE);
          $cache->addCacheableDependency($file_access);
          if (!$file_access->isAllowed()) {
            continue;
          }
          $language = $track->track_language ?: $media->language()->getId();
          $tracks[] = new Attribute([
            'kind' => $kind,
            'src' => $this->fileUrlGenerator->generateString($file->getFileUri()),
            'srclang' => $language,
            'label' => $track->description ?: ($languages[$language][0] ?? $language),
            'default' => !$default_set,
          ]);
          $default_set = TRUE;
        }
      }
      if ($media->hasField('field_video_transcript_enabled')) {
        $setting = $media->get('field_video_transcript_enabled');
        $access = $setting->access('view', NULL, TRUE);
        $cache->addCacheableDependency($access);
        $attributes['data-include-transcript'] = $access->isAllowed() && ($setting->isEmpty() || $setting->value) ? 'true' : 'false';
      }

      $elements[$delta] = [
        '#theme' => 'mass_media_ableplayer',
        '#attributes' => new Attribute($attributes),
        '#url' => $url,
        '#tracks' => $tracks,
        // Mayflower still needs an embed URL for client-rendered components.
        '#mass_video_embed' => ['src' => $source['src'], 'width' => 854, 'height' => 480],
        '#attached' => ['library' => $libraries],
      ];
      $cache->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
