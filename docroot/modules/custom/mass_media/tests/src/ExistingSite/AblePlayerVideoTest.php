<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\mayflower\Prepare\Atoms;
use Drupal\media\Entity\Media;
use MassGov\Dtt\MassExistingSiteBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests Able Player with legacy Video media fields and Mayflower rendering.
 */
class AblePlayerVideoTest extends MassExistingSiteBase {

  /**
   * URL variants already supported by the existing Video type still render.
   */
  #[DataProvider('videoUrls')]
  public function testVideoRendering(string $url, string $attribute, string $id): void {
    $media = Media::create([
      'bundle' => 'media_video',
      'name' => 'Remote video test',
      'field_media_video_embed_field' => $url,
      'field_video_transcript' => ['uri' => 'https://example.com/transcript'],
    ]);
    $video = Atoms::prepareVideo($media, []);
    $this->assertNotEmpty($video['src']);
    $this->assertSame('Remote video test', $video['label']);
    $this->assertSame('mass_media_ableplayer', $video['player']['#theme']);
    $this->assertContains('mass_media/ableplayer', $video['player']['#attached']['library']);
    $this->assertSame($id, $video['player']['#attributes']->toArray()[$attribute]);

    $build = [
      '#type' => 'inline_template',
      '#template' => "{% include '@atoms/09-media/video.twig' %}",
      '#context' => ['video' => $video],
    ];
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $this->assertStringContainsString('data-mass-able-player', $html);
    $this->assertStringContainsString('Skip this video', $html);
    $this->assertStringContainsString('https://example.com/transcript', $html);
    $this->assertStringNotContainsString('<iframe', $html);
    $this->assertStringNotContainsString('js-ma-responsive-video', $html);
    $this->assertContains('mass_media/ableplayer', $build['#attached']['library']);
  }

  /**
   * The media transcript template can render the configured formatter.
   */
  public function testMediaTranscriptPageRendering(): void {
    $media = Media::create([
      'bundle' => 'media_video',
      'name' => 'Transcript page video',
      'field_media_video_embed_field' => 'https://youtu.be/dYM4eZq0R4Y',
    ]);
    $theme_manager = \Drupal::theme();
    $original_theme = $theme_manager->getActiveTheme();
    $theme_manager->setActiveTheme(\Drupal::service('theme.initialization')->getActiveThemeByName('mass_theme'));
    try {
      $build = \Drupal::entityTypeManager()->getViewBuilder('media')->view($media, 'full');
      $html = (string) \Drupal::service('renderer')->renderRoot($build);
      $this->assertStringContainsString('Transcript page video', $html);
      $this->assertStringContainsString('data-mass-able-player', $html);
      $this->assertStringNotContainsString('<iframe', $html);
      $this->assertContains('mass_media/ableplayer', $build['#attached']['library']);
    }
    finally {
      $theme_manager->setActiveTheme($original_theme);
    }
  }

  /**
   * Provides remote video URLs, including legacy and unlisted variants.
   */
  public static function videoUrls(): array {
    return [
      'youtube' => ['https://www.youtube.com/watch?v=dYM4eZq0R4Y', 'data-youtube-id', 'dYM4eZq0R4Y'],
      'short URL' => ['https://youtu.be/dYM4eZq0R4Y', 'data-youtube-id', 'dYM4eZq0R4Y'],
      'shorts' => ['https://www.youtube.com/shorts/dYM4eZq0R4Y', 'data-youtube-id', 'dYM4eZq0R4Y'],
      'legacy URL' => ['https://www.youtube.com/watch/dYM4eZq0R4Y', 'data-youtube-id', 'dYM4eZq0R4Y'],
      'whitespace' => [' https://www.youtube.com/watch?v=dYM4eZq0R4Y ', 'data-youtube-id', 'dYM4eZq0R4Y'],
      'vimeo' => ['https://vimeo.com/286221808', 'data-vimeo-id', '286221808'],
      'unlisted vimeo' => ['https://vimeo.com/286221808/a31d148dc9', 'data-vimeo-id', 'https://player.vimeo.com/video/286221808?h=a31d148dc9'],
      'embedded unlisted vimeo' => ['https://player.vimeo.com/video/286221808?h=a31d148dc9', 'data-vimeo-id', 'https://player.vimeo.com/video/286221808?h=a31d148dc9'],
    ];
  }

}
