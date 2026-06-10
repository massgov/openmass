# mass_inline_message tests

## ExistingSite (PHP, no browser)

- `InlineMessageNormalizeTest` — body HTML normalization (`MessageBoxBody`)
- `InlineMessagePreviewTest` — renderer + filter output
- `InlineMessageFilterTest` — `filter_mass_inline_message`
- `InlineMessageMessageBoxBodyFormatTest` — `message_box_body` text format
- `InlineMessageConstraintValidationTest` — save-time constraint rules

## ExistingSiteJavascript (Selenium)

- `InlineMessageCKEditorTest` — insert via dialog, edit via widget toolbar (node body)
- `InlineMessageInfoDetailsOverviewTest` — info_details Overview field save/load
- `InlineMessageLayoutParagraphsTest` — LP Rich text insert (nested + top-level) + widget toolbar in modal

Shared helpers live in `src/Traits/`.
