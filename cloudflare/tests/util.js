
import {URL} from 'url'
import {
  isEditUrl,
  isLegacyUrl,
  isFileRedirect
} from '../src/util';
import makeServiceWorkerMock from "service-worker-mock";

const mockContext = makeServiceWorkerMock();

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
});

describe('isFileRedirect', function() {
  const tests = [
    ['/files/foo', true],
    ['/foo/bar', false],
    ['/foo/files/bar', false],
    ['https://www.mass.gov/files/foo', true],
  ];

  beforeEach(() => {
    Object.assign(global, makeServiceWorkerMock());
    jest.resetModules();
  });

  tests.forEach(function(test) {
    it(`${test[1] ? 'Should' : 'Should not'} treat ${test[0]} as a file redirect`, function() {
      const response = new mockContext.Response('', {
        status: 301,
        headers: {
          'Location': test[0]
        }
      })
      expect(isFileRedirect(response)).toEqual(test[1]);
    })
  })
})
