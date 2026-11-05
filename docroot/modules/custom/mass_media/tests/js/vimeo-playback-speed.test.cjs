const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const behavior = fs.readFileSync(path.join(__dirname, '../../js/mass_media.ableplayer.js'), 'utf8');

function initialize({ dimensionsFail = false } = {}) {
  let resolveRate;
  let rejectRate;
  const rate = new Promise((resolve, reject) => { resolveRate = resolve; rejectRate = reject; });
  const instances = [];
  const sdkOptions = [];
  class BasePlayer {
    constructor() {
      this.mediaId = 'test-video';
      this.vimeoId = 'https://player.vimeo.com/video/123?h=private';
      this.vimeoUrlHasParams = true;
      this.playerWidth = 854;
      this.$mediaContainer = { prepend() {} };
      this.$media = { remove: () => { this.mediaRemoved = true; } };
      instances.push(this);
      this.ready = this.initVimeoPlayer().then(() => { this.controlsEnabled = this.vimeoSupportsPlaybackRateChange; });
    }
    resizePlayer(width, height) { this.dimensions = [width, height]; }
  }
  class VimeoPlayer {
    constructor(container, options) { sdkOptions.push(options); }
    ready() { return Promise.resolve(); }
    getVideoWidth() { return dimensionsFail ? Promise.reject(new Error('No dimensions')) : Promise.resolve(1920); }
    getVideoHeight() { return Promise.resolve(1080); }
    setPlaybackRate(value) { assert.equal(value, 1); return rate; }
  }
  const Drupal = { behaviors: {} };
  vm.runInNewContext(behavior, {
    Drupal,
    once: () => [{}],
    window: { AblePlayer: BasePlayer, Vimeo: { Player: VimeoPlayer } },
    document: { createElement: () => ({ querySelector: () => ({ setAttribute() {} }) }) }
  });
  Drupal.behaviors.massMediaAblePlayer.attach({});
  return { player: instances[0], sdkOptions, resolveRate, rejectRate };
}

test('Vimeo speed is explicitly enabled and controls wait for the rate API', async () => {
  const { player, sdkOptions, resolveRate } = initialize();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(sdkOptions[0].speed, true);
  assert.equal(sdkOptions[0].controls, false);
  assert.equal(sdkOptions[0].url, 'https://player.vimeo.com/video/123?h=private');
  assert.equal(player.controlsEnabled, undefined, 'Controls must not be built before the asynchronous capability check');
  resolveRate(1);
  await player.ready;
  assert.equal(player.controlsEnabled, true);
  assert.equal(player.mediaRemoved, true);
  assert.deepEqual(player.dimensions, [1920, 1080]);
});

test('A rejected rate API leaves playback usable without speed controls', async () => {
  const { player, rejectRate } = initialize();
  await new Promise(resolve => setImmediate(resolve));
  rejectRate(new Error('Setting playback rate is not enabled'));
  await player.ready;
  assert.equal(player.controlsEnabled, false);
  assert.equal(player.mediaRemoved, true);
});

test('Unavailable video dimensions do not prevent the player from initializing', async () => {
  const { player, resolveRate } = initialize({ dimensionsFail: true });
  await new Promise(resolve => setImmediate(resolve));
  resolveRate(1);
  await player.ready;
  assert.equal(player.controlsEnabled, true);
  assert.equal(player.dimensions, undefined);
});
