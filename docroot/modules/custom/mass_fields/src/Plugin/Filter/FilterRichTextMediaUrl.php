<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\mass_fields\MassUrlReplacementService;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Filters lang attributes in the rich text.
 *
 * @Filter(
 *   id = "filter_richtext_media_url",
 *   title = @Translation("Filter/Replace media URLs"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class FilterRichTextMediaUrl extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The URL replacement service.
   *
   * @var \Drupal\mass_fields\MassUrlReplacementService
   */
  protected MassUrlReplacementService $urlReplacementService;

  /**
   * Constructs a new FilterMyModuleProcessUrls.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\mass_fields\MassUrlReplacementService $urlReplacementService
   *   The URL replacement service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MassUrlReplacementService $urlReplacementService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->urlReplacementService = $urlReplacementService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mass_fields.url_replacement_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $processedText = $this->urlReplacementService->processText($text);
    return new FilterProcessResult($processedText);
  }

}
