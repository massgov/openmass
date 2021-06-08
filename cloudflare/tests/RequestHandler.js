import {setupMocks, dummyResponse} from './mocks';
import RequestHandler from '../src/RequestHandler';


describe('RequestHandler', function() {
  beforeEach(() => setupMocks(global));

  let handler;

  beforeEach(function() {
    handler = new RequestHandler('TEST_TOKEN');
    handler.www = jest.fn(() => Promise.resolve(dummyResponse()))
    handler.edit = jest.fn(() => Promise.resolve(dummyResponse()))
  })

  it('Should route requests to the edit backend', async function() {
    var request = new Request('https://edit.mass.gov/admin/structure');
    await handler.handle(request)
    expect(handler.edit.mock.calls.length).toEqual(1);
    expect(handler.www.mock.calls.length).toEqual(0);
  });

  it('Should route requests to the www backend', async function() {
    var request = new Request('https://www.mass.gov/admin/structure');
    await handler.handle(request);
    expect(handler.edit.mock.calls.length).toEqual(0);
    expect(handler.www.mock.calls.length).toEqual(1);
  });

  it('Should catch errors and return a 5xx response', async function() {
    // Swallow the console.log call so we don't need to see it.
    global.console = {log: function() {}}
    handler.www = function() {
      throw new Error('Testing is hard.');
    }
    var request = new Request('https://www.mass.gov/admin/structure');
    const response = await handler.handle(request);
    expect(response.status).toEqual(503);
  });

  it('Should return a 301 response', async function() {
    var request = new Request('https://www.mass.gov/libraries');
    const response = await handler.handle(request);
    expect(response.status).toEqual(301);
    expect(response.headers.get('location')).toEqual("https://libraries.state.ma.us/");
  });
});
