<?php

namespace Drupal\mass_redirects\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mass_content\Entity\Bundle\node\NodeBundle;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Move Redirects entity form.
 */
class MoveRedirectsForm extends ContentEntityForm {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
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
    /** @var NodeBundle $node */
    $node = $this->getEntity();
    foreach ($this->getRedirects($node) as $redirect) {
      // Automatic redirects back to this page can be repointed.
      if (!in_array($redirect->getRedirect()['uri'], ['internal:/node/' . $node->id(), 'entity:node/' . $node->id()], TRUE)) {
        return $this->buildFormInBound($form, $form_state, $node);
      }
    }

    return $this->buildFormOutbound($form, $this->shortenUrl($node));
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
    if ($redirects = $this->getRedirects($node)) {
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
      $form_state->setRedirectUrl($node->toUrl('redirects', ['path_processing' => FALSE]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormOutbound(array &$form, FormStateInterface $form_state) {
    $done = [];
    $target_uri = 'node/' . $form_state->getValue('target');
    $target_entity = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('target'));

    $node = $this->getEntity();
    $redirects = $this->getRedirects($node);
    if (!$redirects) {
      $redirect = Redirect::create();
      $redirect->setSource($this->shortenUrl($node));
      $redirect->setLanguage($node->language()->getId());
      $redirect->setStatusCode($this->config('redirect.settings')->get('default_status_code'));
      $redirects = [$redirect];
    }
    foreach ($redirects as $redirect) {
      $redirect->setRedirect($target_uri);
      if ($redirect->validate()->getEntityViolations()->count()) {
        $this->messenger()->addError($this->t("Unable to redirect URLs. Please report this content id."));
        break;
      }
      $redirect->save();
      $done[] = $redirect->getSourceUrl();
    }
    $this->messenger()->addStatus($this->t('Redirected @list to <a href="@href">@title</a>.', ['@list' => implode(', ', $done), '@title' => $target_entity->label(), '@href' => $target_entity->toUrl()->toString()]));
    $form_state->setRedirectUrl($node->toUrl());
  }

  /**
   * Gets redirects whose source is this page's public alias.
   */
  public function getRedirects(NodeBundle $node): array {
    return $this->entityTypeManager->getStorage('redirect')->loadByProperties([
      'redirect_source__path' => $this->shortenUrl($node),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    // Not saving.
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
    if ($redirects = $this->getRedirects($node)) {
      foreach ($redirects as $redirect) {
        $target = $redirect->getRedirect();
        $params = Url::fromUri($target['uri'])->getRouteParameters();
        $entity_type = key($params);
        $target_entity = $this->entityTypeManager->getStorage($entity_type)->load($params[$entity_type]);
        $parts = [
          '@source_path' => $this->t('<a href="@href">@title</a>', ['@title' => $redirect->getSourceUrl(), '@href' => $redirect->getSourceUrl()]),
          '@href' => $target_entity->toUrl()->toString(),
          '@title' => $target_entity->label(),
        ];
        $items[] = $this->t('@source_path currently points to <a href="@href">@href</a>', $parts);
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

  public function buildFormOutbound(array $form, string $source): array {
    $form['target'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Pick a target for the URL'),
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];
    $form['list'] = [
      '#theme' => 'item_list',
      '#items' => [$source],
      '#title' => $this->t('The following URL will be redirected'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add redirect'),
      '#submit' => ['::submitFormOutbound'],
    ];
    return $form;
  }

}
