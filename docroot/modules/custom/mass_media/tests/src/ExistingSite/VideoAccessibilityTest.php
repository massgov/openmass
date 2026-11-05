<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mayflower\Prepare\Atoms;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests optional accessibility content on the existing Video media type.
 */
class VideoAccessibilityTest extends MassExistingSiteBase {

  /**
   * Creates a managed WebVTT fixture with normal file cleanup.
   */
  private function createTrack(string $fixture): File {
    $uri = 'public://' . $this->randomMachineName() . '.vtt';
    \Drupal::service('file_system')->copy(__DIR__ . '/../../fixtures/' . $fixture, $uri);
    $file = File::create(['uri' => $uri, 'filemime' => 'text/vtt']);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);
    return $file;
  }

  /**
   * Uploaded language tracks and alternate video URLs reach the actual player.
   */
  public function testTracksAndAlternateVideos(): void {
    $english = $this->createTrack('captions-en.vtt');
    $spanish = $this->createTrack('captions-es.vtt');
    $chapters = $this->createTrack('chapters-en.vtt');
    $descriptions = $this->createTrack('descriptions-en.vtt');
    $spanish_descriptions = $this->createTrack('descriptions-es.vtt');
    $media = Media::create([
      'bundle' => 'media_video',
      'name' => 'Accessible video',
      'field_media_video_embed_field' => 'https://youtu.be/dhwpLACAls8',
      'field_video_captions' => [
        ['target_id' => $english->id(), 'track_language' => 'en'],
        ['target_id' => $spanish->id(), 'track_language' => 'es', 'description' => 'Español'],
      ],
      'field_video_chapters' => ['target_id' => $chapters->id(), 'track_language' => 'en'],
      'field_video_descriptions' => [
        ['target_id' => $descriptions->id(), 'track_language' => 'en'],
        ['target_id' => $spanish_descriptions->id(), 'track_language' => 'es', 'description' => 'Descripciones en español'],
      ],
      'field_video_audio_description' => ['uri' => 'https://youtu.be/Ck2TdVMhWcU'],
      'field_video_sign_language' => ['uri' => 'https://youtu.be/bf6214F7srY'],
    ]);
    $player = Atoms::prepareVideo($media, [])['player'];
    $attributes = $player['#attributes']->toArray();
    $this->assertSame('Ck2TdVMhWcU', $attributes['data-youtube-desc-id']);
    $this->assertSame('bf6214F7srY', $attributes['data-youtube-sign-src']);
    $this->assertSame('true', $attributes['data-include-transcript']);
    $tracks = array_map(fn($track) => $track->toArray(), $player['#tracks']);
    $this->assertCount(5, $tracks);
    $this->assertSame('English', $tracks[0]['label']);
    $this->assertSame('Español', $tracks[1]['label']);
    $this->assertSame('es', $tracks[1]['srclang']);
    $this->assertTrue($tracks[0]['default']);
    $this->assertFalse($tracks[1]['default']);
    $this->assertSame('chapters', $tracks[2]['kind']);
    $this->assertSame('descriptions', $tracks[3]['kind']);
    $this->assertSame('en', $tracks[3]['srclang']);
    $this->assertTrue($tracks[3]['default']);
    $this->assertSame('descriptions', $tracks[4]['kind']);
    $this->assertSame('es', $tracks[4]['srclang']);
    $this->assertFalse($tracks[4]['default']);
    $this->assertContains('file:' . $descriptions->id(), $player['#cache']['tags']);
    $this->assertContains('file:' . $spanish->id(), $player['#cache']['tags']);
    $html = (string) \Drupal::service('renderer')->renderRoot($player);
    $this->assertSame(5, substr_count($html, '<track '));
    $this->assertStringContainsString('kind="descriptions"', $html);
    $this->assertStringContainsString('srclang="es"', $html);

    $media->set('field_video_transcript_enabled', FALSE);
    $player = Atoms::prepareVideo($media, [])['player'];
    $this->assertSame('false', $player['#attributes']->toArray()['data-include-transcript']);
    $media->set('field_video_transcript_enabled', []);
    $player = Atoms::prepareVideo($media, [])['player'];
    $this->assertSame('true', $player['#attributes']->toArray()['data-include-transcript']);
  }

  /**
   * Track metadata survives a media save and retains normal managed file usage.
   */
  public function testTrackStorageAndFileUsage(): void {
    $file = $this->createTrack('captions-es.vtt');
    $media = Media::create([
      'bundle' => 'media_video',
      'name' => 'Track storage fixture',
      // Avoid provider metadata requests in this storage-only test.
      'field_media_video_embed_field' => 'https://example.com/video',
      'field_video_captions' => ['target_id' => $file->id(), 'track_language' => 'es', 'description' => 'Español'],
      'field_video_descriptions' => ['target_id' => $file->id(), 'track_language' => 'es', 'description' => 'Descripciones'],
    ]);
    $media->save();
    $this->markEntityForCleanup($media);
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $storage->resetCache([$media->id()]);
    $reloaded = $storage->load($media->id());
    $this->assertSame('es', $reloaded->get('field_video_captions')->track_language);
    $this->assertSame('Español', $reloaded->get('field_video_captions')->description);
    $this->assertSame((string) $file->id(), (string) $reloaded->get('field_video_captions')->target_id);
    $this->assertSame('es', $reloaded->get('field_video_descriptions')->track_language);
    $this->assertSame('Descripciones', $reloaded->get('field_video_descriptions')->description);
    $usage = \Drupal::service('file.usage')->listUsage($file);
    $this->assertNotEmpty($usage['file']['media'][$media->id()]);
  }

  /**
   * Provider checks reject alternate videos that the player cannot use.
   */
  #[DataProvider('alternateVideos')]
  public function testAlternateVideoValidation(string $main, string $description, string $sign, array $expected_paths): void {
    $media = Media::create([
      'bundle' => 'media_video',
      'name' => 'Provider validation',
      'field_media_video_embed_field' => $main,
      'field_video_audio_description' => $description ? ['uri' => $description] : [],
      'field_video_sign_language' => $sign ? ['uri' => $sign] : [],
    ]);
    $paths = [];
    foreach ($media->validate() as $violation) {
      if (str_starts_with($violation->getPropertyPath(), 'field_video_audio_description') || str_starts_with($violation->getPropertyPath(), 'field_video_sign_language')) {
        $paths[] = $violation->getPropertyPath();
      }
    }
    $this->assertSame($expected_paths, $paths);
  }

  /**
   * Supplies allowed and incompatible provider combinations.
   */
  public static function alternateVideos(): array {
    $youtube = 'https://youtu.be/dhwpLACAls8';
    $vimeo = 'https://vimeo.com/286221808/a31d148dc9';
    return [
      'optional empty fields' => [$youtube, '', '', []],
      'youtube alternatives' => [$youtube, $youtube, $youtube, []],
      'vimeo description' => [$vimeo, $vimeo, '', []],
      'wrong description provider' => [$youtube, $vimeo, '', ['field_video_audio_description.0.uri']],
      'unsupported description' => [$youtube, 'https://example.com/video', '', ['field_video_audio_description.0.uri']],
      'vimeo sign source' => [$youtube, '', $vimeo, ['field_video_sign_language.0.uri']],
      'vimeo main with sign source' => [$vimeo, '', $youtube, ['field_video_sign_language.0.uri']],
    ];
  }

  /**
   * The editor form groups the upload, language, label, and alternate inputs.
   */
  public function testAccessibilityEditorForm(): void {
    $file = $this->createTrack('captions-es.vtt');
    $media = Media::create([
      'bundle' => 'media_video',
      'name' => 'Editor form video',
      'field_media_video_embed_field' => 'https://youtu.be/dhwpLACAls8',
      'field_video_captions' => ['target_id' => $file->id(), 'track_language' => 'es', 'description' => 'Español'],
      'field_video_descriptions' => ['target_id' => $file->id(), 'track_language' => 'es', 'description' => 'Descripciones'],
    ]);
    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo(User::load(1));
    try {
      $form = \Drupal::service('entity.form_builder')->getForm($media);
      $this->assertSame('video_accessibility', $form['field_video_captions']['#group']);
      $this->assertSame('video_accessibility', $form['field_video_descriptions']['#group']);
      $this->assertSame('es', $form['field_video_descriptions']['widget'][0]['track_language']['#value']);
      $this->assertSame('es', $form['field_video_captions']['widget'][0]['track_language']['#value']);
      $this->assertSame('Español', $form['field_video_captions']['widget'][0]['description']['#value']);
      $html = (string) \Drupal::service('renderer')->renderRoot($form);
      $this->assertStringContainsString('Video accessibility', $html);
      $this->assertStringContainsString('Track language', $html);
      $this->assertStringContainsString('Audio descriptions (.vtt)', $html);
      $this->assertStringContainsString('Synchronized sign-language video URL', $html);
      $this->assertStringContainsString('Use the same provider as the main video', $html);
      $this->assertStringContainsString('Both videos must be on YouTube', $html);
    }
    finally {
      $switcher->switchBack();
    }
  }

}
