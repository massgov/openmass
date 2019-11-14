/**
 * This file sets up the Cloudflare worker for mass.gov.
 *
 * Here, we just create a new RequestHandler and bind it to fetch
 * events.
 */
import RequestHandler from "./RequestHandler";

// Setup the request handler with our CDN token.
const handler = new RequestHandler(process.env.MASS_CDN_TOKEN);

addEventListener('fetch', event => {
  event.respondWith(handler.handle(event.request));
})
