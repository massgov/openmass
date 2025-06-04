<?php

declare(strict_types=1);

namespace PHPSTORM_META {

  // Entity types.
  registerArgumentsSet('entity_type_ids',
    'access_token',
    'action',
    'base_field_override',
    'behavior_settings',
    'block',
    'configurable_language',
    'contact_form',
    'contact_message',
    'content_moderation_state',
    'crop',
    'crop_type',
    'date_format',
    'editor',
    'eh_microsite_menu_override',
    'embed_button',
    'entity_browser',
    'entity_embed_fake_entity',
    'entity_form_display',
    'entity_form_mode',
    'entity_hierarchy_microsite',
    'entity_view_display',
    'entity_view_mode',
    'environment_indicator',
    'field_config',
    'field_storage_config',
    'file',
    'filter_format',
    'flag',
    'flagging',
    'geocoder_provider',
    'google_tag_container',
    'image_style',
    'key',
    'key_config_override',
    'language_content_settings',
    'linkit_profile',
    'mailchimp_transactional_template',
    'media',
    'media_type',
    'menu',
    'menu_link_content',
    'metatag_defaults',
    'node',
    'node_type',
    'openid_connect_client',
    'paragraph',
    'paragraphs_library_item',
    'paragraphs_type',
    'path_alias',
    'pathauto_pattern',
    'pfdp_directory',
    'redirect',
    'response_header',
    'responsive_image_style',
    'rest_resource_config',
    'scheduled_transition',
    'simple_sitemap',
    'simple_sitemap_type',
    'taxonomy_term',
    'taxonomy_vocabulary',
    'user',
    'user_ref_access',
    'user_role',
    'view',
    'view_mode_page_pattern',
    'workflow',
  );
  expectedArguments(\Drupal\KernelTests\KernelTestBase::installEntitySchema(), 0, argumentsSet('entity_type_ids'));
  expectedReturnValues(\Drupal\Core\Entity\EntityInterface::getEntityTypeId(), argumentsSet('entity_type_ids'));

  // Storages.
  override(
    \Drupal\Core\Entity\EntityTypeManagerInterface::getStorage(0),
    map([
      'access_token' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'action' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'base_field_override' => '\Drupal\Core\Field\BaseFieldOverrideStorage',
      'behavior_settings' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'block' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'configurable_language' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'contact_form' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'contact_message' => '\Drupal\Core\Entity\ContentEntityNullStorage',
      'content_moderation_state' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'crop' => '\Drupal\crop\CropStorageInterface',
      'crop_type' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'date_format' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'editor' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'eh_microsite_menu_override' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'embed_button' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'entity_browser' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'entity_embed_fake_entity' => '\Drupal\Core\Entity\ContentEntityNullStorage',
      'entity_form_display' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'entity_form_mode' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'entity_hierarchy_microsite' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'entity_view_display' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'entity_view_mode' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'environment_indicator' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'field_config' => '\Drupal\field\FieldConfigStorage',
      'field_storage_config' => '\Drupal\field\FieldStorageConfigStorage',
      'file' => '\Drupal\file\FileStorageInterface',
      'filter_format' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'flag' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'flagging' => '\Drupal\flag\Entity\Storage\FlaggingStorageInterface',
      'geocoder_provider' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'google_tag_container' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'image_style' => '\Drupal\image\ImageStyleStorageInterface',
      'key' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'key_config_override' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'language_content_settings' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'linkit_profile' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'mailchimp_transactional_template' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'media' => '\Drupal\media\MediaStorage',
      'media_type' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'menu' => '\Drupal\system\MenuStorage',
      'menu_link_content' => '\Drupal\menu_link_content\MenuLinkContentStorageInterface',
      'metatag_defaults' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'node' => '\Drupal\node\NodeStorageInterface',
      'node_type' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'openid_connect_client' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'paragraph' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'paragraphs_library_item' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'paragraphs_type' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'path_alias' => '\Drupal\path_alias\PathAliasStorage',
      'pathauto_pattern' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'pfdp_directory' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'redirect' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'response_header' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'responsive_image_style' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'rest_resource_config' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'scheduled_transition' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'simple_sitemap' => '\Drupal\simple_sitemap\Entity\SimpleSitemapStorage',
      'simple_sitemap_type' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeStorage',
      'taxonomy_term' => '\Drupal\taxonomy\TermStorageInterface',
      'taxonomy_vocabulary' => '\Drupal\taxonomy\VocabularyStorageInterface',
      'user' => '\Drupal\user\UserStorageInterface',
      'user_ref_access' => '\Drupal\Core\Entity\Sql\SqlContentEntityStorage',
      'user_role' => '\Drupal\user\RoleStorageInterface',
      'view' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'view_mode_page_pattern' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
      'workflow' => '\Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
    ]),
  );

  // View builders.
  override(
    \Drupal\Core\Entity\EntityTypeManagerInterface::getViewBuilder(0),
    map([
      'access_token' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'block' => '\Drupal\block\BlockViewBuilder',
      'contact_message' => '\Drupal\contact\MessageViewBuilder',
      'content_moderation_state' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'crop' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'eh_microsite_menu_override' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'entity_embed_fake_entity' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'entity_hierarchy_microsite' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'file' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'flagging' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'media' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'menu_link_content' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'node' => '\Drupal\node\NodeViewBuilder',
      'paragraph' => '\Drupal\paragraphs\ParagraphViewBuilder',
      'paragraphs_library_item' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'path_alias' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'redirect' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'scheduled_transition' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'taxonomy_term' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'user' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
      'user_ref_access' => '\Drupal\Core\Entity\EntityViewBuilderInterface',
    ]),
  );

  // List builders.
  override(
    \Drupal\Core\Entity\EntityTypeManagerInterface::getListBuilder(0),
    map([
      'access_token' => '\Drupal\access_unpublished\AccessTokenListBuilder',
      'action' => '\Drupal\action\ActionListBuilder',
      'block' => '\Drupal\block\BlockListBuilder',
      'configurable_language' => '\Drupal\language\LanguageListBuilder',
      'contact_form' => '\Drupal\contact\ContactFormListBuilder',
      'crop_type' => '\Drupal\crop\CropTypeListBuilder',
      'date_format' => '\Drupal\system\DateFormatListBuilder',
      'embed_button' => '\Drupal\embed\EmbedButtonListBuilder',
      'entity_browser' => '\Drupal\entity_browser\Controllers\EntityBrowserListBuilder',
      'entity_form_mode' => '\Drupal\field_ui\EntityFormModeListBuilder',
      'entity_hierarchy_microsite' => '\Drupal\entity_hierarchy_microsite\MicrositeListBuilder',
      'entity_view_mode' => '\Drupal\field_ui\EntityDisplayModeListBuilder',
      'environment_indicator' => '\Drupal\environment_indicator\EnvironmentIndicatorListBuilder',
      'field_config' => '\Drupal\field_ui\FieldConfigListBuilder',
      'field_storage_config' => '\Drupal\field_ui\FieldStorageConfigListBuilder',
      'file' => '\Drupal\Core\Entity\EntityListBuilderInterface',
      'filter_format' => '\Drupal\filter\FilterFormatListBuilder',
      'flag' => '\Drupal\flag\Controller\FlagListBuilder',
      'geocoder_provider' => '\Drupal\geocoder\GeocoderProviderListBuilder',
      'google_tag_container' => '\Drupal\google_tag\TagContainerListBuilder',
      'image_style' => '\Drupal\image\ImageStyleListBuilder',
      'key' => '\Drupal\key\Controller\KeyListBuilder',
      'key_config_override' => '\Drupal\key\Controller\KeyConfigOverrideListBuilder',
      'linkit_profile' => '\Drupal\linkit\ProfileListBuilder',
      'mailchimp_transactional_template' => '\Drupal\mailchimp_transactional_template\Controller\TemplateMapListBuilder',
      'media' => '\Drupal\media\MediaListBuilder',
      'media_type' => '\Drupal\media\MediaTypeListBuilder',
      'menu' => '\Drupal\menu_ui\MenuListBuilder',
      'menu_link_content' => '\Drupal\menu_link_content\MenuLinkListBuilder',
      'metatag_defaults' => '\Drupal\metatag\MetatagDefaultsListBuilder',
      'node' => '\Drupal\node\NodeListBuilder',
      'node_type' => '\Drupal\node\NodeTypeListBuilder',
      'openid_connect_client' => '\Drupal\openid_connect\Controller\OpenIDConnectClientListBuilder',
      'paragraphs_library_item' => '\Drupal\Core\Entity\EntityListBuilderInterface',
      'paragraphs_type' => '\Drupal\paragraphs\Controller\ParagraphsTypeListBuilder',
      'path_alias' => '\Drupal\path\PathAliasListBuilder',
      'pathauto_pattern' => '\Drupal\pathauto\PathautoPatternListBuilder',
      'pfdp_directory' => '\Drupal\pfdp\DirectoryListBuilder',
      'redirect' => '\Drupal\Core\Entity\EntityListBuilderInterface',
      'response_header' => '\Drupal\http_response_headers\Controller\ResponseHeaderListBuilder',
      'responsive_image_style' => '\Drupal\responsive_image\ResponsiveImageStyleListBuilder',
      'scheduled_transition' => '\Drupal\scheduled_transitions\ScheduledTransitionsListBuilder',
      'simple_sitemap' => '\Drupal\simple_sitemap\SimpleSitemapListBuilder',
      'simple_sitemap_type' => '\Drupal\simple_sitemap\SimpleSitemapTypeListBuilder',
      'taxonomy_term' => '\Drupal\Core\Entity\EntityListBuilderInterface',
      'taxonomy_vocabulary' => '\Drupal\taxonomy\VocabularyListBuilder',
      'user' => '\Drupal\user\UserListBuilder',
      'user_role' => '\Drupal\user\RoleListBuilder',
      'view' => '\Drupal\views_ui\ViewListBuilder',
      'view_mode_page_pattern' => '\Drupal\view_mode_page\Form\PatternListBuilder',
      'workflow' => '\Drupal\workflows\WorkflowListBuilder',
    ]),
  );

  // Access control handlers.
  override(
    \Drupal\Core\Entity\EntityTypeManagerInterface::getAccessControlHandler(0),
    map([
      'access_token' => '\Drupal\access_unpublished\AccessTokenAccessControlHandler',
      'action' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'base_field_override' => '\Drupal\Core\Field\BaseFieldOverrideAccessControlHandler',
      'behavior_settings' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'block' => '\Drupal\block\BlockAccessControlHandler',
      'configurable_language' => '\Drupal\language\LanguageAccessControlHandler',
      'contact_form' => '\Drupal\contact\ContactFormAccessControlHandler',
      'contact_message' => '\Drupal\contact\ContactMessageAccessControlHandler',
      'content_moderation_state' => '\Drupal\content_moderation\ContentModerationStateAccessControlHandler',
      'crop' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'crop_type' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'date_format' => '\Drupal\system\DateFormatAccessControlHandler',
      'editor' => '\Drupal\editor\EditorAccessControlHandler',
      'eh_microsite_menu_override' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'embed_button' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'entity_browser' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'entity_embed_fake_entity' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'entity_form_display' => '\Drupal\Core\Entity\Entity\Access\EntityFormDisplayAccessControlHandler',
      'entity_form_mode' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'entity_hierarchy_microsite' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'entity_view_display' => '\Drupal\Core\Entity\Entity\Access\EntityViewDisplayAccessControlHandler',
      'entity_view_mode' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'environment_indicator' => '\Drupal\environment_indicator\EnvironmentIndicatorAccessControlHandler',
      'field_config' => '\Drupal\field\FieldConfigAccessControlHandler',
      'field_storage_config' => '\Drupal\field\FieldStorageConfigAccessControlHandler',
      'file' => '\Drupal\file_entity_delete\Access\FileAccessControlHandler',
      'filter_format' => '\Drupal\filter\FilterFormatAccessControlHandler',
      'flag' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'flagging' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'geocoder_provider' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'google_tag_container' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'image_style' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'key' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'key_config_override' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'language_content_settings' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'linkit_profile' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'mailchimp_transactional_template' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'media' => '\Drupal\media\MediaAccessControlHandler',
      'media_type' => '\Drupal\media\MediaTypeAccessControlHandler',
      'menu' => '\Drupal\system\MenuAccessControlHandler',
      'menu_link_content' => '\Drupal\menu_link_content\MenuLinkContentAccessControlHandler',
      'metatag_defaults' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'node' => '\Drupal\node\NodeAccessControlHandlerInterface',
      'node_type' => '\Drupal\node\NodeTypeAccessControlHandler',
      'openid_connect_client' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'paragraph' => '\Drupal\paragraphs\ParagraphAccessControlHandler',
      'paragraphs_library_item' => '\Drupal\paragraphs_library\LibraryItemAccessControlHandler',
      'paragraphs_type' => '\Drupal\paragraphs\ParagraphsTypeAccessControlHandler',
      'path_alias' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'pathauto_pattern' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'pfdp_directory' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'redirect' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'response_header' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'responsive_image_style' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'rest_resource_config' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'scheduled_transition' => '\Drupal\scheduled_transitions\ScheduledTransitionsAccessControlHandler',
      'simple_sitemap' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'simple_sitemap_type' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'taxonomy_term' => '\Drupal\taxonomy\TermAccessControlHandler',
      'taxonomy_vocabulary' => '\Drupal\taxonomy\VocabularyAccessControlHandler',
      'user' => '\Drupal\user\UserAccessControlHandler',
      'user_ref_access' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'user_role' => '\Drupal\user\RoleAccessControlHandler',
      'view' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'view_mode_page_pattern' => '\Drupal\Core\Entity\EntityAccessControlHandlerInterface',
      'workflow' => '\Drupal\workflows\WorkflowAccessControlHandler',
    ]),
  );

  // Storage methods.
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\access_unpublished\Entity\AccessToken[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\access_unpublished\Entity\AccessToken']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\access_unpublished\Entity\AccessToken']));
  override(\Drupal\access_unpublished\Entity\AccessToken::loadMultiple(), map(['' => '\Drupal\access_unpublished\Entity\AccessToken[]']));
  override(\Drupal\access_unpublished\Entity\AccessToken::load(), map(['' => '\Drupal\access_unpublished\Entity\AccessToken']));
  override(\Drupal\access_unpublished\Entity\AccessToken::create(), map(['' => '\Drupal\access_unpublished\Entity\AccessToken']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\system\Entity\Action[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\system\Entity\Action']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\system\Entity\Action']));
  override(\Drupal\system\Entity\Action::loadMultiple(), map(['' => '\Drupal\system\Entity\Action[]']));
  override(\Drupal\system\Entity\Action::load(), map(['' => '\Drupal\system\Entity\Action']));
  override(\Drupal\system\Entity\Action::create(), map(['' => '\Drupal\system\Entity\Action']));

  override(\Drupal\Core\Field\BaseFieldOverrideStorage::loadMultiple(), map(['' => '\Drupal\Core\Field\Entity\BaseFieldOverride[]']));
  override(\Drupal\Core\Field\BaseFieldOverrideStorage::load(), map(['' => '\Drupal\Core\Field\Entity\BaseFieldOverride']));
  override(\Drupal\Core\Field\BaseFieldOverrideStorage::create(), map(['' => '\Drupal\Core\Field\Entity\BaseFieldOverride']));
  override(\Drupal\Core\Field\Entity\BaseFieldOverride::loadMultiple(), map(['' => '\Drupal\Core\Field\Entity\BaseFieldOverride[]']));
  override(\Drupal\Core\Field\Entity\BaseFieldOverride::load(), map(['' => '\Drupal\Core\Field\Entity\BaseFieldOverride']));
  override(\Drupal\Core\Field\Entity\BaseFieldOverride::create(), map(['' => '\Drupal\Core\Field\Entity\BaseFieldOverride']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\rabbit_hole\Entity\BehaviorSettings[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\rabbit_hole\Entity\BehaviorSettings']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\rabbit_hole\Entity\BehaviorSettings']));
  override(\Drupal\rabbit_hole\Entity\BehaviorSettings::loadMultiple(), map(['' => '\Drupal\rabbit_hole\Entity\BehaviorSettings[]']));
  override(\Drupal\rabbit_hole\Entity\BehaviorSettings::load(), map(['' => '\Drupal\rabbit_hole\Entity\BehaviorSettings']));
  override(\Drupal\rabbit_hole\Entity\BehaviorSettings::create(), map(['' => '\Drupal\rabbit_hole\Entity\BehaviorSettings']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\block\Entity\Block[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\block\Entity\Block']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\block\Entity\Block']));
  override(\Drupal\block\Entity\Block::loadMultiple(), map(['' => '\Drupal\block\Entity\Block[]']));
  override(\Drupal\block\Entity\Block::load(), map(['' => '\Drupal\block\Entity\Block']));
  override(\Drupal\block\Entity\Block::create(), map(['' => '\Drupal\block\Entity\Block']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\language\Entity\ConfigurableLanguage[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\language\Entity\ConfigurableLanguage']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\language\Entity\ConfigurableLanguage']));
  override(\Drupal\language\Entity\ConfigurableLanguage::loadMultiple(), map(['' => '\Drupal\language\Entity\ConfigurableLanguage[]']));
  override(\Drupal\language\Entity\ConfigurableLanguage::load(), map(['' => '\Drupal\language\Entity\ConfigurableLanguage']));
  override(\Drupal\language\Entity\ConfigurableLanguage::create(), map(['' => '\Drupal\language\Entity\ConfigurableLanguage']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\contact\Entity\ContactForm[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\contact\Entity\ContactForm']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\contact\Entity\ContactForm']));
  override(\Drupal\contact\Entity\ContactForm::loadMultiple(), map(['' => '\Drupal\contact\Entity\ContactForm[]']));
  override(\Drupal\contact\Entity\ContactForm::load(), map(['' => '\Drupal\contact\Entity\ContactForm']));
  override(\Drupal\contact\Entity\ContactForm::create(), map(['' => '\Drupal\contact\Entity\ContactForm']));

  override(\Drupal\Core\Entity\ContentEntityNullStorage::loadMultiple(), map(['' => '\Drupal\contact\Entity\Message[]']));
  override(\Drupal\Core\Entity\ContentEntityNullStorage::load(), map(['' => '\Drupal\contact\Entity\Message']));
  override(\Drupal\Core\Entity\ContentEntityNullStorage::create(), map(['' => '\Drupal\contact\Entity\Message']));
  override(\Drupal\contact\Entity\Message::loadMultiple(), map(['' => '\Drupal\contact\Entity\Message[]']));
  override(\Drupal\contact\Entity\Message::load(), map(['' => '\Drupal\contact\Entity\Message']));
  override(\Drupal\contact\Entity\Message::create(), map(['' => '\Drupal\contact\Entity\Message']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\content_moderation\Entity\ContentModerationStateInterface[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\content_moderation\Entity\ContentModerationStateInterface']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\content_moderation\Entity\ContentModerationStateInterface']));
  override(\Drupal\content_moderation\Entity\ContentModerationStateInterface::loadMultiple(), map(['' => '\Drupal\content_moderation\Entity\ContentModerationStateInterface[]']));
  override(\Drupal\content_moderation\Entity\ContentModerationStateInterface::load(), map(['' => '\Drupal\content_moderation\Entity\ContentModerationStateInterface']));
  override(\Drupal\content_moderation\Entity\ContentModerationStateInterface::create(), map(['' => '\Drupal\content_moderation\Entity\ContentModerationStateInterface']));

  override(\Drupal\crop\CropStorageInterface::loadMultiple(), map(['' => '\Drupal\crop\Entity\Crop[]']));
  override(\Drupal\crop\CropStorageInterface::load(), map(['' => '\Drupal\crop\Entity\Crop']));
  override(\Drupal\crop\CropStorageInterface::create(), map(['' => '\Drupal\crop\Entity\Crop']));
  override(\Drupal\crop\Entity\Crop::loadMultiple(), map(['' => '\Drupal\crop\Entity\Crop[]']));
  override(\Drupal\crop\Entity\Crop::load(), map(['' => '\Drupal\crop\Entity\Crop']));
  override(\Drupal\crop\Entity\Crop::create(), map(['' => '\Drupal\crop\Entity\Crop']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\crop\Entity\CropType[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\crop\Entity\CropType']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\crop\Entity\CropType']));
  override(\Drupal\crop\Entity\CropType::loadMultiple(), map(['' => '\Drupal\crop\Entity\CropType[]']));
  override(\Drupal\crop\Entity\CropType::load(), map(['' => '\Drupal\crop\Entity\CropType']));
  override(\Drupal\crop\Entity\CropType::create(), map(['' => '\Drupal\crop\Entity\CropType']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\Core\Datetime\Entity\DateFormat[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\Core\Datetime\Entity\DateFormat']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\Core\Datetime\Entity\DateFormat']));
  override(\Drupal\Core\Datetime\Entity\DateFormat::loadMultiple(), map(['' => '\Drupal\Core\Datetime\Entity\DateFormat[]']));
  override(\Drupal\Core\Datetime\Entity\DateFormat::load(), map(['' => '\Drupal\Core\Datetime\Entity\DateFormat']));
  override(\Drupal\Core\Datetime\Entity\DateFormat::create(), map(['' => '\Drupal\Core\Datetime\Entity\DateFormat']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\editor\Entity\Editor[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\editor\Entity\Editor']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\editor\Entity\Editor']));
  override(\Drupal\editor\Entity\Editor::loadMultiple(), map(['' => '\Drupal\editor\Entity\Editor[]']));
  override(\Drupal\editor\Entity\Editor::load(), map(['' => '\Drupal\editor\Entity\Editor']));
  override(\Drupal\editor\Entity\Editor::create(), map(['' => '\Drupal\editor\Entity\Editor']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface']));
  override(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface::loadMultiple(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface[]']));
  override(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface::load(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface']));
  override(\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface::create(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\embed\Entity\EmbedButton[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\embed\Entity\EmbedButton']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\embed\Entity\EmbedButton']));
  override(\Drupal\embed\Entity\EmbedButton::loadMultiple(), map(['' => '\Drupal\embed\Entity\EmbedButton[]']));
  override(\Drupal\embed\Entity\EmbedButton::load(), map(['' => '\Drupal\embed\Entity\EmbedButton']));
  override(\Drupal\embed\Entity\EmbedButton::create(), map(['' => '\Drupal\embed\Entity\EmbedButton']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\entity_browser\Entity\EntityBrowser[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\entity_browser\Entity\EntityBrowser']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\entity_browser\Entity\EntityBrowser']));
  override(\Drupal\entity_browser\Entity\EntityBrowser::loadMultiple(), map(['' => '\Drupal\entity_browser\Entity\EntityBrowser[]']));
  override(\Drupal\entity_browser\Entity\EntityBrowser::load(), map(['' => '\Drupal\entity_browser\Entity\EntityBrowser']));
  override(\Drupal\entity_browser\Entity\EntityBrowser::create(), map(['' => '\Drupal\entity_browser\Entity\EntityBrowser']));

  override(\Drupal\Core\Entity\ContentEntityNullStorage::loadMultiple(), map(['' => '\Drupal\entity_embed\Entity\EntityEmbedFakeEntity[]']));
  override(\Drupal\Core\Entity\ContentEntityNullStorage::load(), map(['' => '\Drupal\entity_embed\Entity\EntityEmbedFakeEntity']));
  override(\Drupal\Core\Entity\ContentEntityNullStorage::create(), map(['' => '\Drupal\entity_embed\Entity\EntityEmbedFakeEntity']));
  override(\Drupal\entity_embed\Entity\EntityEmbedFakeEntity::loadMultiple(), map(['' => '\Drupal\entity_embed\Entity\EntityEmbedFakeEntity[]']));
  override(\Drupal\entity_embed\Entity\EntityEmbedFakeEntity::load(), map(['' => '\Drupal\entity_embed\Entity\EntityEmbedFakeEntity']));
  override(\Drupal\entity_embed\Entity\EntityEmbedFakeEntity::create(), map(['' => '\Drupal\entity_embed\Entity\EntityEmbedFakeEntity']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormDisplay[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormDisplay']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormDisplay']));
  override(\Drupal\Core\Entity\Entity\EntityFormDisplay::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormDisplay[]']));
  override(\Drupal\Core\Entity\Entity\EntityFormDisplay::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormDisplay']));
  override(\Drupal\Core\Entity\Entity\EntityFormDisplay::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormDisplay']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormMode[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormMode']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormMode']));
  override(\Drupal\Core\Entity\Entity\EntityFormMode::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormMode[]']));
  override(\Drupal\Core\Entity\Entity\EntityFormMode::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormMode']));
  override(\Drupal\Core\Entity\Entity\EntityFormMode::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityFormMode']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface']));
  override(\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface::loadMultiple(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface[]']));
  override(\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface::load(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface']));
  override(\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface::create(), map(['' => '\Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewDisplay[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewDisplay']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewDisplay']));
  override(\Drupal\Core\Entity\Entity\EntityViewDisplay::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewDisplay[]']));
  override(\Drupal\Core\Entity\Entity\EntityViewDisplay::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewDisplay']));
  override(\Drupal\Core\Entity\Entity\EntityViewDisplay::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewDisplay']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewMode[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewMode']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewMode']));
  override(\Drupal\Core\Entity\Entity\EntityViewMode::loadMultiple(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewMode[]']));
  override(\Drupal\Core\Entity\Entity\EntityViewMode::load(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewMode']));
  override(\Drupal\Core\Entity\Entity\EntityViewMode::create(), map(['' => '\Drupal\Core\Entity\Entity\EntityViewMode']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\environment_indicator\Entity\EnvironmentIndicator[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\environment_indicator\Entity\EnvironmentIndicator']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\environment_indicator\Entity\EnvironmentIndicator']));
  override(\Drupal\environment_indicator\Entity\EnvironmentIndicator::loadMultiple(), map(['' => '\Drupal\environment_indicator\Entity\EnvironmentIndicator[]']));
  override(\Drupal\environment_indicator\Entity\EnvironmentIndicator::load(), map(['' => '\Drupal\environment_indicator\Entity\EnvironmentIndicator']));
  override(\Drupal\environment_indicator\Entity\EnvironmentIndicator::create(), map(['' => '\Drupal\environment_indicator\Entity\EnvironmentIndicator']));

  override(\Drupal\field\FieldConfigStorage::loadMultiple(), map(['' => '\Drupal\field\Entity\FieldConfig[]']));
  override(\Drupal\field\FieldConfigStorage::load(), map(['' => '\Drupal\field\Entity\FieldConfig']));
  override(\Drupal\field\FieldConfigStorage::create(), map(['' => '\Drupal\field\Entity\FieldConfig']));
  override(\Drupal\field\Entity\FieldConfig::loadMultiple(), map(['' => '\Drupal\field\Entity\FieldConfig[]']));
  override(\Drupal\field\Entity\FieldConfig::load(), map(['' => '\Drupal\field\Entity\FieldConfig']));
  override(\Drupal\field\Entity\FieldConfig::create(), map(['' => '\Drupal\field\Entity\FieldConfig']));

  override(\Drupal\field\FieldStorageConfigStorage::loadMultiple(), map(['' => '\Drupal\field\Entity\FieldStorageConfig[]']));
  override(\Drupal\field\FieldStorageConfigStorage::load(), map(['' => '\Drupal\field\Entity\FieldStorageConfig']));
  override(\Drupal\field\FieldStorageConfigStorage::create(), map(['' => '\Drupal\field\Entity\FieldStorageConfig']));
  override(\Drupal\field\Entity\FieldStorageConfig::loadMultiple(), map(['' => '\Drupal\field\Entity\FieldStorageConfig[]']));
  override(\Drupal\field\Entity\FieldStorageConfig::load(), map(['' => '\Drupal\field\Entity\FieldStorageConfig']));
  override(\Drupal\field\Entity\FieldStorageConfig::create(), map(['' => '\Drupal\field\Entity\FieldStorageConfig']));

  override(\Drupal\file\FileStorageInterface::loadMultiple(), map(['' => '\Drupal\file\Entity\File[]']));
  override(\Drupal\file\FileStorageInterface::load(), map(['' => '\Drupal\file\Entity\File']));
  override(\Drupal\file\FileStorageInterface::create(), map(['' => '\Drupal\file\Entity\File']));
  override(\Drupal\file\Entity\File::loadMultiple(), map(['' => '\Drupal\file\Entity\File[]']));
  override(\Drupal\file\Entity\File::load(), map(['' => '\Drupal\file\Entity\File']));
  override(\Drupal\file\Entity\File::create(), map(['' => '\Drupal\file\Entity\File']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\filter\Entity\FilterFormat[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\filter\Entity\FilterFormat']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\filter\Entity\FilterFormat']));
  override(\Drupal\filter\Entity\FilterFormat::loadMultiple(), map(['' => '\Drupal\filter\Entity\FilterFormat[]']));
  override(\Drupal\filter\Entity\FilterFormat::load(), map(['' => '\Drupal\filter\Entity\FilterFormat']));
  override(\Drupal\filter\Entity\FilterFormat::create(), map(['' => '\Drupal\filter\Entity\FilterFormat']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\flag\Entity\Flag[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\flag\Entity\Flag']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\flag\Entity\Flag']));
  override(\Drupal\flag\Entity\Flag::loadMultiple(), map(['' => '\Drupal\flag\Entity\Flag[]']));
  override(\Drupal\flag\Entity\Flag::load(), map(['' => '\Drupal\flag\Entity\Flag']));
  override(\Drupal\flag\Entity\Flag::create(), map(['' => '\Drupal\flag\Entity\Flag']));

  override(\Drupal\flag\Entity\Storage\FlaggingStorageInterface::loadMultiple(), map(['' => '\Drupal\flag\Entity\Flagging[]']));
  override(\Drupal\flag\Entity\Storage\FlaggingStorageInterface::load(), map(['' => '\Drupal\flag\Entity\Flagging']));
  override(\Drupal\flag\Entity\Storage\FlaggingStorageInterface::create(), map(['' => '\Drupal\flag\Entity\Flagging']));
  override(\Drupal\flag\Entity\Flagging::loadMultiple(), map(['' => '\Drupal\flag\Entity\Flagging[]']));
  override(\Drupal\flag\Entity\Flagging::load(), map(['' => '\Drupal\flag\Entity\Flagging']));
  override(\Drupal\flag\Entity\Flagging::create(), map(['' => '\Drupal\flag\Entity\Flagging']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\geocoder\Entity\GeocoderProvider[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\geocoder\Entity\GeocoderProvider']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\geocoder\Entity\GeocoderProvider']));
  override(\Drupal\geocoder\Entity\GeocoderProvider::loadMultiple(), map(['' => '\Drupal\geocoder\Entity\GeocoderProvider[]']));
  override(\Drupal\geocoder\Entity\GeocoderProvider::load(), map(['' => '\Drupal\geocoder\Entity\GeocoderProvider']));
  override(\Drupal\geocoder\Entity\GeocoderProvider::create(), map(['' => '\Drupal\geocoder\Entity\GeocoderProvider']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\google_tag\Entity\TagContainer[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\google_tag\Entity\TagContainer']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\google_tag\Entity\TagContainer']));
  override(\Drupal\google_tag\Entity\TagContainer::loadMultiple(), map(['' => '\Drupal\google_tag\Entity\TagContainer[]']));
  override(\Drupal\google_tag\Entity\TagContainer::load(), map(['' => '\Drupal\google_tag\Entity\TagContainer']));
  override(\Drupal\google_tag\Entity\TagContainer::create(), map(['' => '\Drupal\google_tag\Entity\TagContainer']));

  override(\Drupal\image\ImageStyleStorageInterface::loadMultiple(), map(['' => '\Drupal\mass_utility\EntityAlter\ImageStyle[]']));
  override(\Drupal\image\ImageStyleStorageInterface::load(), map(['' => '\Drupal\mass_utility\EntityAlter\ImageStyle']));
  override(\Drupal\image\ImageStyleStorageInterface::create(), map(['' => '\Drupal\mass_utility\EntityAlter\ImageStyle']));
  override(\Drupal\mass_utility\EntityAlter\ImageStyle::loadMultiple(), map(['' => '\Drupal\mass_utility\EntityAlter\ImageStyle[]']));
  override(\Drupal\mass_utility\EntityAlter\ImageStyle::load(), map(['' => '\Drupal\mass_utility\EntityAlter\ImageStyle']));
  override(\Drupal\mass_utility\EntityAlter\ImageStyle::create(), map(['' => '\Drupal\mass_utility\EntityAlter\ImageStyle']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\key\Entity\Key[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\key\Entity\Key']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\key\Entity\Key']));
  override(\Drupal\key\Entity\Key::loadMultiple(), map(['' => '\Drupal\key\Entity\Key[]']));
  override(\Drupal\key\Entity\Key::load(), map(['' => '\Drupal\key\Entity\Key']));
  override(\Drupal\key\Entity\Key::create(), map(['' => '\Drupal\key\Entity\Key']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\key\Entity\KeyConfigOverride[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\key\Entity\KeyConfigOverride']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\key\Entity\KeyConfigOverride']));
  override(\Drupal\key\Entity\KeyConfigOverride::loadMultiple(), map(['' => '\Drupal\key\Entity\KeyConfigOverride[]']));
  override(\Drupal\key\Entity\KeyConfigOverride::load(), map(['' => '\Drupal\key\Entity\KeyConfigOverride']));
  override(\Drupal\key\Entity\KeyConfigOverride::create(), map(['' => '\Drupal\key\Entity\KeyConfigOverride']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\language\Entity\ContentLanguageSettings[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\language\Entity\ContentLanguageSettings']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\language\Entity\ContentLanguageSettings']));
  override(\Drupal\language\Entity\ContentLanguageSettings::loadMultiple(), map(['' => '\Drupal\language\Entity\ContentLanguageSettings[]']));
  override(\Drupal\language\Entity\ContentLanguageSettings::load(), map(['' => '\Drupal\language\Entity\ContentLanguageSettings']));
  override(\Drupal\language\Entity\ContentLanguageSettings::create(), map(['' => '\Drupal\language\Entity\ContentLanguageSettings']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\linkit\Entity\Profile[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\linkit\Entity\Profile']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\linkit\Entity\Profile']));
  override(\Drupal\linkit\Entity\Profile::loadMultiple(), map(['' => '\Drupal\linkit\Entity\Profile[]']));
  override(\Drupal\linkit\Entity\Profile::load(), map(['' => '\Drupal\linkit\Entity\Profile']));
  override(\Drupal\linkit\Entity\Profile::create(), map(['' => '\Drupal\linkit\Entity\Profile']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface']));
  override(\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface::loadMultiple(), map(['' => '\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface[]']));
  override(\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface::load(), map(['' => '\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface']));
  override(\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface::create(), map(['' => '\Drupal\mailchimp_transactional_template\Entity\TemplateMapInterface']));

  override(\Drupal\media\MediaStorage::loadMultiple(), map(['' => '\Drupal\media\Entity\Media[]']));
  override(\Drupal\media\MediaStorage::load(), map(['' => '\Drupal\media\Entity\Media']));
  override(\Drupal\media\MediaStorage::create(), map(['' => '\Drupal\media\Entity\Media']));
  override(\Drupal\media\Entity\Media::loadMultiple(), map(['' => '\Drupal\media\Entity\Media[]']));
  override(\Drupal\media\Entity\Media::load(), map(['' => '\Drupal\media\Entity\Media']));
  override(\Drupal\media\Entity\Media::create(), map(['' => '\Drupal\media\Entity\Media']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\media\Entity\MediaType[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\media\Entity\MediaType']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\media\Entity\MediaType']));
  override(\Drupal\media\Entity\MediaType::loadMultiple(), map(['' => '\Drupal\media\Entity\MediaType[]']));
  override(\Drupal\media\Entity\MediaType::load(), map(['' => '\Drupal\media\Entity\MediaType']));
  override(\Drupal\media\Entity\MediaType::create(), map(['' => '\Drupal\media\Entity\MediaType']));

  override(\Drupal\system\MenuStorage::loadMultiple(), map(['' => '\Drupal\system\Entity\Menu[]']));
  override(\Drupal\system\MenuStorage::load(), map(['' => '\Drupal\system\Entity\Menu']));
  override(\Drupal\system\MenuStorage::create(), map(['' => '\Drupal\system\Entity\Menu']));
  override(\Drupal\system\Entity\Menu::loadMultiple(), map(['' => '\Drupal\system\Entity\Menu[]']));
  override(\Drupal\system\Entity\Menu::load(), map(['' => '\Drupal\system\Entity\Menu']));
  override(\Drupal\system\Entity\Menu::create(), map(['' => '\Drupal\system\Entity\Menu']));

  override(\Drupal\menu_link_content\MenuLinkContentStorageInterface::loadMultiple(), map(['' => '\Drupal\menu_link_content\Entity\MenuLinkContent[]']));
  override(\Drupal\menu_link_content\MenuLinkContentStorageInterface::load(), map(['' => '\Drupal\menu_link_content\Entity\MenuLinkContent']));
  override(\Drupal\menu_link_content\MenuLinkContentStorageInterface::create(), map(['' => '\Drupal\menu_link_content\Entity\MenuLinkContent']));
  override(\Drupal\menu_link_content\Entity\MenuLinkContent::loadMultiple(), map(['' => '\Drupal\menu_link_content\Entity\MenuLinkContent[]']));
  override(\Drupal\menu_link_content\Entity\MenuLinkContent::load(), map(['' => '\Drupal\menu_link_content\Entity\MenuLinkContent']));
  override(\Drupal\menu_link_content\Entity\MenuLinkContent::create(), map(['' => '\Drupal\menu_link_content\Entity\MenuLinkContent']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\metatag\Entity\MetatagDefaults[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\metatag\Entity\MetatagDefaults']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\metatag\Entity\MetatagDefaults']));
  override(\Drupal\metatag\Entity\MetatagDefaults::loadMultiple(), map(['' => '\Drupal\metatag\Entity\MetatagDefaults[]']));
  override(\Drupal\metatag\Entity\MetatagDefaults::load(), map(['' => '\Drupal\metatag\Entity\MetatagDefaults']));
  override(\Drupal\metatag\Entity\MetatagDefaults::create(), map(['' => '\Drupal\metatag\Entity\MetatagDefaults']));

  override(\Drupal\node\NodeStorageInterface::loadMultiple(), map(['' => '\Drupal\node\Entity\Node[]']));
  override(\Drupal\node\NodeStorageInterface::load(), map(['' => '\Drupal\node\Entity\Node']));
  override(\Drupal\node\NodeStorageInterface::create(), map(['' => '\Drupal\node\Entity\Node']));
  override(\Drupal\node\Entity\Node::loadMultiple(), map(['' => '\Drupal\node\Entity\Node[]']));
  override(\Drupal\node\Entity\Node::load(), map(['' => '\Drupal\node\Entity\Node']));
  override(\Drupal\node\Entity\Node::create(), map(['' => '\Drupal\node\Entity\Node']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\node\Entity\NodeType[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\node\Entity\NodeType']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\node\Entity\NodeType']));
  override(\Drupal\node\Entity\NodeType::loadMultiple(), map(['' => '\Drupal\node\Entity\NodeType[]']));
  override(\Drupal\node\Entity\NodeType::load(), map(['' => '\Drupal\node\Entity\NodeType']));
  override(\Drupal\node\Entity\NodeType::create(), map(['' => '\Drupal\node\Entity\NodeType']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\openid_connect\Entity\OpenIDConnectClientEntity[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\openid_connect\Entity\OpenIDConnectClientEntity']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\openid_connect\Entity\OpenIDConnectClientEntity']));
  override(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::loadMultiple(), map(['' => '\Drupal\openid_connect\Entity\OpenIDConnectClientEntity[]']));
  override(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::load(), map(['' => '\Drupal\openid_connect\Entity\OpenIDConnectClientEntity']));
  override(\Drupal\openid_connect\Entity\OpenIDConnectClientEntity::create(), map(['' => '\Drupal\openid_connect\Entity\OpenIDConnectClientEntity']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\paragraphs\Entity\Paragraph[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\paragraphs\Entity\Paragraph']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\paragraphs\Entity\Paragraph']));
  override(\Drupal\paragraphs\Entity\Paragraph::loadMultiple(), map(['' => '\Drupal\paragraphs\Entity\Paragraph[]']));
  override(\Drupal\paragraphs\Entity\Paragraph::load(), map(['' => '\Drupal\paragraphs\Entity\Paragraph']));
  override(\Drupal\paragraphs\Entity\Paragraph::create(), map(['' => '\Drupal\paragraphs\Entity\Paragraph']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\paragraphs_library\Entity\LibraryItem[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\paragraphs_library\Entity\LibraryItem']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\paragraphs_library\Entity\LibraryItem']));
  override(\Drupal\paragraphs_library\Entity\LibraryItem::loadMultiple(), map(['' => '\Drupal\paragraphs_library\Entity\LibraryItem[]']));
  override(\Drupal\paragraphs_library\Entity\LibraryItem::load(), map(['' => '\Drupal\paragraphs_library\Entity\LibraryItem']));
  override(\Drupal\paragraphs_library\Entity\LibraryItem::create(), map(['' => '\Drupal\paragraphs_library\Entity\LibraryItem']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\paragraphs\Entity\ParagraphsType[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\paragraphs\Entity\ParagraphsType']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\paragraphs\Entity\ParagraphsType']));
  override(\Drupal\paragraphs\Entity\ParagraphsType::loadMultiple(), map(['' => '\Drupal\paragraphs\Entity\ParagraphsType[]']));
  override(\Drupal\paragraphs\Entity\ParagraphsType::load(), map(['' => '\Drupal\paragraphs\Entity\ParagraphsType']));
  override(\Drupal\paragraphs\Entity\ParagraphsType::create(), map(['' => '\Drupal\paragraphs\Entity\ParagraphsType']));

  override(\Drupal\path_alias\PathAliasStorage::loadMultiple(), map(['' => '\Drupal\path_alias\Entity\PathAlias[]']));
  override(\Drupal\path_alias\PathAliasStorage::load(), map(['' => '\Drupal\path_alias\Entity\PathAlias']));
  override(\Drupal\path_alias\PathAliasStorage::create(), map(['' => '\Drupal\path_alias\Entity\PathAlias']));
  override(\Drupal\path_alias\Entity\PathAlias::loadMultiple(), map(['' => '\Drupal\path_alias\Entity\PathAlias[]']));
  override(\Drupal\path_alias\Entity\PathAlias::load(), map(['' => '\Drupal\path_alias\Entity\PathAlias']));
  override(\Drupal\path_alias\Entity\PathAlias::create(), map(['' => '\Drupal\path_alias\Entity\PathAlias']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\pathauto\Entity\PathautoPattern[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\pathauto\Entity\PathautoPattern']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\pathauto\Entity\PathautoPattern']));
  override(\Drupal\pathauto\Entity\PathautoPattern::loadMultiple(), map(['' => '\Drupal\pathauto\Entity\PathautoPattern[]']));
  override(\Drupal\pathauto\Entity\PathautoPattern::load(), map(['' => '\Drupal\pathauto\Entity\PathautoPattern']));
  override(\Drupal\pathauto\Entity\PathautoPattern::create(), map(['' => '\Drupal\pathauto\Entity\PathautoPattern']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\pfdp\Entity\DirectoryEntityInterface[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\pfdp\Entity\DirectoryEntityInterface']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\pfdp\Entity\DirectoryEntityInterface']));
  override(\Drupal\pfdp\Entity\DirectoryEntityInterface::loadMultiple(), map(['' => '\Drupal\pfdp\Entity\DirectoryEntityInterface[]']));
  override(\Drupal\pfdp\Entity\DirectoryEntityInterface::load(), map(['' => '\Drupal\pfdp\Entity\DirectoryEntityInterface']));
  override(\Drupal\pfdp\Entity\DirectoryEntityInterface::create(), map(['' => '\Drupal\pfdp\Entity\DirectoryEntityInterface']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\redirect\Entity\Redirect[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\redirect\Entity\Redirect']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\redirect\Entity\Redirect']));
  override(\Drupal\redirect\Entity\Redirect::loadMultiple(), map(['' => '\Drupal\redirect\Entity\Redirect[]']));
  override(\Drupal\redirect\Entity\Redirect::load(), map(['' => '\Drupal\redirect\Entity\Redirect']));
  override(\Drupal\redirect\Entity\Redirect::create(), map(['' => '\Drupal\redirect\Entity\Redirect']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\http_response_headers\Entity\ResponseHeader[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\http_response_headers\Entity\ResponseHeader']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\http_response_headers\Entity\ResponseHeader']));
  override(\Drupal\http_response_headers\Entity\ResponseHeader::loadMultiple(), map(['' => '\Drupal\http_response_headers\Entity\ResponseHeader[]']));
  override(\Drupal\http_response_headers\Entity\ResponseHeader::load(), map(['' => '\Drupal\http_response_headers\Entity\ResponseHeader']));
  override(\Drupal\http_response_headers\Entity\ResponseHeader::create(), map(['' => '\Drupal\http_response_headers\Entity\ResponseHeader']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\responsive_image\Entity\ResponsiveImageStyle[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\responsive_image\Entity\ResponsiveImageStyle']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\responsive_image\Entity\ResponsiveImageStyle']));
  override(\Drupal\responsive_image\Entity\ResponsiveImageStyle::loadMultiple(), map(['' => '\Drupal\responsive_image\Entity\ResponsiveImageStyle[]']));
  override(\Drupal\responsive_image\Entity\ResponsiveImageStyle::load(), map(['' => '\Drupal\responsive_image\Entity\ResponsiveImageStyle']));
  override(\Drupal\responsive_image\Entity\ResponsiveImageStyle::create(), map(['' => '\Drupal\responsive_image\Entity\ResponsiveImageStyle']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\rest\Entity\RestResourceConfig[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\rest\Entity\RestResourceConfig']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\rest\Entity\RestResourceConfig']));
  override(\Drupal\rest\Entity\RestResourceConfig::loadMultiple(), map(['' => '\Drupal\rest\Entity\RestResourceConfig[]']));
  override(\Drupal\rest\Entity\RestResourceConfig::load(), map(['' => '\Drupal\rest\Entity\RestResourceConfig']));
  override(\Drupal\rest\Entity\RestResourceConfig::create(), map(['' => '\Drupal\rest\Entity\RestResourceConfig']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface']));
  override(\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface::loadMultiple(), map(['' => '\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[]']));
  override(\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface::load(), map(['' => '\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface']));
  override(\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface::create(), map(['' => '\Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface']));

  override(\Drupal\simple_sitemap\Entity\SimpleSitemapStorage::loadMultiple(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapStorage::load(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapInterface']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapStorage::create(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapInterface']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapInterface::loadMultiple(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapInterface::load(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapInterface']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapInterface::create(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapInterface']));

  override(\Drupal\simple_sitemap\Entity\SimpleSitemapTypeStorage::loadMultiple(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface[]']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapTypeStorage::load(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapTypeStorage::create(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface::loadMultiple(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface[]']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface::load(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface']));
  override(\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface::create(), map(['' => '\Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface']));

  override(\Drupal\taxonomy\TermStorageInterface::loadMultiple(), map(['' => '\Drupal\taxonomy\Entity\Term[]']));
  override(\Drupal\taxonomy\TermStorageInterface::load(), map(['' => '\Drupal\taxonomy\Entity\Term']));
  override(\Drupal\taxonomy\TermStorageInterface::create(), map(['' => '\Drupal\taxonomy\Entity\Term']));
  override(\Drupal\taxonomy\Entity\Term::loadMultiple(), map(['' => '\Drupal\taxonomy\Entity\Term[]']));
  override(\Drupal\taxonomy\Entity\Term::load(), map(['' => '\Drupal\taxonomy\Entity\Term']));
  override(\Drupal\taxonomy\Entity\Term::create(), map(['' => '\Drupal\taxonomy\Entity\Term']));

  override(\Drupal\taxonomy\VocabularyStorageInterface::loadMultiple(), map(['' => '\Drupal\taxonomy\Entity\Vocabulary[]']));
  override(\Drupal\taxonomy\VocabularyStorageInterface::load(), map(['' => '\Drupal\taxonomy\Entity\Vocabulary']));
  override(\Drupal\taxonomy\VocabularyStorageInterface::create(), map(['' => '\Drupal\taxonomy\Entity\Vocabulary']));
  override(\Drupal\taxonomy\Entity\Vocabulary::loadMultiple(), map(['' => '\Drupal\taxonomy\Entity\Vocabulary[]']));
  override(\Drupal\taxonomy\Entity\Vocabulary::load(), map(['' => '\Drupal\taxonomy\Entity\Vocabulary']));
  override(\Drupal\taxonomy\Entity\Vocabulary::create(), map(['' => '\Drupal\taxonomy\Entity\Vocabulary']));

  override(\Drupal\user\UserStorageInterface::loadMultiple(), map(['' => '\Drupal\user\Entity\User[]']));
  override(\Drupal\user\UserStorageInterface::load(), map(['' => '\Drupal\user\Entity\User']));
  override(\Drupal\user\UserStorageInterface::create(), map(['' => '\Drupal\user\Entity\User']));
  override(\Drupal\user\Entity\User::loadMultiple(), map(['' => '\Drupal\user\Entity\User[]']));
  override(\Drupal\user\Entity\User::load(), map(['' => '\Drupal\user\Entity\User']));
  override(\Drupal\user\Entity\User::create(), map(['' => '\Drupal\user\Entity\User']));

  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::loadMultiple(), map(['' => '\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess[]']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::load(), map(['' => '\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess']));
  override(\Drupal\Core\Entity\Sql\SqlContentEntityStorage::create(), map(['' => '\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess']));
  override(\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess::loadMultiple(), map(['' => '\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess[]']));
  override(\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess::load(), map(['' => '\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess']));
  override(\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess::create(), map(['' => '\Drupal\mass_entityaccess_userreference\Entity\UserRefAccess']));

  override(\Drupal\user\RoleStorageInterface::loadMultiple(), map(['' => '\Drupal\user\Entity\Role[]']));
  override(\Drupal\user\RoleStorageInterface::load(), map(['' => '\Drupal\user\Entity\Role']));
  override(\Drupal\user\RoleStorageInterface::create(), map(['' => '\Drupal\user\Entity\Role']));
  override(\Drupal\user\Entity\Role::loadMultiple(), map(['' => '\Drupal\user\Entity\Role[]']));
  override(\Drupal\user\Entity\Role::load(), map(['' => '\Drupal\user\Entity\Role']));
  override(\Drupal\user\Entity\Role::create(), map(['' => '\Drupal\user\Entity\Role']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\views\Entity\View[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\views\Entity\View']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\views\Entity\View']));
  override(\Drupal\views\Entity\View::loadMultiple(), map(['' => '\Drupal\views\Entity\View[]']));
  override(\Drupal\views\Entity\View::load(), map(['' => '\Drupal\views\Entity\View']));
  override(\Drupal\views\Entity\View::create(), map(['' => '\Drupal\views\Entity\View']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\view_mode_page\Entity\ViewmodepagePattern[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\view_mode_page\Entity\ViewmodepagePattern']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\view_mode_page\Entity\ViewmodepagePattern']));
  override(\Drupal\view_mode_page\Entity\ViewmodepagePattern::loadMultiple(), map(['' => '\Drupal\view_mode_page\Entity\ViewmodepagePattern[]']));
  override(\Drupal\view_mode_page\Entity\ViewmodepagePattern::load(), map(['' => '\Drupal\view_mode_page\Entity\ViewmodepagePattern']));
  override(\Drupal\view_mode_page\Entity\ViewmodepagePattern::create(), map(['' => '\Drupal\view_mode_page\Entity\ViewmodepagePattern']));

  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadMultiple(), map(['' => '\Drupal\workflows\Entity\Workflow[]']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::load(), map(['' => '\Drupal\workflows\Entity\Workflow']));
  override(\Drupal\Core\Config\Entity\ConfigEntityStorageInterface::create(), map(['' => '\Drupal\workflows\Entity\Workflow']));
  override(\Drupal\workflows\Entity\Workflow::loadMultiple(), map(['' => '\Drupal\workflows\Entity\Workflow[]']));
  override(\Drupal\workflows\Entity\Workflow::load(), map(['' => '\Drupal\workflows\Entity\Workflow']));
  override(\Drupal\workflows\Entity\Workflow::create(), map(['' => '\Drupal\workflows\Entity\Workflow']));

}
