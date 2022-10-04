<?php

namespace Drupal\mass_utility\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\mass_content\Entity\Bundle\node\NodeBundle;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\redirect\RedirectRepository;
use Psr\Container\ContainerInterface;

/**
 * Provides a Move Redirects entity form.
 */
class MoveRedirectsForm extends ContentEntityForm {

  protected RedirectRepository $redirectRepository;

  public function __construct($redirectRepository) {
    $this->redirectRepository = $redirectRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('redirect.repository'),
    );
  }

  /**
   * User must have edit access and the node must be in Trash.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeBundle $node, AccountInterface $account) {
    return AccessResult::allowedIf($node->access('edit', $account) && $node->getModerationState()->getString() == MassModeration::TRASH);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_utility_move_redirects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $items = [];

    /** @var NodeBundle $node */
    $node = $this->getEntity();
    $items = $this->getRedirectItems($node, $items);
    $items = $this->getAliasItems($node, $items);

    $form['target'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Pick a target for the URLs'),
      '#required' => TRUE,
    ];

    $form['list'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => 'The following URLs will be redirected',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing yet.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $this->getEntity();
    $redirects = $this->redirectRepository->findByDestinationUri(['internal:/node/' . $node->id()]);
    foreach ($redirects as $redirect) {
      $redirect->setRedirect('node/' . $form['target']);
    }
    $this->messenger()->addStatus($this->t('The URLs have been redirected.'));
  }

  /**
   * Get any redirects to move.
   *
   * @param \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node
   * @param array $items
   *
   * @return array
   */
  public function getRedirectItems(NodeBundle $node, array $items): array {
    $redirects = $this->redirectRepository->findByDestinationUri(['internal:/node/' . $node->id()]);
    foreach ($redirects as $redirect) {
      $items[] = $redirect->getSourceUrl();
    }
    return $items;
  }

  /**
   * Get any potential alias.
   *
   * @param \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node
   * @param array $items
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getAliasItems(NodeBundle $node, array $items): array {
    $url = $node->toUrl()->toString();
    // Strip off unwanted suffix
    $url = str_replace('---unpublished', '', $url);
    if (strpos($url, 'node/') === FALSE) {
      $items[] = Link::fromTextAndUrl($url, Url::fromUserInput($url));
    }
    return $items;
  }

}
