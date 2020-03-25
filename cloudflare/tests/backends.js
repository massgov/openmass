
import {setupMocks, dummyResponse} from './mocks';
import {legacy, www, edit} from "../src/backends";


describe('Legacy backend', function() {
  beforeEach(() => setupMocks(global));

  test('Should route all requests to the Legacy backend, overriding the host', async function() {
    const handler = legacy()
    var request = new Request('https://www.mass.gov/foo');
    await handler(request)

    expect(fetch.mock.calls.length).toBe(1);
    expect(fetch.mock.calls[0][0]).toBe(request);
    expect(fetch.mock.calls[0][1]).toEqual({
      cf: {resolveOverride: 'legacy.mass.gov', cacheTtl: 86400},
    });
  });

  test('Should return the response given by the backend', function() {
    expect.assertions(1);
    const request = new Request('https://www.mass.gov/anf');
    return expect(legacy()(request)).resolves.toEqual(dummyResponse());
  });
});

describe('Edit backend', function() {
  beforeEach(() => setupMocks(global));

  test('Should not modify the request\'s method, url, body, or existing headers', async function() {
    const request = new Request('https://www.mass.gov/foo', {
      method: 'OPTIONS',
      headers: new Headers({foo: 'bar'}),
      body: 'Foobar',
    });
    await edit('TEST_TOKEN')(request);
    expect(fetch.mock.calls.length).toBe(1);
    const backendRequest = fetch.mock.calls[0][0];
    expect(backendRequest.method).toEqual('OPTIONS');
    expect(backendRequest.url).toEqual('https://www.mass.gov/foo');
    expect(backendRequest.body).toBe(request.body);
    expect(backendRequest.headers.get('foo')).toEqual('bar');
  });

  test('Should add the CDN token as a header', async function() {
    const request = new Request('https://www.mass.gov/foo');
    await edit('TEST_TOKEN')(request);
    expect(fetch.mock.calls.length).toBe(1);
    const backendRequest = fetch.mock.calls[0][0];
    expect(backendRequest.headers.has('mass-cdn-fwd')).toBe(true);
    expect(backendRequest.headers.get('mass-cdn-fwd')).toBe('TEST_TOKEN');
  });

  test('Should return the backend response', async function() {
    const request = new Request('https://www.mass.gov/foo');
    const response = await edit('TEST_TOKEN')(request);
    expect(response).toEqual(dummyResponse());
  });

  test('Should allow POST requests', async function() {
    const request = new Request('https://www.mass.gov/foo', {
      method: 'POST',
      body: 'FOOBAR',
    });
    const response = await edit('TEST_TOKEN')(request);
    expect(response).toEqual(dummyResponse());
  });
});

describe('WWW Backend', function() {
  beforeEach(() => setupMocks(global));

  test('Should not modify the request\'s method, url, body, or existing headers', async function() {
    expect.assertions(5);
    const request = new Request('https://www.mass.gov/foo', {
      method: 'OPTIONS',
      headers: new Headers({foo: 'bar'}),
      body: 'Foobar',
    });
    await www('TEST_TOKEN')(request);
    expect(fetch.mock.calls.length).toBe(1);
    const backendRequest = fetch.mock.calls[0][0];
    expect(backendRequest.method).toEqual('OPTIONS');
    expect(backendRequest.url).toEqual('https://www.mass.gov/foo');
    expect(backendRequest.body).toBe(request.body);
    expect(backendRequest.headers.get('foo')).toEqual('bar');
  });

  test('Should add the CDN token as a header', async function() {
    expect.assertions(3);
    const request = new Request('https://www.mass.gov/foo');
    await www('TEST_TOKEN')(request);
    expect(fetch.mock.calls.length).toBe(1);
    const backendRequest = fetch.mock.calls[0][0];
    expect(backendRequest.headers.has('mass-cdn-fwd')).toBe(true);
    expect(backendRequest.headers.get('mass-cdn-fwd')).toBe('TEST_TOKEN');
  });

  test('Should return the backend response', async function() {
    expect.assertions(1);
    const request = new Request('https://www.mass.gov/foo');
    const response = await www('TEST_TOKEN')(request);
    expect(response).toEqual(dummyResponse());
  });

  test('Should strip cookies from the request before sending it to the backend', async function() {
    const request = new Request('http://example.com/foo', {
      headers: new Headers({
        'cookie': 'foo=bar'
      })
    })
    await www('TEST_TOKEN')(request);
    expect(fetch.mock.calls.length).toBe(1);
    const backendRequest = fetch.mock.calls[0][0];
    expect(backendRequest.headers.has('cookie')).toBe(false);
  });

  const urlTests = [
    ['https://www.mass.gov/abc?foo=bar', 'https://www.mass.gov/abc?foo=bar'],
    ['https://www.mass.gov/abc?utm_source=foo', 'https://www.mass.gov/abc'],
    ['https://www.mass.gov/abc?utm_source=foo&bar=baz', 'https://www.mass.gov/abc?bar=baz'],
  ]
  urlTests.forEach(function(pair) {
    test(`Should normalize ${pair[0]} to ${pair[1]}`, async function() {
      const request = new Request(pair[0]);
      await www('TEST_TOKEN')(request);
      expect(fetch.mock.calls.length).toBe(1);
      const backendRequest = fetch.mock.calls[0][0];
      expect(backendRequest.url).toEqual(pair[1])
    });
  });

  test('Should strip X-AH and x-Drupal headers', async function() {
    global.fetch = jest.fn(() => Promise.resolve(new Response('', {
      headers: new Headers({
        'x-ah-environment': 'prod',
        'x-drupal-cache': 'HIT',
        'foo': 'bar'
      })
    })));

    const request = new Request('https://www.mass.gov');
    const response = await www('TEST_TOKEN')(request);
    expect(response.headers.has('x-ah-environment')).toEqual(false)
    expect(response.headers.has('x-drupal-cache')).toEqual(false)
    expect(response.headers.get('foo')).toEqual('bar')
  });

  const browserTTLTests = [
      ['https://www.mass.gov/', 'public, max-age=604800, stale-if-error=604800, stale-while-revalidate=604800', 'public, max-age=1800, stale-if-error=604800, stale-while-revalidate=604800'],
      ['https://www.mass.gov/jsonapi/node/alert?foo=bar', 'public, max-age=604800, stale-if-error=604800, stale-while-revalidate=604800', 'public, max-age=60, stale-if-error=604800, stale-while-revalidate=604800'],
      ['https://www.mass.gov/alerts', 'public, max-age=604800, stale-if-error=604800, stale-while-revalidate=604800', 'public, max-age=60, stale-if-error=604800, stale-while-revalidate=604800'],
      ['https://www.mass.gov/', 'public, s-max-age=604800, max-age=604800, stale-if-error=604800, stale-while-revalidate=604800', 'public, s-max-age=604800, max-age=1800, stale-if-error=604800, stale-while-revalidate=604800'],
      ['https://www.mass.gov/', 'private', 'private'],
      ['https://www.mass.gov/info-details/covid-19-cases-quarantine-and-monitoring', 'public, s-max-age=604800, max-age=604800, stale-if-error=604800, stale-while-revalidate=604800', 'public, s-max-age=604800, max-age=60, stale-if-error=604800, stale-while-revalidate=604800'],
  ]
  browserTTLTests.forEach(function([url, originResponseHeaders, expectedResponseHeaders]) {

    test(`Should set cache headers to ${expectedResponseHeaders} given a url of ${url} and origin headers: ${originResponseHeaders}`, async function() {
      global.fetch = jest.fn(() => Promise.resolve(new Response('', {
        headers: new Headers({
          'Cache-Control': originResponseHeaders,
          'Date': 'Fri, 24 May 2019 18:11:06 GMT',
          'X-Generator': 'Drupal 8 (https://www.drupal.org)',
        })
      })));

      const request = new Request(url);
      const response = await www('TEST_TOKEN')(request);
      expect(response.headers.get('cache-control')).toEqual(expectedResponseHeaders);
    })
  });

  test(`Should alter cache headers for non-Drupal responses`, async function() {
    global.fetch = jest.fn(() => Promise.resolve(new Response('', {
      headers: new Headers({
        'Cache-Control': 'public, max-age=604800',
        'Date': 'Fri, 24 May 2019 18:11:06 GMT',
      })
    })));

    const request = new Request('https://www.mass.gov/');
    const response = await www('TEST_TOKEN')(request);
    expect(response.headers.get('cache-control')).toEqual('public, max-age=1800');
  })

  const edgeTTLTests = [
    // Most pages get a 30 minute max lifetime.
    ['https://www.mass.gov/', {cf: {cacheTtl: 1800}}],
    // Alert endpoints get shortened edge TTL.
    ['https://www.mass.gov/alerts/foo', {cf: {cacheTtl: 60}}],
    ['https://www.mass.gov/jsonapi/node/alert?foo=bar', {cf: {cacheTtl: 60}}],
    // No override is expected for static assets.
    ['https://www.mass.gov/foo.jpg', {cf: {}}],
    ['https://www.mass.gov/info-details/covid-19-cases-quarantine-and-monitoring', {cf: {cacheTtl: 60}}],
  ]

  edgeTTLTests.forEach(function([url, expectedOverrides]) {
    test(`Check edge cache lifetime for ${url}`, async function() {
      global.fetch = jest.fn(() => Promise.resolve(new Response('')));

      await www('TEST_TOKEN')(new Request(url));

      expect(fetch.mock.calls.length).toBe(1);
      expect(fetch.mock.calls[0][1]).toEqual(expectedOverrides)
    });
  })

  test(`Should pre-resolve media download redirects`, async function() {
    const expectedResponse = dummyResponse();

    global.fetch = jest.fn((request) => {
      if(request.url === 'https://www.mass.gov/media/123/download') {
        return new Response('', {
          status: 301,
          headers: new Headers({
            'Location': '/files/foo'
          })
        })
      }
      return expectedResponse
    })
    const response = await www('TEST_TOKEN')(new Request('https://www.mass.gov/media/123/download'));

    expect(response).toEqual(response)
    expect(fetch.mock.calls.length).toBe(2);
  });
  test('Should not allow infinite loops when handling media download redirects', async function() {
    global.fetch = jest.fn((request) => {
      if(request.url === 'https://www.mass.gov/media/123/download') {
        return new Response('', {
          status: 301,
          headers: new Headers({
            'Location': '/media/123/download',
          })
        })
      }
      return dummyResponse()
    })
    const response = await www('TEST_TOKEN')(new Request('https://www.mass.gov/media/123/download'));

    // Check that we've avoided recursion by only executing one fetch.
    expect(fetch.mock.calls.length).toBe(1);
    expect(response.status).toEqual(301);
  });

  test('Should pre-resolve media download redirects only when they redirect directly to files.', async function() {
    global.fetch = jest.fn((request) => {
      if(request.url === 'https://www.mass.gov/media/123/download') {
        return new Response('', {
          status: 301,
          headers: new Headers({
            'Location': '/doc/foo/download'
          })
        })
      }
      return dummyResponse()
    })
    const response = await www('TEST_TOKEN')(new Request('https://www.mass.gov/media/123/download'));

    expect(response.headers).toEqual(new Headers({
      'Location': '/doc/foo/download'
    }))
    expect(fetch.mock.calls.length).toBe(1);
  });

  test('Should set a short cache lifetime for media download redirects', async function() {
    global.fetch = jest.fn(() => Promise.resolve(new Response('', {
      status: 404,
      headers: new Headers({
        'Cache-Control': 'public, max-age=3000',
      })
    })));
    const response = await www('TEST_TOKEN')(new Request('https://www.mass.gov/media/123/download'));
    expect(response.headers.get('cache-control')).toEqual('public, max-age=60');
  })

});
