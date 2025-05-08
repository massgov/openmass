<?php

declare(strict_types=1);

namespace PHPSTORM_META {

  // Access token.
  registerArgumentsSet('access_token__links',
    'delete',
    'renew',
  );
  expectedArguments(\Drupal\access_unpublished\Entity\AccessToken::toUrl(), 0, argumentsSet('access_token__links'));
  expectedArguments(\Drupal\access_unpublished\Entity\AccessToken::toLink(), 1, argumentsSet('access_token__links'));
  expectedArguments(\Drupal\access_unpublished\Entity\AccessToken::hasLinkTemplate(), 0, argumentsSet('access_token__links'));
  expectedArguments(\Drupal\access_unpublished\AccessTokenInterface::toUrl(), 0, argumentsSet('access_token__links'));
  expectedArguments(\Drupal\access_unpublished\AccessTokenInterface::toLink(), 1, argumentsSet('access_token__links'));
  expectedArguments(\Drupal\access_unpublished\AccessTokenInterface::hasLinkTemplate(), 0, argumentsSet('access_token__links'));

  // Action.
  registerArgumentsSet('action__links',
    'delete-form',
    'edit-form',
    'collection',
  );
  expectedArguments(\Drupal\system\Entity\Action::toUrl(), 0, argumentsSet('action__links'));
  expectedArguments(\Drupal\system\Entity\Action::toLink(), 1, argumentsSet('action__links'));
  expectedArguments(\Drupal\system\Entity\Action::hasLinkTemplate(), 0, argumentsSet('action__links'));

  // Block.
  registerArgumentsSet('block__links',
    'delete-form',
    'edit-form',
    'enable',
    'disable',
  );
  expectedArguments(\Drupal\block\Entity\Block::toUrl(), 0, argumentsSet('block__links'));
  expectedArguments(\Drupal\block\Entity\Block::toLink(), 1, argumentsSet('block__links'));
  expectedArguments(\Drupal\block\Entity\Block::hasLinkTemplate(), 0, argumentsSet('block__links'));
  expectedArguments(\Drupal\block\BlockInterface::toUrl(), 0, argumentsSet('block__links'));
  expectedArguments(\Drupal\block\BlockInterface::toLink(), 1, argumentsSet('block__links'));
  expectedArguments(\Drupal\block\BlockInterface::hasLinkTemplate(), 0, argumentsSet('block__links'));

  // Language.
  registerArgumentsSet('configurable_language__links',
    'delete-form',
    'edit-form',
    'collection',
  );
  expectedArguments(\Drupal\language\Entity\ConfigurableLanguage::toUrl(), 0, argumentsSet('configurable_language__links'));
  expectedArguments(\Drupal\language\Entity\ConfigurableLanguage::toLink(), 1, argumentsSet('configurable_language__links'));
  expectedArguments(\Drupal\language\Entity\ConfigurableLanguage::hasLinkTemplate(), 0, argumentsSet('configurable_language__links'));
  expectedArguments(\Drupal\language\ConfigurableLanguageInterface::toUrl(), 0, argumentsSet('configurable_language__links'));
  expectedArguments(\Drupal\language\ConfigurableLanguageInterface::toLink(), 1, argumentsSet('configurable_language__links'));
  expectedArguments(\Drupal\language\ConfigurableLanguageInterface::hasLinkTemplate(), 0, argumentsSet('configurable_language__links'));

  // Contact form.
  registerArgumentsSet('contact_form__links',
    'delete-form',
    'edit-form',
    'entity-permissions-form',
    'collection',
    'canonical',
    'auto-label',
  );
  expectedArguments(\Drupal\contact\Entity\ContactForm::toUrl(), 0, argumentsSet('contact_form__links'));
  expectedArguments(\Drupal\contact\Entity\ContactForm::toLink(), 1, argumentsSet('contact_form__links'));
  expectedArguments(\Drupal\contact\Entity\ContactForm::hasLinkTemplate(), 0, argumentsSet('contact_form__links'));
  expectedArguments(\Drupal\contact\ContactFormInterface::toUrl(), 0, argumentsSet('contact_form__links'));
  expectedArguments(\Drupal\contact\ContactFormInterface::toLink(), 1, argumentsSet('contact_form__links'));
  expectedArguments(\Drupal\contact\ContactFormInterface::hasLinkTemplate(), 0, argumentsSet('contact_form__links'));

  // Crop type.
  registerArgumentsSet('crop_type__links',
    'edit-form',
    'delete-form',
    'auto-label',
  );
  expectedArguments(\Drupal\crop\Entity\CropType::toUrl(), 0, argumentsSet('crop_type__links'));
  expectedArguments(\Drupal\crop\Entity\CropType::toLink(), 1, argumentsSet('crop_type__links'));
  expectedArguments(\Drupal\crop\Entity\CropType::hasLinkTemplate(), 0, argumentsSet('crop_type__links'));
  expectedArguments(\Drupal\crop\CropTypeInterface::toUrl(), 0, argumentsSet('crop_type__links'));
  expectedArguments(\Drupal\crop\CropTypeInterface::toLink(), 1, argumentsSet('crop_type__links'));
  expectedArguments(\Drupal\crop\CropTypeInterface::hasLinkTemplate(), 0, argumentsSet('crop_type__links'));

  // Date format.
  registerArgumentsSet('date_format__links',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\Core\Datetime\Entity\DateFormat::toUrl(), 0, argumentsSet('date_format__links'));
  expectedArguments(\Drupal\Core\Datetime\Entity\DateFormat::toLink(), 1, argumentsSet('date_format__links'));
  expectedArguments(\Drupal\Core\Datetime\Entity\DateFormat::hasLinkTemplate(), 0, argumentsSet('date_format__links'));
  expectedArguments(\Drupal\Core\Datetime\DateFormatInterface::toUrl(), 0, argumentsSet('date_format__links'));
  expectedArguments(\Drupal\Core\Datetime\DateFormatInterface::toLink(), 1, argumentsSet('date_format__links'));
  expectedArguments(\Drupal\Core\Datetime\DateFormatInterface::hasLinkTemplate(), 0, argumentsSet('date_format__links'));

  // Microsite menu override.
  registerArgumentsSet('eh_microsite_menu_override__links',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverride::toUrl(), 0, argumentsSet('eh_microsite_menu_override__links'));
  expectedArguments(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverride::toLink(), 1, argumentsSet('eh_microsite_menu_override__links'));
  expectedArguments(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverride::hasLinkTemplate(), 0, argumentsSet('eh_microsite_menu_override__links'));

  // Embed button.
  registerArgumentsSet('embed_button__links',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\embed\Entity\EmbedButton::toUrl(), 0, argumentsSet('embed_button__links'));
  expectedArguments(\Drupal\embed\Entity\EmbedButton::toLink(), 1, argumentsSet('embed_button__links'));
  expectedArguments(\Drupal\embed\Entity\EmbedButton::hasLinkTemplate(), 0, argumentsSet('embed_button__links'));
  expectedArguments(\Drupal\embed\EmbedButtonInterface::toUrl(), 0, argumentsSet('embed_button__links'));
  expectedArguments(\Drupal\embed\EmbedButtonInterface::toLink(), 1, argumentsSet('embed_button__links'));
  expectedArguments(\Drupal\embed\EmbedButtonInterface::hasLinkTemplate(), 0, argumentsSet('embed_button__links'));

  // Entity browser.
  registerArgumentsSet('entity_browser__links',
    'canonical',
    'collection',
    'edit-form',
    'edit-widgets',
    'delete-form',
  );
  expectedArguments(\Drupal\entity_browser\Entity\EntityBrowser::toUrl(), 0, argumentsSet('entity_browser__links'));
  expectedArguments(\Drupal\entity_browser\Entity\EntityBrowser::toLink(), 1, argumentsSet('entity_browser__links'));
  expectedArguments(\Drupal\entity_browser\Entity\EntityBrowser::hasLinkTemplate(), 0, argumentsSet('entity_browser__links'));
  expectedArguments(\Drupal\entity_browser\EntityBrowserInterface::toUrl(), 0, argumentsSet('entity_browser__links'));
  expectedArguments(\Drupal\entity_browser\EntityBrowserInterface::toLink(), 1, argumentsSet('entity_browser__links'));
  expectedArguments(\Drupal\entity_browser\EntityBrowserInterface::hasLinkTemplate(), 0, argumentsSet('entity_browser__links'));

  // Form mode.
  registerArgumentsSet('entity_form_mode__links',
    'delete-form',
    'edit-form',
    'add-form',
    'collection',
  );
  expectedArguments(\Drupal\Core\Entity\Entity\EntityFormMode::toUrl(), 0, argumentsSet('entity_form_mode__links'));
  expectedArguments(\Drupal\Core\Entity\Entity\EntityFormMode::toLink(), 1, argumentsSet('entity_form_mode__links'));
  expectedArguments(\Drupal\Core\Entity\Entity\EntityFormMode::hasLinkTemplate(), 0, argumentsSet('entity_form_mode__links'));
  expectedArguments(\Drupal\Core\Entity\EntityFormModeInterface::toUrl(), 0, argumentsSet('entity_form_mode__links'));
  expectedArguments(\Drupal\Core\Entity\EntityFormModeInterface::toLink(), 1, argumentsSet('entity_form_mode__links'));
  expectedArguments(\Drupal\Core\Entity\EntityFormModeInterface::hasLinkTemplate(), 0, argumentsSet('entity_form_mode__links'));

  // Microsite.
  registerArgumentsSet('entity_hierarchy_microsite__links',
    'add-form',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\entity_hierarchy_microsite\Entity\Microsite::toUrl(), 0, argumentsSet('entity_hierarchy_microsite__links'));
  expectedArguments(\Drupal\entity_hierarchy_microsite\Entity\Microsite::toLink(), 1, argumentsSet('entity_hierarchy_microsite__links'));
  expectedArguments(\Drupal\entity_hierarchy_microsite\Entity\Microsite::hasLinkTemplate(), 0, argumentsSet('entity_hierarchy_microsite__links'));

  // View mode.
  registerArgumentsSet('entity_view_mode__links',
    'delete-form',
    'edit-form',
    'add-form',
    'collection',
  );
  expectedArguments(\Drupal\Core\Entity\Entity\EntityViewMode::toUrl(), 0, argumentsSet('entity_view_mode__links'));
  expectedArguments(\Drupal\Core\Entity\Entity\EntityViewMode::toLink(), 1, argumentsSet('entity_view_mode__links'));
  expectedArguments(\Drupal\Core\Entity\Entity\EntityViewMode::hasLinkTemplate(), 0, argumentsSet('entity_view_mode__links'));
  expectedArguments(\Drupal\Core\Entity\EntityViewModeInterface::toUrl(), 0, argumentsSet('entity_view_mode__links'));
  expectedArguments(\Drupal\Core\Entity\EntityViewModeInterface::toLink(), 1, argumentsSet('entity_view_mode__links'));
  expectedArguments(\Drupal\Core\Entity\EntityViewModeInterface::hasLinkTemplate(), 0, argumentsSet('entity_view_mode__links'));

  // Environment Switcher.
  registerArgumentsSet('environment_indicator__links',
    'edit-form',
    'edit-permissions-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\environment_indicator\Entity\EnvironmentIndicator::toUrl(), 0, argumentsSet('environment_indicator__links'));
  expectedArguments(\Drupal\environment_indicator\Entity\EnvironmentIndicator::toLink(), 1, argumentsSet('environment_indicator__links'));
  expectedArguments(\Drupal\environment_indicator\Entity\EnvironmentIndicator::hasLinkTemplate(), 0, argumentsSet('environment_indicator__links'));

  // Field storage.
  registerArgumentsSet('field_storage_config__links',
    'collection',
  );
  expectedArguments(\Drupal\field\Entity\FieldStorageConfig::toUrl(), 0, argumentsSet('field_storage_config__links'));
  expectedArguments(\Drupal\field\Entity\FieldStorageConfig::toLink(), 1, argumentsSet('field_storage_config__links'));
  expectedArguments(\Drupal\field\Entity\FieldStorageConfig::hasLinkTemplate(), 0, argumentsSet('field_storage_config__links'));
  expectedArguments(\Drupal\field\FieldStorageConfigInterface::toUrl(), 0, argumentsSet('field_storage_config__links'));
  expectedArguments(\Drupal\field\FieldStorageConfigInterface::toLink(), 1, argumentsSet('field_storage_config__links'));
  expectedArguments(\Drupal\field\FieldStorageConfigInterface::hasLinkTemplate(), 0, argumentsSet('field_storage_config__links'));

  // File.
  registerArgumentsSet('file__links',
    'delete-form',
  );
  expectedArguments(\Drupal\file\Entity\File::toUrl(), 0, argumentsSet('file__links'));
  expectedArguments(\Drupal\file\Entity\File::toLink(), 1, argumentsSet('file__links'));
  expectedArguments(\Drupal\file\Entity\File::hasLinkTemplate(), 0, argumentsSet('file__links'));
  expectedArguments(\Drupal\file\FileInterface::toUrl(), 0, argumentsSet('file__links'));
  expectedArguments(\Drupal\file\FileInterface::toLink(), 1, argumentsSet('file__links'));
  expectedArguments(\Drupal\file\FileInterface::hasLinkTemplate(), 0, argumentsSet('file__links'));

  // Text format.
  registerArgumentsSet('filter_format__links',
    'edit-form',
    'disable',
  );
  expectedArguments(\Drupal\filter\Entity\FilterFormat::toUrl(), 0, argumentsSet('filter_format__links'));
  expectedArguments(\Drupal\filter\Entity\FilterFormat::toLink(), 1, argumentsSet('filter_format__links'));
  expectedArguments(\Drupal\filter\Entity\FilterFormat::hasLinkTemplate(), 0, argumentsSet('filter_format__links'));
  expectedArguments(\Drupal\filter\FilterFormatInterface::toUrl(), 0, argumentsSet('filter_format__links'));
  expectedArguments(\Drupal\filter\FilterFormatInterface::toLink(), 1, argumentsSet('filter_format__links'));
  expectedArguments(\Drupal\filter\FilterFormatInterface::hasLinkTemplate(), 0, argumentsSet('filter_format__links'));

  // Flag.
  registerArgumentsSet('flag__links',
    'edit-form',
    'delete-form',
    'collection',
    'enable',
    'disable',
    'reset',
    'auto-label',
  );
  expectedArguments(\Drupal\flag\Entity\Flag::toUrl(), 0, argumentsSet('flag__links'));
  expectedArguments(\Drupal\flag\Entity\Flag::toLink(), 1, argumentsSet('flag__links'));
  expectedArguments(\Drupal\flag\Entity\Flag::hasLinkTemplate(), 0, argumentsSet('flag__links'));
  expectedArguments(\Drupal\flag\FlagInterface::toUrl(), 0, argumentsSet('flag__links'));
  expectedArguments(\Drupal\flag\FlagInterface::toLink(), 1, argumentsSet('flag__links'));
  expectedArguments(\Drupal\flag\FlagInterface::hasLinkTemplate(), 0, argumentsSet('flag__links'));

  // Flagging.
  registerArgumentsSet('flagging__links',
    'delete-form',
  );
  expectedArguments(\Drupal\flag\Entity\Flagging::toUrl(), 0, argumentsSet('flagging__links'));
  expectedArguments(\Drupal\flag\Entity\Flagging::toLink(), 1, argumentsSet('flagging__links'));
  expectedArguments(\Drupal\flag\Entity\Flagging::hasLinkTemplate(), 0, argumentsSet('flagging__links'));
  expectedArguments(\Drupal\flag\FlaggingInterface::toUrl(), 0, argumentsSet('flagging__links'));
  expectedArguments(\Drupal\flag\FlaggingInterface::toLink(), 1, argumentsSet('flagging__links'));
  expectedArguments(\Drupal\flag\FlaggingInterface::hasLinkTemplate(), 0, argumentsSet('flagging__links'));

  // Geocoder provider.
  registerArgumentsSet('geocoder_provider__links',
    'collection',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\geocoder\Entity\GeocoderProvider::toUrl(), 0, argumentsSet('geocoder_provider__links'));
  expectedArguments(\Drupal\geocoder\Entity\GeocoderProvider::toLink(), 1, argumentsSet('geocoder_provider__links'));
  expectedArguments(\Drupal\geocoder\Entity\GeocoderProvider::hasLinkTemplate(), 0, argumentsSet('geocoder_provider__links'));
  expectedArguments(\Drupal\geocoder\GeocoderProviderInterface::toUrl(), 0, argumentsSet('geocoder_provider__links'));
  expectedArguments(\Drupal\geocoder\GeocoderProviderInterface::toLink(), 1, argumentsSet('geocoder_provider__links'));
  expectedArguments(\Drupal\geocoder\GeocoderProviderInterface::hasLinkTemplate(), 0, argumentsSet('geocoder_provider__links'));

  // Google Tag Container.
  registerArgumentsSet('google_tag_container__links',
    'add-form',
    'edit-form',
    'delete-form',
    'enable',
    'disable',
    'collection',
  );
  expectedArguments(\Drupal\google_tag\Entity\TagContainer::toUrl(), 0, argumentsSet('google_tag_container__links'));
  expectedArguments(\Drupal\google_tag\Entity\TagContainer::toLink(), 1, argumentsSet('google_tag_container__links'));
  expectedArguments(\Drupal\google_tag\Entity\TagContainer::hasLinkTemplate(), 0, argumentsSet('google_tag_container__links'));

  // Image style.
  registerArgumentsSet('image_style__links',
    'flush-form',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\mass_utility\EntityAlter\ImageStyle::toUrl(), 0, argumentsSet('image_style__links'));
  expectedArguments(\Drupal\mass_utility\EntityAlter\ImageStyle::toLink(), 1, argumentsSet('image_style__links'));
  expectedArguments(\Drupal\mass_utility\EntityAlter\ImageStyle::hasLinkTemplate(), 0, argumentsSet('image_style__links'));

  // Key.
  registerArgumentsSet('key__links',
    'add-form',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\key\Entity\Key::toUrl(), 0, argumentsSet('key__links'));
  expectedArguments(\Drupal\key\Entity\Key::toLink(), 1, argumentsSet('key__links'));
  expectedArguments(\Drupal\key\Entity\Key::hasLinkTemplate(), 0, argumentsSet('key__links'));
  expectedArguments(\Drupal\key\KeyInterface::toUrl(), 0, argumentsSet('key__links'));
  expectedArguments(\Drupal\key\KeyInterface::toLink(), 1, argumentsSet('key__links'));
  expectedArguments(\Drupal\key\KeyInterface::hasLinkTemplate(), 0, argumentsSet('key__links'));

  // Key Configuration Override.
  registerArgumentsSet('key_config_override__links',
    'add-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\key\Entity\KeyConfigOverride::toUrl(), 0, argumentsSet('key_config_override__links'));
  expectedArguments(\Drupal\key\Entity\KeyConfigOverride::toLink(), 1, argumentsSet('key_config_override__links'));
  expectedArguments(\Drupal\key\Entity\KeyConfigOverride::hasLinkTemplate(), 0, argumentsSet('key_config_override__links'));
  expectedArguments(\Drupal\key\KeyConfigOverrideInterface::toUrl(), 0, argumentsSet('key_config_override__links'));
  expectedArguments(\Drupal\key\KeyConfigOverrideInterface::toLink(), 1, argumentsSet('key_config_override__links'));
  expectedArguments(\Drupal\key\KeyConfigOverrideInterface::hasLinkTemplate(), 0, argumentsSet('key_config_override__links'));

  // Linkit profile.
  registerArgumentsSet('linkit_profile__links',
    'collection',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\linkit\Entity\Profile::toUrl(), 0, argumentsSet('linkit_profile__links'));
  expectedArguments(\Drupal\linkit\Entity\Profile::toLink(), 1, argumentsSet('linkit_profile__links'));
  expectedArguments(\Drupal\linkit\Entity\Profile::hasLinkTemplate(), 0, argumentsSet('linkit_profile__links'));
  expectedArguments(\Drupal\linkit\ProfileInterface::toUrl(), 0, argumentsSet('linkit_profile__links'));
  expectedArguments(\Drupal\linkit\ProfileInterface::toLink(), 1, argumentsSet('linkit_profile__links'));
  expectedArguments(\Drupal\linkit\ProfileInterface::hasLinkTemplate(), 0, argumentsSet('linkit_profile__links'));

  // Mailchimp Transactional Template Map.
  registerArgumentsSet('mailchimp_transactional_template__links',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\mailchimp_transactional_template\Entity\TemplateMap::toUrl(), 0, argumentsSet('mailchimp_transactional_template__links'));
  expectedArguments(\Drupal\mailchimp_transactional_template\Entity\TemplateMap::toLink(), 1, argumentsSet('mailchimp_transactional_template__links'));
  expectedArguments(\Drupal\mailchimp_transactional_template\Entity\TemplateMap::hasLinkTemplate(), 0, argumentsSet('mailchimp_transactional_template__links'));

  // Media.
  registerArgumentsSet('media__links',
    'add-page',
    'add-form',
    'canonical',
    'collection',
    'delete-form',
    'delete-multiple-form',
    'edit-form',
    'revision',
    'revision-delete-form',
    'revision-revert-form',
    'version-history',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
    'latest-version',
    'drupal:content-translation-overview',
    'drupal:content-translation-add',
    'drupal:content-translation-edit',
    'drupal:content-translation-delete',
  );
  expectedArguments(\Drupal\media\Entity\Media::toUrl(), 0, argumentsSet('media__links'));
  expectedArguments(\Drupal\media\Entity\Media::toLink(), 1, argumentsSet('media__links'));
  expectedArguments(\Drupal\media\Entity\Media::hasLinkTemplate(), 0, argumentsSet('media__links'));
  expectedArguments(\Drupal\media\MediaInterface::toUrl(), 0, argumentsSet('media__links'));
  expectedArguments(\Drupal\media\MediaInterface::toLink(), 1, argumentsSet('media__links'));
  expectedArguments(\Drupal\media\MediaInterface::hasLinkTemplate(), 0, argumentsSet('media__links'));

  // Media type.
  registerArgumentsSet('media_type__links',
    'add-form',
    'edit-form',
    'delete-form',
    'entity-permissions-form',
    'collection',
    'auto-label',
  );
  expectedArguments(\Drupal\media\Entity\MediaType::toUrl(), 0, argumentsSet('media_type__links'));
  expectedArguments(\Drupal\media\Entity\MediaType::toLink(), 1, argumentsSet('media_type__links'));
  expectedArguments(\Drupal\media\Entity\MediaType::hasLinkTemplate(), 0, argumentsSet('media_type__links'));
  expectedArguments(\Drupal\media\MediaTypeInterface::toUrl(), 0, argumentsSet('media_type__links'));
  expectedArguments(\Drupal\media\MediaTypeInterface::toLink(), 1, argumentsSet('media_type__links'));
  expectedArguments(\Drupal\media\MediaTypeInterface::hasLinkTemplate(), 0, argumentsSet('media_type__links'));

  // Menu.
  registerArgumentsSet('menu__links',
    'add-form',
    'delete-form',
    'edit-form',
    'add-link-form',
    'collection',
  );
  expectedArguments(\Drupal\system\Entity\Menu::toUrl(), 0, argumentsSet('menu__links'));
  expectedArguments(\Drupal\system\Entity\Menu::toLink(), 1, argumentsSet('menu__links'));
  expectedArguments(\Drupal\system\Entity\Menu::hasLinkTemplate(), 0, argumentsSet('menu__links'));
  expectedArguments(\Drupal\system\MenuInterface::toUrl(), 0, argumentsSet('menu__links'));
  expectedArguments(\Drupal\system\MenuInterface::toLink(), 1, argumentsSet('menu__links'));
  expectedArguments(\Drupal\system\MenuInterface::hasLinkTemplate(), 0, argumentsSet('menu__links'));

  // Custom menu link.
  registerArgumentsSet('menu_link_content__links',
    'canonical',
    'edit-form',
    'delete-form',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
    'latest-version',
    'drupal:content-translation-overview',
    'drupal:content-translation-add',
    'drupal:content-translation-edit',
    'drupal:content-translation-delete',
  );
  expectedArguments(\Drupal\menu_link_content\Entity\MenuLinkContent::toUrl(), 0, argumentsSet('menu_link_content__links'));
  expectedArguments(\Drupal\menu_link_content\Entity\MenuLinkContent::toLink(), 1, argumentsSet('menu_link_content__links'));
  expectedArguments(\Drupal\menu_link_content\Entity\MenuLinkContent::hasLinkTemplate(), 0, argumentsSet('menu_link_content__links'));
  expectedArguments(\Drupal\menu_link_content\MenuLinkContentInterface::toUrl(), 0, argumentsSet('menu_link_content__links'));
  expectedArguments(\Drupal\menu_link_content\MenuLinkContentInterface::toLink(), 1, argumentsSet('menu_link_content__links'));
  expectedArguments(\Drupal\menu_link_content\MenuLinkContentInterface::hasLinkTemplate(), 0, argumentsSet('menu_link_content__links'));

  // Metatag defaults.
  registerArgumentsSet('metatag_defaults__links',
    'edit-form',
    'delete-form',
    'revert-form',
    'collection',
  );
  expectedArguments(\Drupal\metatag\Entity\MetatagDefaults::toUrl(), 0, argumentsSet('metatag_defaults__links'));
  expectedArguments(\Drupal\metatag\Entity\MetatagDefaults::toLink(), 1, argumentsSet('metatag_defaults__links'));
  expectedArguments(\Drupal\metatag\Entity\MetatagDefaults::hasLinkTemplate(), 0, argumentsSet('metatag_defaults__links'));
  expectedArguments(\Drupal\metatag\MetatagDefaultsInterface::toUrl(), 0, argumentsSet('metatag_defaults__links'));
  expectedArguments(\Drupal\metatag\MetatagDefaultsInterface::toLink(), 1, argumentsSet('metatag_defaults__links'));
  expectedArguments(\Drupal\metatag\MetatagDefaultsInterface::hasLinkTemplate(), 0, argumentsSet('metatag_defaults__links'));

  // Content.
  registerArgumentsSet('node__links',
    'canonical',
    'delete-form',
    'delete-multiple-form',
    'edit-form',
    'version-history',
    'revision',
    'create',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
    'latest-version',
    'redirects',
    'drupal:content-translation-overview',
    'drupal:content-translation-add',
    'drupal:content-translation-edit',
    'drupal:content-translation-delete',
  );
  expectedArguments(\Drupal\node\Entity\Node::toUrl(), 0, argumentsSet('node__links'));
  expectedArguments(\Drupal\node\Entity\Node::toLink(), 1, argumentsSet('node__links'));
  expectedArguments(\Drupal\node\Entity\Node::hasLinkTemplate(), 0, argumentsSet('node__links'));
  expectedArguments(\Drupal\node\NodeInterface::toUrl(), 0, argumentsSet('node__links'));
  expectedArguments(\Drupal\node\NodeInterface::toLink(), 1, argumentsSet('node__links'));
  expectedArguments(\Drupal\node\NodeInterface::hasLinkTemplate(), 0, argumentsSet('node__links'));

  // Content type.
  registerArgumentsSet('node_type__links',
    'edit-form',
    'delete-form',
    'entity-permissions-form',
    'collection',
    'auto-label',
  );
  expectedArguments(\Drupal\node\Entity\NodeType::toUrl(), 0, argumentsSet('node_type__links'));
  expectedArguments(\Drupal\node\Entity\NodeType::toLink(), 1, argumentsSet('node_type__links'));
  expectedArguments(\Drupal\node\Entity\NodeType::hasLinkTemplate(), 0, argumentsSet('node_type__links'));
  expectedArguments(\Drupal\node\NodeTypeInterface::toUrl(), 0, argumentsSet('node_type__links'));
  expectedArguments(\Drupal\node\NodeTypeInterface::toLink(), 1, argumentsSet('node_type__links'));
  expectedArguments(\Drupal\node\NodeTypeInterface::hasLinkTemplate(), 0, argumentsSet('node_type__links'));

  // OpenID Connect client.
  registerArgumentsSet('openid_connect_client__links',
    'edit-form',
    'delete-form',
    'enable',
    'disable',
    'collection',
  );
  expectedArguments(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::toUrl(), 0, argumentsSet('openid_connect_client__links'));
  expectedArguments(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::toLink(), 1, argumentsSet('openid_connect_client__links'));
  expectedArguments(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::hasLinkTemplate(), 0, argumentsSet('openid_connect_client__links'));
  expectedArguments(\Drupal\openid_connect\OpenIDConnectClientEntityInterface::toUrl(), 0, argumentsSet('openid_connect_client__links'));
  expectedArguments(\Drupal\openid_connect\OpenIDConnectClientEntityInterface::toLink(), 1, argumentsSet('openid_connect_client__links'));
  expectedArguments(\Drupal\openid_connect\OpenIDConnectClientEntityInterface::hasLinkTemplate(), 0, argumentsSet('openid_connect_client__links'));

  // Paragraphs library item.
  registerArgumentsSet('paragraphs_library_item__links',
    'add-form',
    'edit-form',
    'delete-form',
    'collection',
    'canonical',
    'revision',
    'revision-revert',
    'revision-delete',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
    'latest-version',
    'drupal:content-translation-overview',
    'drupal:content-translation-add',
    'drupal:content-translation-edit',
    'drupal:content-translation-delete',
  );
  expectedArguments(\Drupal\paragraphs_library\Entity\LibraryItem::toUrl(), 0, argumentsSet('paragraphs_library_item__links'));
  expectedArguments(\Drupal\paragraphs_library\Entity\LibraryItem::toLink(), 1, argumentsSet('paragraphs_library_item__links'));
  expectedArguments(\Drupal\paragraphs_library\Entity\LibraryItem::hasLinkTemplate(), 0, argumentsSet('paragraphs_library_item__links'));
  expectedArguments(\Drupal\paragraphs_library\LibraryItemInterface::toUrl(), 0, argumentsSet('paragraphs_library_item__links'));
  expectedArguments(\Drupal\paragraphs_library\LibraryItemInterface::toLink(), 1, argumentsSet('paragraphs_library_item__links'));
  expectedArguments(\Drupal\paragraphs_library\LibraryItemInterface::hasLinkTemplate(), 0, argumentsSet('paragraphs_library_item__links'));

  // Paragraphs type.
  registerArgumentsSet('paragraphs_type__links',
    'edit-form',
    'delete-form',
    'collection',
    'auto-label',
  );
  expectedArguments(\Drupal\paragraphs\Entity\ParagraphsType::toUrl(), 0, argumentsSet('paragraphs_type__links'));
  expectedArguments(\Drupal\paragraphs\Entity\ParagraphsType::toLink(), 1, argumentsSet('paragraphs_type__links'));
  expectedArguments(\Drupal\paragraphs\Entity\ParagraphsType::hasLinkTemplate(), 0, argumentsSet('paragraphs_type__links'));
  expectedArguments(\Drupal\paragraphs\ParagraphsTypeInterface::toUrl(), 0, argumentsSet('paragraphs_type__links'));
  expectedArguments(\Drupal\paragraphs\ParagraphsTypeInterface::toLink(), 1, argumentsSet('paragraphs_type__links'));
  expectedArguments(\Drupal\paragraphs\ParagraphsTypeInterface::hasLinkTemplate(), 0, argumentsSet('paragraphs_type__links'));

  // URL alias.
  registerArgumentsSet('path_alias__links',
    'collection',
    'add-form',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\path_alias\Entity\PathAlias::toUrl(), 0, argumentsSet('path_alias__links'));
  expectedArguments(\Drupal\path_alias\Entity\PathAlias::toLink(), 1, argumentsSet('path_alias__links'));
  expectedArguments(\Drupal\path_alias\Entity\PathAlias::hasLinkTemplate(), 0, argumentsSet('path_alias__links'));
  expectedArguments(\Drupal\path_alias\PathAliasInterface::toUrl(), 0, argumentsSet('path_alias__links'));
  expectedArguments(\Drupal\path_alias\PathAliasInterface::toLink(), 1, argumentsSet('path_alias__links'));
  expectedArguments(\Drupal\path_alias\PathAliasInterface::hasLinkTemplate(), 0, argumentsSet('path_alias__links'));

  // Pathauto pattern.
  registerArgumentsSet('pathauto_pattern__links',
    'collection',
    'edit-form',
    'delete-form',
    'enable',
    'disable',
    'duplicate-form',
  );
  expectedArguments(\Drupal\pathauto\Entity\PathautoPattern::toUrl(), 0, argumentsSet('pathauto_pattern__links'));
  expectedArguments(\Drupal\pathauto\Entity\PathautoPattern::toLink(), 1, argumentsSet('pathauto_pattern__links'));
  expectedArguments(\Drupal\pathauto\Entity\PathautoPattern::hasLinkTemplate(), 0, argumentsSet('pathauto_pattern__links'));
  expectedArguments(\Drupal\pathauto\PathautoPatternInterface::toUrl(), 0, argumentsSet('pathauto_pattern__links'));
  expectedArguments(\Drupal\pathauto\PathautoPatternInterface::toLink(), 1, argumentsSet('pathauto_pattern__links'));
  expectedArguments(\Drupal\pathauto\PathautoPatternInterface::hasLinkTemplate(), 0, argumentsSet('pathauto_pattern__links'));

  // Private files download permission directory.
  registerArgumentsSet('pfdp_directory__links',
    'edit',
    'delete',
  );
  expectedArguments(\Drupal\pfdp\Entity\DirectoryEntity::toUrl(), 0, argumentsSet('pfdp_directory__links'));
  expectedArguments(\Drupal\pfdp\Entity\DirectoryEntity::toLink(), 1, argumentsSet('pfdp_directory__links'));
  expectedArguments(\Drupal\pfdp\Entity\DirectoryEntity::hasLinkTemplate(), 0, argumentsSet('pfdp_directory__links'));

  // Redirect.
  registerArgumentsSet('redirect__links',
    'canonical',
    'delete-form',
    'edit-form',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
  );
  expectedArguments(\Drupal\redirect\Entity\Redirect::toUrl(), 0, argumentsSet('redirect__links'));
  expectedArguments(\Drupal\redirect\Entity\Redirect::toLink(), 1, argumentsSet('redirect__links'));
  expectedArguments(\Drupal\redirect\Entity\Redirect::hasLinkTemplate(), 0, argumentsSet('redirect__links'));

  // Response Header.
  registerArgumentsSet('response_header__links',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\http_response_headers\Entity\ResponseHeader::toUrl(), 0, argumentsSet('response_header__links'));
  expectedArguments(\Drupal\http_response_headers\Entity\ResponseHeader::toLink(), 1, argumentsSet('response_header__links'));
  expectedArguments(\Drupal\http_response_headers\Entity\ResponseHeader::hasLinkTemplate(), 0, argumentsSet('response_header__links'));
  expectedArguments(\Drupal\http_response_headers\ResponseHeaderInterface::toUrl(), 0, argumentsSet('response_header__links'));
  expectedArguments(\Drupal\http_response_headers\ResponseHeaderInterface::toLink(), 1, argumentsSet('response_header__links'));
  expectedArguments(\Drupal\http_response_headers\ResponseHeaderInterface::hasLinkTemplate(), 0, argumentsSet('response_header__links'));

  // Responsive image style.
  registerArgumentsSet('responsive_image_style__links',
    'edit-form',
    'duplicate-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\responsive_image\Entity\ResponsiveImageStyle::toUrl(), 0, argumentsSet('responsive_image_style__links'));
  expectedArguments(\Drupal\responsive_image\Entity\ResponsiveImageStyle::toLink(), 1, argumentsSet('responsive_image_style__links'));
  expectedArguments(\Drupal\responsive_image\Entity\ResponsiveImageStyle::hasLinkTemplate(), 0, argumentsSet('responsive_image_style__links'));
  expectedArguments(\Drupal\responsive_image\ResponsiveImageStyleInterface::toUrl(), 0, argumentsSet('responsive_image_style__links'));
  expectedArguments(\Drupal\responsive_image\ResponsiveImageStyleInterface::toLink(), 1, argumentsSet('responsive_image_style__links'));
  expectedArguments(\Drupal\responsive_image\ResponsiveImageStyleInterface::hasLinkTemplate(), 0, argumentsSet('responsive_image_style__links'));

  // scheduled transition.
  registerArgumentsSet('scheduled_transition__links',
    'collection',
    'delete-form',
    'reschedule-form',
  );
  expectedArguments(\Drupal\scheduled_transitions\Entity\ScheduledTransition::toUrl(), 0, argumentsSet('scheduled_transition__links'));
  expectedArguments(\Drupal\scheduled_transitions\Entity\ScheduledTransition::toLink(), 1, argumentsSet('scheduled_transition__links'));
  expectedArguments(\Drupal\scheduled_transitions\Entity\ScheduledTransition::hasLinkTemplate(), 0, argumentsSet('scheduled_transition__links'));

  // Sitemap.
  registerArgumentsSet('simple_sitemap__links',
    'add-form',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\simple_sitemap\Entity\SimpleSitemap::toUrl(), 0, argumentsSet('simple_sitemap__links'));
  expectedArguments(\Drupal\simple_sitemap\Entity\SimpleSitemap::toLink(), 1, argumentsSet('simple_sitemap__links'));
  expectedArguments(\Drupal\simple_sitemap\Entity\SimpleSitemap::hasLinkTemplate(), 0, argumentsSet('simple_sitemap__links'));

  // Simple XML sitemap type.
  registerArgumentsSet('simple_sitemap_type__links',
    'add-form',
    'edit-form',
    'delete-form',
    'collection',
  );
  expectedArguments(\Drupal\simple_sitemap\Entity\SimpleSitemapType::toUrl(), 0, argumentsSet('simple_sitemap_type__links'));
  expectedArguments(\Drupal\simple_sitemap\Entity\SimpleSitemapType::toLink(), 1, argumentsSet('simple_sitemap_type__links'));
  expectedArguments(\Drupal\simple_sitemap\Entity\SimpleSitemapType::hasLinkTemplate(), 0, argumentsSet('simple_sitemap_type__links'));

  // Taxonomy term.
  registerArgumentsSet('taxonomy_term__links',
    'canonical',
    'delete-form',
    'edit-form',
    'create',
    'revision',
    'revision-delete-form',
    'revision-revert-form',
    'version-history',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
    'latest-version',
    'drupal:content-translation-overview',
    'drupal:content-translation-add',
    'drupal:content-translation-edit',
    'drupal:content-translation-delete',
  );
  expectedArguments(\Drupal\taxonomy\Entity\Term::toUrl(), 0, argumentsSet('taxonomy_term__links'));
  expectedArguments(\Drupal\taxonomy\Entity\Term::toLink(), 1, argumentsSet('taxonomy_term__links'));
  expectedArguments(\Drupal\taxonomy\Entity\Term::hasLinkTemplate(), 0, argumentsSet('taxonomy_term__links'));
  expectedArguments(\Drupal\taxonomy\TermInterface::toUrl(), 0, argumentsSet('taxonomy_term__links'));
  expectedArguments(\Drupal\taxonomy\TermInterface::toLink(), 1, argumentsSet('taxonomy_term__links'));
  expectedArguments(\Drupal\taxonomy\TermInterface::hasLinkTemplate(), 0, argumentsSet('taxonomy_term__links'));

  // Taxonomy vocabulary.
  registerArgumentsSet('taxonomy_vocabulary__links',
    'add-form',
    'delete-form',
    'reset-form',
    'overview-form',
    'edit-form',
    'entity-permissions-form',
    'collection',
    'auto-label',
  );
  expectedArguments(\Drupal\taxonomy\Entity\Vocabulary::toUrl(), 0, argumentsSet('taxonomy_vocabulary__links'));
  expectedArguments(\Drupal\taxonomy\Entity\Vocabulary::toLink(), 1, argumentsSet('taxonomy_vocabulary__links'));
  expectedArguments(\Drupal\taxonomy\Entity\Vocabulary::hasLinkTemplate(), 0, argumentsSet('taxonomy_vocabulary__links'));
  expectedArguments(\Drupal\taxonomy\VocabularyInterface::toUrl(), 0, argumentsSet('taxonomy_vocabulary__links'));
  expectedArguments(\Drupal\taxonomy\VocabularyInterface::toLink(), 1, argumentsSet('taxonomy_vocabulary__links'));
  expectedArguments(\Drupal\taxonomy\VocabularyInterface::hasLinkTemplate(), 0, argumentsSet('taxonomy_vocabulary__links'));

  // User.
  registerArgumentsSet('user__links',
    'canonical',
    'edit-form',
    'cancel-form',
    'collection',
    'entity_hierarchy_reorder',
    'scheduled_transitions',
    'scheduled_transition_add',
    'contact-form',
    'drupal:content-translation-overview',
    'drupal:content-translation-add',
    'drupal:content-translation-edit',
    'drupal:content-translation-delete',
  );
  expectedArguments(\Drupal\user\Entity\User::toUrl(), 0, argumentsSet('user__links'));
  expectedArguments(\Drupal\user\Entity\User::toLink(), 1, argumentsSet('user__links'));
  expectedArguments(\Drupal\user\Entity\User::hasLinkTemplate(), 0, argumentsSet('user__links'));
  expectedArguments(\Drupal\user\UserInterface::toUrl(), 0, argumentsSet('user__links'));
  expectedArguments(\Drupal\user\UserInterface::toLink(), 1, argumentsSet('user__links'));
  expectedArguments(\Drupal\user\UserInterface::hasLinkTemplate(), 0, argumentsSet('user__links'));

  // Role.
  registerArgumentsSet('user_role__links',
    'delete-form',
    'edit-form',
    'edit-permissions-form',
    'collection',
  );
  expectedArguments(\Drupal\user\Entity\Role::toUrl(), 0, argumentsSet('user_role__links'));
  expectedArguments(\Drupal\user\Entity\Role::toLink(), 1, argumentsSet('user_role__links'));
  expectedArguments(\Drupal\user\Entity\Role::hasLinkTemplate(), 0, argumentsSet('user_role__links'));
  expectedArguments(\Drupal\user\RoleInterface::toUrl(), 0, argumentsSet('user_role__links'));
  expectedArguments(\Drupal\user\RoleInterface::toLink(), 1, argumentsSet('user_role__links'));
  expectedArguments(\Drupal\user\RoleInterface::hasLinkTemplate(), 0, argumentsSet('user_role__links'));

  // View.
  registerArgumentsSet('view__links',
    'edit-form',
    'edit-display-form',
    'preview-form',
    'duplicate-form',
    'delete-form',
    'enable',
    'disable',
    'break-lock-form',
    'collection',
  );
  expectedArguments(\Drupal\views\Entity\View::toUrl(), 0, argumentsSet('view__links'));
  expectedArguments(\Drupal\views\Entity\View::toLink(), 1, argumentsSet('view__links'));
  expectedArguments(\Drupal\views\Entity\View::hasLinkTemplate(), 0, argumentsSet('view__links'));

  // Viewmodepage pattern.
  registerArgumentsSet('view_mode_page_pattern__links',
    'collection',
    'edit-form',
    'delete-form',
  );
  expectedArguments(\Drupal\view_mode_page\Entity\ViewmodepagePattern::toUrl(), 0, argumentsSet('view_mode_page_pattern__links'));
  expectedArguments(\Drupal\view_mode_page\Entity\ViewmodepagePattern::toLink(), 1, argumentsSet('view_mode_page_pattern__links'));
  expectedArguments(\Drupal\view_mode_page\Entity\ViewmodepagePattern::hasLinkTemplate(), 0, argumentsSet('view_mode_page_pattern__links'));
  expectedArguments(\Drupal\view_mode_page\ViewmodepagePatternInterface::toUrl(), 0, argumentsSet('view_mode_page_pattern__links'));
  expectedArguments(\Drupal\view_mode_page\ViewmodepagePatternInterface::toLink(), 1, argumentsSet('view_mode_page_pattern__links'));
  expectedArguments(\Drupal\view_mode_page\ViewmodepagePatternInterface::hasLinkTemplate(), 0, argumentsSet('view_mode_page_pattern__links'));

  // Workflow.
  registerArgumentsSet('workflow__links',
    'add-form',
    'edit-form',
    'delete-form',
    'add-state-form',
    'add-transition-form',
    'collection',
  );
  expectedArguments(\Drupal\workflows\Entity\Workflow::toUrl(), 0, argumentsSet('workflow__links'));
  expectedArguments(\Drupal\workflows\Entity\Workflow::toLink(), 1, argumentsSet('workflow__links'));
  expectedArguments(\Drupal\workflows\Entity\Workflow::hasLinkTemplate(), 0, argumentsSet('workflow__links'));
  expectedArguments(\Drupal\workflows\WorkflowInterface::toUrl(), 0, argumentsSet('workflow__links'));
  expectedArguments(\Drupal\workflows\WorkflowInterface::toLink(), 1, argumentsSet('workflow__links'));
  expectedArguments(\Drupal\workflows\WorkflowInterface::hasLinkTemplate(), 0, argumentsSet('workflow__links'));

}
