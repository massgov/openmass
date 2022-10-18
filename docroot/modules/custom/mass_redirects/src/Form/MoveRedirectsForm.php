<?php

namespace Drupal\mass_redirects\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
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
  public static function access(NodeBundle $node, AccountInterface $account) {
    return AccessResult::allowedIf($node->getModerationState()->getString() == MassModeration::TRASH);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_redirects_move_redirects';
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
      $form = $this->buildFormInBound($form, $form_state, $node);
    }
    else {
      $form = $this->buildFormOutbound($form, $items);
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
   * Submit handler for inbound.
   */
  public function submitFormInBound(array $form, FormStateInterface $form_state) {
    /** @var NodeBundle $node */
    $node = $this->getEntity();
    $short_url = $this->shortenUrl($node);
    if ($redirects = $this->redirectRepository->findBySourcePath($short_url)) {
      foreach ($redirects as $redirect) {
        $redirect->setRedirect($node->toUrl('canonical', ['alias' => TRUE])->toString());
        $redirect->save();
      }
      $parts = [
        '@short_url' => $this->t('<a href="@href">@text</a>', ['@href' => '/' . $short_url, '@text' => $short_url]),
        '@title' => $node->label(),
        '@href' => $node->toUrl()->toString(),
      ];
      $this->messenger()->addStatus($this->t('Removed redirect. @short_url now points to <a href="@href">@title</a>.', $parts));
      $form_state->setRedirectUrl($node->toUrl('redirects'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormOutbound(array &$form, FormStateInterface $form_state) {
    $done = [];
    $target_uri = 'node/' . $form_state->getValues()['target'];
    $params = Url::fromUri('internal:/' . $target_uri)->getRouteParameters();
    $entity_type = key($params);
    $target_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);

    $node = $this->getEntity();
    $redirects = $this->getRedirects($node);
    foreach ($redirects as $redirect) {
      $redirect->setRedirect($target_uri);
      $redirect->save();
      $done[] = $this->t('<a href="@href">@title</a>', ['@href' => $redirect->getSourceUrl(), '@title' => $redirect->getSourceUrl()]);
    }
    $aliases = $this->getAliasItems($node);
    foreach ($aliases as $alias) {
      $redirect = Redirect::create();
      $redirect->setSource($alias);
      $redirect->setRedirect('node/' . $node->id());
      $redirect->setLanguage($node->language()->getId());
      $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
      $failed = $redirect->validate()->getEntityViolations()->count();
      if ($failed) {
        // We should not get here. If we do, we'll want to adjust code so we don't try to create an invalid redirect.
        $this->messenger()->addError($this->t("Unable to redirect URLs. Please report this content id."));
        break;
      }
      else {
        $success = $redirect->save();
        $done[] = $this->t('<a href="@href">@title</a>', ['@href' => $redirect->getSourceUrl(), '@title' => $redirect->getSourceUrl()]);
      }
    }
    $this->messenger()->addStatus($this->t('Redirected @list to <a href="@href">@title</a>.', ['@list' => implode(', ', $done), '@title' => $target_entity->label(), '@href' => $target_entity->toUrl()->toString()]));
    $form_state->setRedirectUrl($node->toUrl());
  }

  public function getRedirects(EntityInterface $node): array {
    $id = $node->id();
    $redirects = $this->redirectRepository->findByDestinationUri(["internal:/node/$id", "entity:node/$id"]);
    return $redirects ?? [];
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
   * The alias with 'unpublished' suffix is eligible to be redirected.
   */
  public function getAliasItems(NodeBundle $node): array {
    $short_url = $this->shortenUrl($node);
    if (!$this->redirectRepository->findBySourcePath($short_url)) {
      $items[] = $short_url;
    }
    return $items ?? [];
  }

  public static function shortenUrl(NodeBundle $node): string {
    $url = $node->toUrl()->toString();
    // Strip off unwanted prefix and suffix.
    $url = str_replace('---unpublished', '', $url);
    // Strip leading slash.
    $url = ltrim($url, '/');
    return $url;
  }

  public function buildFormInBound($form, $formState, $node) {
    $items = [];
    $short_url = $this->shortenUrl($node);
    if ($redirects = $this->redirectRepository->findBySourcePath($short_url)) {
      foreach ($redirects as $redirect) {
        $target = $redirect->getRedirect();
        $params = Url::fromUri($target['uri'])->getRouteParameters();
        $entity_type = key($params);
        $target_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
        $parts = [
          '@source_path' => $this->t('<a href="@href">@title</a>', ['@title' => $redirect->getSourceUrl(), '@href' => $redirect->getSourceUrl()]),
          '@href' => $target_entity->toUrl()->toString(),
          '@title' => $target_entity->label(),
        ];
        $items[] = $this->t('@source_path which currently points to <a href="@href">@title</a>?', $parts);
      }
      $form['list'] = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#title' => $this->formatPlural(count($items), 'Remove redirect so you can choose another:', 'Remove redirects so you can choose another.'),
      ];
      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->formatPlural(count($redirects), 'Remove redirect', 'Remove redirects'),
        '#submit' => ['::submitFormInBound'],
      ];
    }
    else {
      $form['sorry'] = [
        '#markup' => $this->t('There are no URLs to point.'),
      ];
    }
    return $form;
  }

  public function buildFormOutbound(array $form, array $items): array {
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
      '#value' => $this->t('Add redirects'),
      '#submit' => ['::submitFormOutbound'],
    ];
    return $form;
  }

}
