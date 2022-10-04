<?php

namespace Drupal\mass_utility\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mass_content\Entity\Bundle\node\NodeBundle;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\redirect\Entity\Redirect;
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
    $items = $this->getRedirectsItems($node);
    $items = array_merge($items, $this->getAliasItems($node));

    if (empty($items)) {
      $form['sorry'] = [
        '#markup' => $this->t('There are no URLs to move.'),
      ];
    }
    else {
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
        '#submit' => ['::submitFormLocal'],
      ];
    }

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
  public function submitFormLocal(array &$form, FormStateInterface $form_state) {
    $node = $this->getEntity();
    $redirects = $this->getRedirects($node);
    foreach ($redirects as $redirect) {
      $redirect->setRedirect('node/' . $form_state->getValues()['target']);
      $redirect->save();
    }
    $aliases = $this->getAliasItems($node);
    foreach ($aliases as $alias) {
      $redirect = Redirect::create();
      $redirect->setSource($alias);
      $redirect->setRedirect('node/' . $node->id());
      $redirect->setLanguage($node->language()->getId());
      $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
      if ($violations = $redirect->validate()) {
        // We should not get here. If we do, we'll want to adjust code so we don't try to create an invalid redirect.
        $this->messenger()->addError($this->t("Unable to redirect URLs. Please report this content id."));
      }
      else {
        $success = $redirect->save();
        $this->messenger()->addStatus($this->t('The URLs have been redirected.'));
        $form_state->setRedirectUrl($node->toUrl());
      }
    }
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $node
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   */
  public function getRedirects(\Drupal\Core\Entity\EntityInterface $node): array {
    $id = $node->id();
    $redirects = $this->redirectRepository->findByDestinationUri(["internal:/node/$id", "entity:node/$id"]);
    // '--unpublished' are not eligible.
    foreach ($redirects as $redirect) {
      if (strpos($redirect->getSourceUrl(), '---unpublished') === FALSE) {
        $return[] = $redirect;
      }
    }
    return $return ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    // Not saving.
  }

  /**
   * Get any Redirects to move.
   */
  public function getRedirectsItems(NodeBundle $node): array {
    $redirects = $this->getRedirects($node);
    foreach ($redirects as $redirect) {
      $items[] = $redirect->getSourceUrl();
    }
    return $items ?? [];
  }

  /**
   * The alias with 'unpublished' is eligible to be redirected.
   *
   * @param \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node
   * @param array $items
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getAliasItems(NodeBundle $node): array {
    $short_url = $this->shortenUrl($node);
    // Strip leading slash.
    $shorter_url = ltrim($short_url, '/');
    if (!$this->redirectRepository->findBySourcePath($shorter_url)) {
      $items[] = $short_url;
    }
    return $items ?? [];
  }

  /**
   * @param \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node
   *
   * @return string
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function shortenUrl(NodeBundle $node): string {
    $url = $node->toUrl()->toString();
    // Strip off unwanted suffix.
    $url = str_replace('---unpublished', '', $url);
    return $url;
  }

}
