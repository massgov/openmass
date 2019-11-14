# Mass.gov Web Services

In August 2017, MassIT decided to standardize on JSON API for its web services. Future web services integrations should be built upon it. Legacy web services should transition to JSON API as possible.

## Links

- [JSONAPI docs pages](https://www.drupal.org/node/2803093).
- [Video series](https://www.youtube.com/playlist?list=PLZOQ_ZMpYrZsyO-3IstImK1okrpfAjuMZ).
- The JSON API schema is viewable at /docson.
- Our docs on [authentication](Authentication.md).

## Authentication

Our web services use cookie authentication. To access this API you need to send a POST request to the /user/login path to generate a cookie. Send the cookie back to a GET request to interact with the webservice.

### Using cookie authentication

First, you need to have a drupal account with a developer role. Ensure you have this.
Next, you must authenticate against the /user/login path.

Make a POST request to `/user/login?_format=json`.

The POST body should be json (select raw in Postman):

```json
{
  "name": "<username of your Drupal account (developer role)>",
  "pass": "<password of your Drupal account (developer role)>"
}
```

One header should also be sent setting Content-Type to application/json.

You should get a valid response from this POST request containing a current_user object with a uid and name, a csrf_token, and a logout_token.
There should also be a session cookie set on the response. This cookie can be used to request information from web services.

### Making authenticated requests

For Postman tests, using the same window for sending a get request to a web services endpoint will automatically attach the cookie.
For other methods of interaction, please ensure that this cookie is attached to the request.

### Links

- [Curl examples on drupal.org](https://www.drupal.org/node/2720655)
