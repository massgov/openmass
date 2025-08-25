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
use Drupal\content_moderation\ModerationInformation;

/**
 * Provides a 'Node Meta Form' block.
 *
 * Displays a compact meta panel for nodes in the admin UI, showing:
 *   - Publication state (from Content Moderation)
 *   - Last saved timestamp
 *   - Author with a quick "Contact the author" link (if allowed)
 *   - Node ID.
 *
 * The block expects a node context and is only visible to users with the
 * 'administer nodes' permission.
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

  /**
   * Current user account.
   */
  protected AccountInterface $currentUser;

  /**
   * Date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Content moderation information service.
   */
  protected ModerationInformation $moderationInformation;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountInterface $current_user,
    DateFormatterInterface $date_formatter,
    ModerationInformation $moderation_information,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('content_moderation.moderation_information'),
    );
  }

  /**
   * Builds the node meta panel.
   *
   * @return array
   *   A render array for the block; empty array when not applicable.
   */
  public function build(): array {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    // Only render for node contexts and when the user can administer nodes.
    if (!$node instanceof NodeInterface || !$this->currentUser->hasPermission('administer nodes')) {
      return [];
    }

    // Container header with the moderation label as the title.
    $form['meta'] = [
      '#type' => 'container',
      '#open' => TRUE,
      '#title' => $this->t('Published'),
      '#attributes' => ['class' => ['entity-meta__header']],
    ];

    // Publication state (hidden for new/unsaved nodes).
    $form['meta']['published'] = [
      '#type' => 'item',
      '#markup' => $this->moderationInformation
        ->getWorkflowForEntity($node)
        ->getTypePlugin()
        ->getState($node->moderation_state->value)
        ->label(),
      '#access' => !$node->isNew(),
      '#wrapper_attributes' => ['class' => ['entity-meta__title']],
    ];

    // Last saved timestamp or a fallback for new nodes.
    $form['meta']['changed'] = [
      '#type' => 'item',
      '#title' => $this->t('Last saved'),
      '#markup' => !$node->isNew()
        ? $this->dateFormatter->format($node->getChangedTime(), 'short')
        : $this->t('Not saved yet'),
      '#wrapper_attributes' => ['class' => ['entity-meta__last-saved']],
    ];

    // Author display name (will be replaced below if contact link is allowed).
    $author = $node->getOwner();
    $form['meta']['author'] = [
      '#type' => 'item',
      '#title' => $this->t('Author'),
      '#markup' => $author->getDisplayName(),
      '#attributes' => ['class' => ['entity-meta__author']],
    ];

    // Build a contact link to the author, pre-populating the subject line.
    $title_prefix = 'Outreach about Mass.gov page "';
    $nid = $node->id();
    $title_suffix = '" (' . $nid . ')';
    $prefix_suffix_combo_length = strlen($title_prefix . $title_suffix);

    // Ensure combined subject stays within 100 characters.
    $title_max_length = 100 - $prefix_suffix_combo_length;
    $title = (string) $node->getTitle();

    // Truncate the node title if needed to fit within the subject limit.
    $title_nid = $title_prefix .
      ((strlen($title) > $title_max_length) ? substr($title, 0, $title_max_length - 1) . '…' : $title) .
      $title_suffix;

    $contact_url = new Url('entity.user.contact_form', ['user' => $author->id()], [
      // Used by Prepopulate module to pre-fill the contact form subject.
      'query' => [
        'edit[subject]' => $title_nid,
      ],
      'attributes' => [
        'title' => $this->t('Contact the author of this content.'),
      ],
    ]);

    // Replace the author markup with a username + contact link when accessible.
    if ($contact_url->access($this->currentUser)) {
      $form['meta']['author']['#markup'] = new FormattableMarkup('@username - @contact', [
        '@username' => $author->getDisplayName(),
        '@contact' => Link::fromTextAndUrl($this->t('Contact the author'), $contact_url)->toString(),
      ]);
    }

    // Add Node ID to meta details.
    $form['meta']['node_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Node ID'),
      '#markup' => $node->id(),
      '#wrapper_attributes' => [
        'class' => 'container-inline',
      ],
    ];

    return $form;
  }

}
