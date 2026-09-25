# SendGrid transactional email

Drupal sends email through the `mass_mail` mail system plugin and the
`drupal/sendgrid` module. All messages use one SendGrid Dynamic Template.

## Required environment variables

- `MASS_SENDGRID_API_KEY`: a SendGrid API key with permission to send mail.
- `MASS_SENDGRID_TEMPLATE_ID`: the Dynamic Template ID, including its `d-`
  prefix.

