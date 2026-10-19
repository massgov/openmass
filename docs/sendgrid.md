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

## Dynamic template

The template receives the rendered Drupal email as `content` and its plain-text
subject as `subject`. Set the template subject to `{{{subject}}}` and insert the
HTML body with `{{{content}}}`. Triple braces prevent subjects containing `&` or
apostrophes from appearing as HTML entities and preserve the rendered body.

Before activating the template in SendGrid:

- Replace the newsletter-specific `Mass.gov News` HTML title with `Mass.gov`.
- Remove the newsletter subscription link unless an approved destination exists;
  otherwise replace its Proofpoint/Mailchimp tracking URL with that direct URL.
- Remove the legacy 2019 Mailchimp UTM parameters from the ServiceNow and
  knowledge base links.
- Remove `<%asm_preferences_raw_url%>` and `<%asm_group_unsubscribe_raw_url%>`
  links and their surrounding unsubscribe text.

The active template is managed in SendGrid, outside this repository. Changes to
this document do not update it.

## Deployment

1. Configure `MASS_SENDGRID_API_KEY` and `MASS_SENDGRID_TEMPLATE_ID` on every
   Acquia environment before deploying the mail-system configuration. Verify
   that the template ID refers to the intended active version and that the
   sender domain is authenticated in that SendGrid account.
2. Deploy and import configuration to uninstall the Mailchimp modules and enable
   SendGrid. Keep the Mailchimp Composer package available for this uninstall
   release; Drupal still needs its code to uninstall the modules.
3. Send a test message on each environment and confirm delivery in SendGrid
   activity and in the recipient's inbox before processing notification queues.
   Reminder records are written before sending to prevent duplicates, so failed
   reminders are not automatically retried.
4. Follow-up work: remove `drupal/mailchimp_transactional` from `composer.json`
   and `composer.lock` in a later release, after all environments have imported
   the uninstall configuration. Create and link a follow-up ticket before merge.

## Manual verification

1. Leave `MASS_SENDGRID` unset locally and trigger an email. Confirm it appears
   in DDEV Mailpit. This path does not exercise the SendGrid dynamic template.
2. To test SendGrid locally, set `MASS_SENDGRID=1` and the two required variables
   in `.ddev/.env`, then restart DDEV. Use controlled test recipients.
3. Submit the Flag Content form with a test sender address. Verify the message
   reaches DigitalSupport and replying addresses the submitter.
4. Trigger an unpublish reminder for a page owned by a test author with other
   editors in its organization. Verify the author receives the message and the
   organization editors appear in Cc.
5. Trigger a watch report or other notification whose page title contains an
   ampersand and an apostrophe. Verify the received subject displays those
   characters literally, the body renders as HTML, and the Mass.gov template
   and intended links appear without newsletter or unsubscribe controls.
6. Remove the local SendGrid opt-in when finished and restart DDEV to restore
   Mailpit delivery.
