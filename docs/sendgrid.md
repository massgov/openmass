# SendGrid transactional email

Drupal sends email through the `sendgrid_mail` mail system plugin provided by
`drupal/sendgrid`. The `mass_sendgrid` custom module configures the sender,
reply-to address, and one SendGrid Dynamic Template for all messages.

## Required environment variables

- `MASS_SENDGRID_API_KEY`: a SendGrid API key with permission to send mail.
- `MASS_SENDGRID_TEMPLATE_ID`: the Dynamic Template ID, including its `d-`
  prefix.
