<?php

namespace Drupal\mass_redirect_normalizer\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mass_redirect_normalizer\RedirectLinkChangeLog;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms clearing all redirect normalization change log rows.
 */
final class RedirectLinkChangeLogClearForm extends ConfirmFormBase {

  public function __construct(
    private readonly RedirectLinkChangeLog $changeLog,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mass_redirect_normalizer.change_log'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mass_redirect_normalizer_change_log_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return (string) $this->t('Clear all redirect normalizer report records?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('This permanently removes all change log rows.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return (string) $this->t('Clear records');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('view.redirect_link_normalizer_report.page_1');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->changeLog->clearAll();
    $this->messenger()->addStatus($this->t('Redirect normalizer report records were cleared.'));
    $form_state->setRedirect('view.redirect_link_normalizer_report.page_1');
  }

}
