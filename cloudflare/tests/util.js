
import {URL} from 'url'
import {
  isEditUrl,
  isLegacyUrl,
  pathIsBanned,
  replaceErrorPages
} from '../src/util';

describe('isLegacyUrl', function() {
  const tests = [
    ['https://www.mass.gov/anf', true],
    ['https://www.mass.gov/abc', false],
    ['https://www.mass.gov/alerts', false],
    ['https://www.mass.gov/alerts/foo', false],
    ['https://www.mass.gov/alert', true],
    ['https://www.mass.gov/alert/foo', true],
  ]

  tests.forEach(function(test) {
    it(`Should treat ${test[0]} as a ${test[1] ? 'legacy' : 'drupal'} url`, function() {
      expect(isLegacyUrl(new URL(test[0]))).toEqual(test[1]);
    });
  });
});

describe('isEditUrl', function() {
  const tests = [
    ['https://edit.mass.gov/admin', true],
    ['https://edit.stage.mass.gov/admin', true],
    ['https://editcf.digital.mass.gov/admin', true],
    ['https://www.mass.gov/abc', false],
    ['https://pilot.mass.gov/abc', false],
  ]

  tests.forEach(function(test) {
    it(`Should treat ${test[0]} as a ${test[1] ? 'legacy' : 'drupal'} url`, function() {
      expect(isEditUrl(new URL(test[0]))).toEqual(test[1]);
    });
  });
})
