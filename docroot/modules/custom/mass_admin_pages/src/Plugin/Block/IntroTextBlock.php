<?php

namespace Drupal\mass_admin_pages\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block for the intro text on the node add page.
 */
#[Block(
  id: 'intro_text_block',
  admin_label: new TranslatableMarkup('Intro Text Block'),
  category: new TranslatableMarkup('Mass.gov'),
)]
class IntroTextBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'block_intro_text_string' => $this->t('Intro text for the Add Content page'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['node_add_page_intro_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Block contents'),
      '#description' => $this->t('This text will appear in the intro text block.'),
      '#default_value' => $this->configuration['block_intro_text_string'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['block_intro_text_string']
      = $form_state->getValue('node_add_page_intro_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->configuration['block_intro_text_string'],
    ];
  }

}
