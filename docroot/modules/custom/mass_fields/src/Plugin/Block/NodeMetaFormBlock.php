<?php

namespace Drupal\mass_fields\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a 'Node Meta Form' block.
 *
 * @Block(
 *   id = "node_meta_form_block",
 *   admin_label = @Translation("Node Meta Form Block"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE, label = @Translation("Node"))
 *   }
 * )
 */
class NodeMetaFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected AccountInterface $currentUser;
  protected DateFormatterInterface $dateFormatter;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('date.formatter'),
    );
  }

  public function build(): array {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    if (!$node instanceof NodeInterface || !$this->currentUser->hasPermission('administer nodes')) {
      return [];
    }

    $form['meta'] = [
      '#type' => 'container',
      '#open' => TRUE,
      '#title' => $this->t('Published'),
      '#attributes' => ['class' => ['entity-meta__header']],
    ];

    $form['meta']['published'] = [
      '#type' => 'item',
      '#markup' => \Drupal::service('content_moderation.moderation_information')->getWorkflowForEntity($node)->getTypePlugin()->getState($node->moderation_state->value)->label(),
      '#access' => !$node->isNew(),
      '#wrapper_attributes' => ['class' => ['entity-meta__title']],
    ];

    $form['meta']['changed'] = [
      '#type' => 'item',
      '#title' => $this->t('Last saved'),
      '#markup' => !$node->isNew()
        ? $this->dateFormatter->format($node->getChangedTime(), 'short')
        : $this->t('Not saved yet'),
      '#wrapper_attributes' => ['class' => ['entity-meta__last-saved']],
    ];

    $form['meta']['author'] = [
      '#type' => 'item',
      '#title' => $this->t('Author'),
      '#attributes' => ['class' => ['entity-meta__author']],
    ];

    // Add link for contacting a user in the publish area.
    $title_prefix = 'Outreach about Mass.gov page "';
    $nid = $node->id();
    $title_suffix = '" (' . $nid . ')';
    $prefix_suffix_combo_lenth = strlen($title_prefix . $title_suffix);
    // Fits title into 100 characters or less.
    $title_max_length = 100 - $prefix_suffix_combo_lenth;
    $title = (string) $node->getTitle();
    // Truncates title as needed when forming combined `$title_nid` string.
    $title_nid = $title_prefix .
      ((strlen($title) > $title_max_length) ? substr($title, 0, $title_max_length - 1) . '…' : $title) .
      $title_suffix;
    $author = $node->getOwner();
    $contact_url = new Url('entity.user.contact_form', ['user' => $author->id()], [
      // Set 'query' option for use by Prepopulate contrib module.
      // Will be used to pre-fill subject in contact form.
      'query' => [
        'edit[subject]' => $title_nid,
      ],
      // Set 'attributes' option for URL.
      'attributes' => [
        'title' => t('Contact the author of this content.'),
      ],
    ]);
    if ($contact_url->access(\Drupal::currentUser())) {
      $form['meta']['author']['#markup'] = new FormattableMarkup('@username - @contact', [
        '@username' => $author->getDisplayName(),
        '@contact' => Link::fromTextAndUrl(t('Contact the author'), $contact_url)->toString(),
      ]);
    }

    // Add Node ID to meta details.
    $form['meta']['node_id'] = [
      '#type' => 'item',
      '#title' => t('Node ID'),
      '#markup' => $node->id(),
      '#wrapper_attributes' => [
        'class' => 'container-inline',
      ],
    ];

    return $form;
  }

}
