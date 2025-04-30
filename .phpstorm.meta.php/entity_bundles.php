<?php

declare(strict_types=1);

namespace PHPSTORM_META {

  // Access token.
  registerArgumentsSet('access_token__bundles',
    'access_token',
  );
  expectedReturnValues(\Drupal\access_unpublished\Entity\AccessToken::bundle(), argumentsSet('access_token__bundles'));
  expectedReturnValues(\Drupal\access_unpublished\AccessTokenInterface::bundle(), argumentsSet('access_token__bundles'));

  // Action.
  registerArgumentsSet('action__bundles',
    'action',
  );
  expectedReturnValues(\Drupal\system\Entity\Action::bundle(), argumentsSet('action__bundles'));

  // Base field override.
  registerArgumentsSet('base_field_override__bundles',
    'base_field_override',
  );
  expectedReturnValues(\Drupal\Core\Field\Entity\BaseFieldOverride::bundle(), argumentsSet('base_field_override__bundles'));

  // Rabbit hole settings.
  registerArgumentsSet('behavior_settings__bundles',
    'behavior_settings',
  );
  expectedReturnValues(\Drupal\rabbit_hole\Entity\BehaviorSettings::bundle(), argumentsSet('behavior_settings__bundles'));
  expectedReturnValues(\Drupal\rabbit_hole\BehaviorSettingsInterface::bundle(), argumentsSet('behavior_settings__bundles'));

  // Block.
  registerArgumentsSet('block__bundles',
    'block',
  );
  expectedReturnValues(\Drupal\block\Entity\Block::bundle(), argumentsSet('block__bundles'));
  expectedReturnValues(\Drupal\block\BlockInterface::bundle(), argumentsSet('block__bundles'));

  // Language.
  registerArgumentsSet('configurable_language__bundles',
    'configurable_language',
  );
  expectedReturnValues(\Drupal\language\Entity\ConfigurableLanguage::bundle(), argumentsSet('configurable_language__bundles'));
  expectedReturnValues(\Drupal\language\ConfigurableLanguageInterface::bundle(), argumentsSet('configurable_language__bundles'));

  // Contact form.
  registerArgumentsSet('contact_form__bundles',
    'contact_form',
  );
  expectedReturnValues(\Drupal\contact\Entity\ContactForm::bundle(), argumentsSet('contact_form__bundles'));
  expectedReturnValues(\Drupal\contact\ContactFormInterface::bundle(), argumentsSet('contact_form__bundles'));

  // Contact message.
  registerArgumentsSet('contact_message__bundles',
    'flag_content',
    'personal',
  );
  expectedReturnValues(\Drupal\contact\Entity\Message::bundle(), argumentsSet('contact_message__bundles'));
  expectedReturnValues(\Drupal\contact\MessageInterface::bundle(), argumentsSet('contact_message__bundles'));

  // Content moderation state.
  registerArgumentsSet('content_moderation_state__bundles',
    'content_moderation_state',
  );
  expectedReturnValues(\Drupal\content_moderation\Entity\ContentModerationState::bundle(), argumentsSet('content_moderation_state__bundles'));

  // Crop.
  registerArgumentsSet('crop__bundles',
    'focal_point',
  );
  expectedReturnValues(\Drupal\crop\Entity\Crop::bundle(), argumentsSet('crop__bundles'));
  expectedReturnValues(\Drupal\crop\CropInterface::bundle(), argumentsSet('crop__bundles'));

  // Crop type.
  registerArgumentsSet('crop_type__bundles',
    'crop_type',
  );
  expectedReturnValues(\Drupal\crop\Entity\CropType::bundle(), argumentsSet('crop_type__bundles'));
  expectedReturnValues(\Drupal\crop\CropTypeInterface::bundle(), argumentsSet('crop_type__bundles'));

  // Date format.
  registerArgumentsSet('date_format__bundles',
    'date_format',
  );
  expectedReturnValues(\Drupal\Core\Datetime\Entity\DateFormat::bundle(), argumentsSet('date_format__bundles'));
  expectedReturnValues(\Drupal\Core\Datetime\DateFormatInterface::bundle(), argumentsSet('date_format__bundles'));

  // Text editor.
  registerArgumentsSet('editor__bundles',
    'editor',
  );
  expectedReturnValues(\Drupal\editor\Entity\Editor::bundle(), argumentsSet('editor__bundles'));
  expectedReturnValues(\Drupal\editor\EditorInterface::bundle(), argumentsSet('editor__bundles'));

  // Microsite menu override.
  registerArgumentsSet('eh_microsite_menu_override__bundles',
    'eh_microsite_menu_override',
  );
  expectedReturnValues(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverride::bundle(), argumentsSet('eh_microsite_menu_override__bundles'));

  // Embed button.
  registerArgumentsSet('embed_button__bundles',
    'embed_button',
  );
  expectedReturnValues(\Drupal\embed\Entity\EmbedButton::bundle(), argumentsSet('embed_button__bundles'));
  expectedReturnValues(\Drupal\embed\EmbedButtonInterface::bundle(), argumentsSet('embed_button__bundles'));

  // Entity browser.
  registerArgumentsSet('entity_browser__bundles',
    'entity_browser',
  );
  expectedReturnValues(\Drupal\entity_browser\Entity\EntityBrowser::bundle(), argumentsSet('entity_browser__bundles'));
  expectedReturnValues(\Drupal\entity_browser\EntityBrowserInterface::bundle(), argumentsSet('entity_browser__bundles'));

  // Fake entity type.
  registerArgumentsSet('entity_embed_fake_entity__bundles',
    'entity_embed_fake_entity',
  );
  expectedReturnValues(\Drupal\entity_embed\Entity\EntityEmbedFakeEntity::bundle(), argumentsSet('entity_embed_fake_entity__bundles'));

  // Entity form display.
  registerArgumentsSet('entity_form_display__bundles',
    'entity_form_display',
  );
  expectedReturnValues(\Drupal\Core\Entity\Entity\EntityFormDisplay::bundle(), argumentsSet('entity_form_display__bundles'));

  // Form mode.
  registerArgumentsSet('entity_form_mode__bundles',
    'entity_form_mode',
  );
  expectedReturnValues(\Drupal\Core\Entity\Entity\EntityFormMode::bundle(), argumentsSet('entity_form_mode__bundles'));
  expectedReturnValues(\Drupal\Core\Entity\EntityFormModeInterface::bundle(), argumentsSet('entity_form_mode__bundles'));

  // Microsite.
  registerArgumentsSet('entity_hierarchy_microsite__bundles',
    'entity_hierarchy_microsite',
  );
  expectedReturnValues(\Drupal\entity_hierarchy_microsite\Entity\Microsite::bundle(), argumentsSet('entity_hierarchy_microsite__bundles'));

  // Entity view display.
  registerArgumentsSet('entity_view_display__bundles',
    'entity_view_display',
  );
  expectedReturnValues(\Drupal\Core\Entity\Entity\EntityViewDisplay::bundle(), argumentsSet('entity_view_display__bundles'));

  // View mode.
  registerArgumentsSet('entity_view_mode__bundles',
    'entity_view_mode',
  );
  expectedReturnValues(\Drupal\Core\Entity\Entity\EntityViewMode::bundle(), argumentsSet('entity_view_mode__bundles'));
  expectedReturnValues(\Drupal\Core\Entity\EntityViewModeInterface::bundle(), argumentsSet('entity_view_mode__bundles'));

  // Environment Switcher.
  registerArgumentsSet('environment_indicator__bundles',
    'environment_indicator',
  );
  expectedReturnValues(\Drupal\environment_indicator\Entity\EnvironmentIndicator::bundle(), argumentsSet('environment_indicator__bundles'));

  // Field.
  registerArgumentsSet('field_config__bundles',
    'field_config',
  );
  expectedReturnValues(\Drupal\field\Entity\FieldConfig::bundle(), argumentsSet('field_config__bundles'));
  expectedReturnValues(\Drupal\field\FieldConfigInterface::bundle(), argumentsSet('field_config__bundles'));

  // Field storage.
  registerArgumentsSet('field_storage_config__bundles',
    'field_storage_config',
  );
  expectedReturnValues(\Drupal\field\Entity\FieldStorageConfig::bundle(), argumentsSet('field_storage_config__bundles'));
  expectedReturnValues(\Drupal\field\FieldStorageConfigInterface::bundle(), argumentsSet('field_storage_config__bundles'));

  // File.
  registerArgumentsSet('file__bundles',
    'file',
  );
  expectedReturnValues(\Drupal\file\Entity\File::bundle(), argumentsSet('file__bundles'));
  expectedReturnValues(\Drupal\file\FileInterface::bundle(), argumentsSet('file__bundles'));

  // Text format.
  registerArgumentsSet('filter_format__bundles',
    'filter_format',
  );
  expectedReturnValues(\Drupal\filter\Entity\FilterFormat::bundle(), argumentsSet('filter_format__bundles'));
  expectedReturnValues(\Drupal\filter\FilterFormatInterface::bundle(), argumentsSet('filter_format__bundles'));

  // Flag.
  registerArgumentsSet('flag__bundles',
    'flag',
  );
  expectedReturnValues(\Drupal\flag\Entity\Flag::bundle(), argumentsSet('flag__bundles'));
  expectedReturnValues(\Drupal\flag\FlagInterface::bundle(), argumentsSet('flag__bundles'));

  // Flagging.
  registerArgumentsSet('flagging__bundles',
    'watch_content',
  );
  expectedReturnValues(\Drupal\flag\Entity\Flagging::bundle(), argumentsSet('flagging__bundles'));
  expectedReturnValues(\Drupal\flag\FlaggingInterface::bundle(), argumentsSet('flagging__bundles'));

  // Geocoder provider.
  registerArgumentsSet('geocoder_provider__bundles',
    'geocoder_provider',
  );
  expectedReturnValues(\Drupal\geocoder\Entity\GeocoderProvider::bundle(), argumentsSet('geocoder_provider__bundles'));
  expectedReturnValues(\Drupal\geocoder\GeocoderProviderInterface::bundle(), argumentsSet('geocoder_provider__bundles'));

  // Google Tag Container.
  registerArgumentsSet('google_tag_container__bundles',
    'google_tag_container',
  );
  expectedReturnValues(\Drupal\google_tag\Entity\TagContainer::bundle(), argumentsSet('google_tag_container__bundles'));

  // Image style.
  registerArgumentsSet('image_style__bundles',
    'image_style',
  );
  expectedReturnValues(\Drupal\mass_utility\EntityAlter\ImageStyle::bundle(), argumentsSet('image_style__bundles'));

  // Key.
  registerArgumentsSet('key__bundles',
    'key',
  );
  expectedReturnValues(\Drupal\key\Entity\Key::bundle(), argumentsSet('key__bundles'));
  expectedReturnValues(\Drupal\key\KeyInterface::bundle(), argumentsSet('key__bundles'));

  // Key Configuration Override.
  registerArgumentsSet('key_config_override__bundles',
    'key_config_override',
  );
  expectedReturnValues(\Drupal\key\Entity\KeyConfigOverride::bundle(), argumentsSet('key_config_override__bundles'));
  expectedReturnValues(\Drupal\key\KeyConfigOverrideInterface::bundle(), argumentsSet('key_config_override__bundles'));

  // Content language settings.
  registerArgumentsSet('language_content_settings__bundles',
    'language_content_settings',
  );
  expectedReturnValues(\Drupal\language\Entity\ContentLanguageSettings::bundle(), argumentsSet('language_content_settings__bundles'));
  expectedReturnValues(\Drupal\language\ContentLanguageSettingsInterface::bundle(), argumentsSet('language_content_settings__bundles'));

  // Linkit profile.
  registerArgumentsSet('linkit_profile__bundles',
    'linkit_profile',
  );
  expectedReturnValues(\Drupal\linkit\Entity\Profile::bundle(), argumentsSet('linkit_profile__bundles'));
  expectedReturnValues(\Drupal\linkit\ProfileInterface::bundle(), argumentsSet('linkit_profile__bundles'));

  // Mailchimp Transactional Template Map.
  registerArgumentsSet('mailchimp_transactional_template__bundles',
    'mailchimp_transactional_template',
  );
  expectedReturnValues(\Drupal\mailchimp_transactional_template\Entity\TemplateMap::bundle(), argumentsSet('mailchimp_transactional_template__bundles'));

  // Media.
  registerArgumentsSet('media__bundles',
    'document',
    'media_video',
  );
  expectedReturnValues(\Drupal\media\Entity\Media::bundle(), argumentsSet('media__bundles'));
  expectedReturnValues(\Drupal\media\MediaInterface::bundle(), argumentsSet('media__bundles'));

  // Media type.
  registerArgumentsSet('media_type__bundles',
    'media_type',
  );
  expectedReturnValues(\Drupal\media\Entity\MediaType::bundle(), argumentsSet('media_type__bundles'));
  expectedReturnValues(\Drupal\media\MediaTypeInterface::bundle(), argumentsSet('media_type__bundles'));

  // Menu.
  registerArgumentsSet('menu__bundles',
    'menu',
  );
  expectedReturnValues(\Drupal\system\Entity\Menu::bundle(), argumentsSet('menu__bundles'));
  expectedReturnValues(\Drupal\system\MenuInterface::bundle(), argumentsSet('menu__bundles'));

  // Custom menu link.
  registerArgumentsSet('menu_link_content__bundles',
    'menu_link_content',
  );
  expectedReturnValues(\Drupal\menu_link_content\Entity\MenuLinkContent::bundle(), argumentsSet('menu_link_content__bundles'));
  expectedReturnValues(\Drupal\menu_link_content\MenuLinkContentInterface::bundle(), argumentsSet('menu_link_content__bundles'));

  // Metatag defaults.
  registerArgumentsSet('metatag_defaults__bundles',
    'metatag_defaults',
  );
  expectedReturnValues(\Drupal\metatag\Entity\MetatagDefaults::bundle(), argumentsSet('metatag_defaults__bundles'));
  expectedReturnValues(\Drupal\metatag\MetatagDefaultsInterface::bundle(), argumentsSet('metatag_defaults__bundles'));

  // Content.
  registerArgumentsSet('node__bundles',
    'action',
    'advisory',
    'alert',
    'api_service_card',
    'binder',
    'campaign_landing',
    'contact_information',
    'curated_list',
    'decision',
    'decision_tree',
    'decision_tree_branch',
    'decision_tree_conclusion',
    'error_page',
    'event',
    'executive_order',
    'external_data_resource',
    'fee',
    'form_page',
    'glossary',
    'guide_page',
    'how_to_page',
    'info_details',
    'interstitial',
    'location',
    'location_details',
    'news',
    'org_page',
    'page',
    'person',
    'regulation',
    'rules',
    'service_page',
    'sitewide_alert',
    'stacked_layout',
    'topic_page',
    'utility_drawer',
  );
  expectedReturnValues(\Drupal\node\Entity\Node::bundle(), argumentsSet('node__bundles'));
  expectedReturnValues(\Drupal\node\Entity\Node::getType(), argumentsSet('node__bundles'));
  expectedReturnValues(\Drupal\node\NodeInterface::bundle(), argumentsSet('node__bundles'));
  expectedReturnValues(\Drupal\node\NodeInterface::getType(), argumentsSet('node__bundles'));

  // Content type.
  registerArgumentsSet('node_type__bundles',
    'node_type',
  );
  expectedReturnValues(\Drupal\node\Entity\NodeType::bundle(), argumentsSet('node_type__bundles'));
  expectedReturnValues(\Drupal\node\NodeTypeInterface::bundle(), argumentsSet('node_type__bundles'));

  // OpenID Connect client.
  registerArgumentsSet('openid_connect_client__bundles',
    'openid_connect_client',
  );
  expectedReturnValues(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::bundle(), argumentsSet('openid_connect_client__bundles'));
  expectedReturnValues(\Drupal\openid_connect\OpenIDConnectClientEntityInterface::bundle(), argumentsSet('openid_connect_client__bundles'));

  // Paragraph.
  registerArgumentsSet('paragraph__bundles',
    '1up_stacked_band',
    '2up_stacked_band',
    '3_up_content',
    '3_up_text',
    'about',
    'action_address',
    'action_address_info',
    'action_area',
    'action_set',
    'action_step',
    'action_step_numbered',
    'action_step_numbered_list',
    'activities',
    'activity',
    'address',
    'adjustment_type',
    'advisory_issuer',
    'advisory_section',
    'alert',
    'board_member',
    'callout_alert',
    'callout_button',
    'callout_link',
    'campaign_features',
    'card',
    'caspio_embed',
    'collection_search',
    'completion_time',
    'contact',
    'contact_group',
    'contact_info',
    'contact_placeholder',
    'content_card_group',
    'csv_table',
    'custom_html',
    'decision_participants',
    'decision_section',
    'emergency_alert',
    'event_agenda_section',
    'event_minutes_section',
    'external_organization',
    'fax_number',
    'featured_content_2up_item',
    'featured_content_single_item',
    'featured_item',
    'featured_item_mosaic',
    'featured_message',
    'featured_topics',
    'file_download',
    'file_download_single',
    'flexible_link_group',
    'footnotes',
    'from_library',
    'full_bleed',
    'guide_section',
    'guide_section_3up',
    'homepage_background_images',
    'hours',
    'icon',
    'icon_link',
    'icon_links',
    'iframe',
    'image',
    'image_credit',
    'info_details_card_group',
    'issuer',
    'key_message',
    'key_message_section',
    'links',
    'links_downloads',
    'links_downloads_flexible',
    'link_group_accordion',
    'link_group_document',
    'link_group_link',
    'list_board_members',
    'list_dynamic',
    'list_item_contact',
    'list_item_document',
    'list_item_link',
    'list_item_person',
    'list_manual_directory',
    'list_static',
    'location_information',
    'manage_account_link',
    'map',
    'map_row',
    'media_contact',
    'method',
    'more_info',
    'multiple_answers',
    'next_step',
    'online_email',
    'organization_contact_logo',
    'organization_grid',
    'org_events',
    'org_locations',
    'org_news',
    'org_related_orgs',
    'org_section_long_form',
    'page',
    'page_group',
    'phone_number',
    'pull_quote',
    'quick_action',
    'recommended_activity',
    'regulation_section',
    'related_content',
    'related_link',
    'rich_text',
    'rules_section',
    'rules_updates',
    'search_band',
    'search_banner',
    'section',
    'section_board_members',
    'section_header',
    'section_heading_text',
    'section_long_form',
    'section_with_heading',
    'service_rich_text',
    'service_section',
    'sitewide_alert_message',
    'slideshow',
    'social_media',
    'stacked_band',
    'start_button',
    'stat',
    'state_organization',
    'subhead',
    'tableau_embed',
    'time_callout',
    'video',
    'video_with_header',
    'video_with_section',
    'what_would_you_like_to_do',
  );
  expectedReturnValues(\Drupal\paragraphs\Entity\Paragraph::bundle(), argumentsSet('paragraph__bundles'));
  expectedReturnValues(\Drupal\paragraphs\ParagraphInterface::bundle(), argumentsSet('paragraph__bundles'));

  // Paragraphs library item.
  registerArgumentsSet('paragraphs_library_item__bundles',
    'paragraphs_library_item',
  );
  expectedReturnValues(\Drupal\paragraphs_library\Entity\LibraryItem::bundle(), argumentsSet('paragraphs_library_item__bundles'));
  expectedReturnValues(\Drupal\paragraphs_library\LibraryItemInterface::bundle(), argumentsSet('paragraphs_library_item__bundles'));

  // Paragraphs type.
  registerArgumentsSet('paragraphs_type__bundles',
    'paragraphs_type',
  );
  expectedReturnValues(\Drupal\paragraphs\Entity\ParagraphsType::bundle(), argumentsSet('paragraphs_type__bundles'));
  expectedReturnValues(\Drupal\paragraphs\ParagraphsTypeInterface::bundle(), argumentsSet('paragraphs_type__bundles'));

  // URL alias.
  registerArgumentsSet('path_alias__bundles',
    'path_alias',
  );
  expectedReturnValues(\Drupal\path_alias\Entity\PathAlias::bundle(), argumentsSet('path_alias__bundles'));
  expectedReturnValues(\Drupal\path_alias\PathAliasInterface::bundle(), argumentsSet('path_alias__bundles'));

  // Pathauto pattern.
  registerArgumentsSet('pathauto_pattern__bundles',
    'pathauto_pattern',
  );
  expectedReturnValues(\Drupal\pathauto\Entity\PathautoPattern::bundle(), argumentsSet('pathauto_pattern__bundles'));
  expectedReturnValues(\Drupal\pathauto\PathautoPatternInterface::bundle(), argumentsSet('pathauto_pattern__bundles'));

  // Private files download permission directory.
  registerArgumentsSet('pfdp_directory__bundles',
    'pfdp_directory',
  );
  expectedReturnValues(\Drupal\pfdp\Entity\DirectoryEntity::bundle(), argumentsSet('pfdp_directory__bundles'));

  // Redirect.
  registerArgumentsSet('redirect__bundles',
    'redirect',
  );
  expectedReturnValues(\Drupal\redirect\Entity\Redirect::bundle(), argumentsSet('redirect__bundles'));

  // Response Header.
  registerArgumentsSet('response_header__bundles',
    'response_header',
  );
  expectedReturnValues(\Drupal\http_response_headers\Entity\ResponseHeader::bundle(), argumentsSet('response_header__bundles'));
  expectedReturnValues(\Drupal\http_response_headers\ResponseHeaderInterface::bundle(), argumentsSet('response_header__bundles'));

  // Responsive image style.
  registerArgumentsSet('responsive_image_style__bundles',
    'responsive_image_style',
  );
  expectedReturnValues(\Drupal\responsive_image\Entity\ResponsiveImageStyle::bundle(), argumentsSet('responsive_image_style__bundles'));
  expectedReturnValues(\Drupal\responsive_image\ResponsiveImageStyleInterface::bundle(), argumentsSet('responsive_image_style__bundles'));

  // REST resource configuration.
  registerArgumentsSet('rest_resource_config__bundles',
    'rest_resource_config',
  );
  expectedReturnValues(\Drupal\rest\Entity\RestResourceConfig::bundle(), argumentsSet('rest_resource_config__bundles'));
  expectedReturnValues(\Drupal\rest\RestResourceConfigInterface::bundle(), argumentsSet('rest_resource_config__bundles'));

  // scheduled transition.
  registerArgumentsSet('scheduled_transition__bundles',
    'scheduled_transition',
  );
  expectedReturnValues(\Drupal\scheduled_transitions\Entity\ScheduledTransition::bundle(), argumentsSet('scheduled_transition__bundles'));

  // Sitemap.
  registerArgumentsSet('simple_sitemap__bundles',
    'simple_sitemap',
  );
  expectedReturnValues(\Drupal\simple_sitemap\Entity\SimpleSitemap::bundle(), argumentsSet('simple_sitemap__bundles'));

  // Simple XML sitemap type.
  registerArgumentsSet('simple_sitemap_type__bundles',
    'simple_sitemap_type',
  );
  expectedReturnValues(\Drupal\simple_sitemap\Entity\SimpleSitemapType::bundle(), argumentsSet('simple_sitemap_type__bundles'));

  // Taxonomy term.
  registerArgumentsSet('taxonomy_term__bundles',
    'action_type',
    'adjustment_type',
    'advisory_publish_state',
    'advisory_type',
    'announcement_type',
    'billing_organizations',
    'binder_type',
    'collections',
    'content_type',
    'data_topic',
    'decision_participant_type',
    'decision_type',
    'document_category',
    'document_contacts',
    'document_creators',
    'document_other_type',
    'document_subjects',
    'document_tags',
    'event_type',
    'icons',
    'label',
    'language',
    'license',
    'location_activities',
    'location_icon',
    'location_icon_park',
    'organization_type',
    'rules_of_court_standing_order',
    'rules_of_court_type',
    'tags',
    'tx_data_resource_type',
    'tx_details_data_type',
    'tx_list_data_type',
    'type',
    'user_organization',
  );
  expectedReturnValues(\Drupal\taxonomy\Entity\Term::bundle(), argumentsSet('taxonomy_term__bundles'));
  expectedReturnValues(\Drupal\taxonomy\TermInterface::bundle(), argumentsSet('taxonomy_term__bundles'));

  // Taxonomy vocabulary.
  registerArgumentsSet('taxonomy_vocabulary__bundles',
    'taxonomy_vocabulary',
  );
  expectedReturnValues(\Drupal\taxonomy\Entity\Vocabulary::bundle(), argumentsSet('taxonomy_vocabulary__bundles'));
  expectedReturnValues(\Drupal\taxonomy\VocabularyInterface::bundle(), argumentsSet('taxonomy_vocabulary__bundles'));

  // User.
  registerArgumentsSet('user__bundles',
    'user',
  );
  expectedReturnValues(\Drupal\user\Entity\User::bundle(), argumentsSet('user__bundles'));
  expectedReturnValues(\Drupal\user\UserInterface::bundle(), argumentsSet('user__bundles'));

  // User Reference Access.
  registerArgumentsSet('user_ref_access__bundles',
    'user_ref_access',
  );
  expectedReturnValues(\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess::bundle(), argumentsSet('user_ref_access__bundles'));

  // Role.
  registerArgumentsSet('user_role__bundles',
    'user_role',
  );
  expectedReturnValues(\Drupal\user\Entity\Role::bundle(), argumentsSet('user_role__bundles'));
  expectedReturnValues(\Drupal\user\RoleInterface::bundle(), argumentsSet('user_role__bundles'));

  // View.
  registerArgumentsSet('view__bundles',
    'view',
  );
  expectedReturnValues(\Drupal\views\Entity\View::bundle(), argumentsSet('view__bundles'));

  // Viewmodepage pattern.
  registerArgumentsSet('view_mode_page_pattern__bundles',
    'view_mode_page_pattern',
  );
  expectedReturnValues(\Drupal\view_mode_page\Entity\ViewmodepagePattern::bundle(), argumentsSet('view_mode_page_pattern__bundles'));
  expectedReturnValues(\Drupal\view_mode_page\ViewmodepagePatternInterface::bundle(), argumentsSet('view_mode_page_pattern__bundles'));

  // Workflow.
  registerArgumentsSet('workflow__bundles',
    'workflow',
  );
  expectedReturnValues(\Drupal\workflows\Entity\Workflow::bundle(), argumentsSet('workflow__bundles'));
  expectedReturnValues(\Drupal\workflows\WorkflowInterface::bundle(), argumentsSet('workflow__bundles'));

}
