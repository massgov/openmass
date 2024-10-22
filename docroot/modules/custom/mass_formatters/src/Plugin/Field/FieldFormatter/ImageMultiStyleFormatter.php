<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\breakpoint\BreakpointManagerInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * @FieldFormatter(
 *   id = "mass_image_multi_style",
 *   label = @Translation("Image Multi-style"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageMultiStyleFormatter extends ImageFormatterBase implements ContainerFactoryPluginInterface {
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The breakpoint manager.
   *
   * @var \Drupal\breakpoint\BreakpointManagerInterface
   */
  protected $breakpointManager;

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\breakpoint\BreakpointManagerInterface $breakpoint_manager
   *   The breakpoint manager service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage, BreakpointManagerInterface $breakpoint_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->currentUser = $current_user;
    $this->imageStyleStorage = $image_style_storage;
    $this->breakpointManager = $breakpoint_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('breakpoint.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_styles' => [],
      'image_link' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $theme = \Drupal::config('system.theme')->get('default');
    $breakpoints = $this->breakpointManager->getBreakpointsByGroup($theme);
    if (empty($breakpoints)) {
      $element['configure'] = [
        '#type' => 'item',
        '#title' => $this->t('Image Styles'),
        '#description' => $this->t('A breakpoints configuration file must be defined in the default theme to configure this formatter.'),
      ];
      return $element;
    }
    $image_styles = image_style_options(FALSE);
    $image_styles_values = $this->getSetting('image_styles');
    $element['image_styles'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Image Styles'),
    ];
    foreach ($breakpoints as $breakpoint) {
      $label = (string) $breakpoint->getLabel();
      $key = $breakpoint->getMediaQuery();
      $element['image_styles'][$key] = [
        '#title' => $this->t('Image style for the @label breakpoint', ['@label' => $label]),
        '#type' => 'select',
        '#default_value' => isset($image_styles_values[$key]) ? $image_styles_values[$key] : '',
        '#empty_option' => t('None (original image)'),
        '#options' => $image_styles,
      ];
    }
    $link_types = [
      'content' => t('Content'),
      'file' => t('File'),
    ];
    $element['image_link'] = [
      '#title' => t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => t('Nothing'),
      '#options' => $link_types,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_values = $this->getSetting('image_styles');
    foreach ($image_style_values as $delta => $image_style) {
      if (isset($image_styles[$image_style])) {
        $summary[] = t('Image style(@delta): @style', [
          '@delta' => $delta,
          '@style' => $image_styles[$image_style],
        ]);
      }
      else {
        $summary[] = t('Image style(@delta): Original image', ['@delta' => $delta]);
      }
    }

    $link_types = [
      'content' => t('Linked to content'),
      'file' => t('Linked to file'),
    ];
    // Display this setting only if image is linked.
    $image_link_setting = $this->getSetting('image_link');
    if (isset($link_types[$image_link_setting])) {
      $summary[] = $link_types[$image_link_setting];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    if (empty($images = $this->getEntitiesToView($items, $langcode))) {
      // Early opt-out if the field is empty.
      return $elements;
    }

    $image_styles = $this->getSetting('image_styles');
    foreach ($image_styles as $breakpoint_width => $image_style_name) {
      if (empty(trim($image_style_name))) {
        continue;
      }
      /** @var \Drupal\image\ImageStyleInterface $image_style */
      $image_style = $this->imageStyleStorage->load($image_style_name);
      /** @var \Drupal\file\FileInterface[] $images */
      foreach ($images as $delta => $image) {
        $image_uri = $image->getFileUri();
        $url = $image_style ? $image_style->buildUrl($image_uri) : \Drupal::service('file_url_generator')->generateAbsoluteString($image_uri);
        $url = \Drupal::service('file_url_generator')->transformRelative($url);

        // Add cacheability metadata from the image and image style.
        $cacheability = CacheableMetadata::createFromObject($image);
        if ($image_style) {
          $cacheability->addCacheableDependency(CacheableMetadata::createFromObject($image_style));
        }

        $elements[$delta][$breakpoint_width] = ['#markup' => $url];
        $cacheability->applyTo($elements[$delta][$breakpoint_width]);
      }
    }
    return $elements;
  }

}
