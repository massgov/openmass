/**
 * @file
 * Initializes Able Player for both initial and AJAX-rendered media.
 *
 * @param {object} Drupal
 *   Drupal behavior registry.
 * @param {Function} once
 *   Drupal once utility.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Enables Vimeo's rate API before Able Player builds its speed controls.
   */
  class MassMediaAblePlayer extends window.AblePlayer {
    async initVimeoPlayer() {
      if (this.hasPlaylist) {
        return super.initVimeoPlayer();
      }

      const container = document.createElement('div');
      container.id = `${this.mediaId}_vimeo`;
      this.$mediaContainer.prepend(container);
      const source = this.vimeoDescId && this.prefDesc ? this.vimeoDescId : this.vimeoId;
      const options = {controls: false, speed: true};
      options[this.vimeoUrlHasParams ? 'url' : 'id'] = source;
      if (this.playerWidth) {
        options.width = this.playerWidth;
      }
      this.activeVimeoId = source;
      this.vimeoPlayer = new window.Vimeo.Player(container, options);
      await this.vimeoPlayer.ready();

      const iframe = container.querySelector('iframe');
      iframe.setAttribute('tabindex', '-1');
      iframe.setAttribute('aria-hidden', 'true');
      try {
        const [width, height] = await Promise.all([
          this.vimeoPlayer.getVideoWidth(),
          this.vimeoPlayer.getVideoHeight()
        ]);
        if (width && height) {
          this.resizePlayer(width, height);
        }
      }
      catch (error) {
        // Keep the configured dimensions if Vimeo cannot report the video size.
      }

      this.$media.remove();
      this.vimeoPlaybackRate = 1;
      try {
        await this.vimeoPlayer.setPlaybackRate(this.vimeoPlaybackRate);
        this.vimeoSupportsPlaybackRateChange = true;
      }
      catch (error) {
        this.vimeoSupportsPlaybackRateChange = false;
      }
    }
  }

  Drupal.behaviors.massMediaAblePlayer = {
    attach(context) {
      once('mass-media-ableplayer', '[data-mass-able-player]', context).forEach((video) => {
        new MassMediaAblePlayer(video);
      });
    }
  };
})(Drupal, once);
