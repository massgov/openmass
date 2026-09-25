# SendGrid transactional email

Drupal sends email through the `mass_mail` mail system plugin and the
`drupal/sendgrid` module. All messages use one SendGrid Dynamic Template.

## Required environment variables

- `MASS_SENDGRID_API_KEY`: a SendGrid API key with permission to send mail.
- `MASS_SENDGRID_TEMPLATE_ID`: the Dynamic Template ID, including its `d-`
  prefix.

## Optional environment variables

- `MASS_SENDGRID_ASM_GROUP_ID`: the numeric SendGrid unsubscribe group ID.
  Configure this when the Dynamic Template uses SendGrid ASM unsubscribe or
  preferences URL tags. When omitted, Drupal does not add an `asm` object to
  the SendGrid request.

The template receives the rendered Drupal email as `content` and its subject
as `subject`.
