
import makeServiceWorkerMock from 'service-worker-mock';
import {URLSearchParams} from 'url';


const mockContext = makeServiceWorkerMock();

export function setupMocks(global) {
  // Set up a mock service worker environment for testing
  // purposes.  This
  // Mock out the service worker environment, including
  // fetch, Request, Response, etc.
  Object.assign(
    global,
    mockContext,
    {
      // The URLSearchParams mock included in 'service-worker-mock' is not
      // an accurate representation of the one Cloudflare uses. Use the built
      // in node URLSearchParams instead.
      URLSearchParams: URLSearchParams,
      // Setup a fetch mock to deliver dummmy responses when fetch is invoked.
      fetch: jest.fn(() => Promise.resolve(dummyResponse())),
    }
  )
}

export function dummyResponse() {
  return new mockContext.Response('', {
    headers: new mockContext.Headers({})
  })
}
