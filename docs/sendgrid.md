# SendGrid transactional email

Drupal sends email through the `sendgrid_mail` mail system plugin provided by
`drupal/sendgrid`. The `mass_sendgrid` custom module configures the sender,
reply-to address, and one SendGrid Dynamic Template for all messages.

## Required environment variables

- `MASS_SENDGRID_API_KEY`: a SendGrid API key with permission to send mail.
- `MASS_SENDGRID_TEMPLATE_ID`: the Dynamic Template ID, including its `d-`
  prefix.

## Transactional delivery

Drupal does not assign these messages to an ASM unsubscribe group. The shared
template must not contain ASM unsubscribe or preferences links: opting out of
one message must not suppress unrelated system notifications.

The template receives the rendered Drupal email as `content` and its subject
as `subject`.
