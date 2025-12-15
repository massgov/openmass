<?php

namespace Drupal\mass_alerts\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class AlertsExclusionsForm extends ConfigFormBase {

  public const CONFIG_NAME = 'mass_alerts.settings';

  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  public function getFormId() {
    return 'mass_alerts_exclusions_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $instructions = [
      $this->t('Use this configuration to disable the display on sitewide alerts on pages related to certain organizations. This affects all sitewide alerts. This does not affect organization or page based alerts.'),
      $this->t('These orgs are stored in the database and a code change is not needed to add or remove organizations.'),
    ];
    $form['#prefix'] = '<div class="description">' . implode('<br>', $instructions) . '</div>';


    if ($form_state->get('initialized') !== TRUE) {
      $stored = array_values(array_unique(array_map('intval', (array) ($config->get('excluded_org_ids') ?? []))));
      $form_state->set('excluded_org_nids', $stored);
      $form_state->set('num_rows', max(1, count($stored)));
      $form_state->set('initialized', TRUE);
    }

    $nids = (array) $form_state->get('excluded_org_nids');
    $num_rows = (int) $form_state->get('num_rows');

    $form['#tree'] = TRUE;

    $form['items'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Hidden organizations'),
    ];

    for ($i = 0; $i < $num_rows; $i++) {
      $default_entity = NULL;
      if (!empty($nids[$i])) {
        $node = Node::load((int) $nids[$i]);
        if ($node instanceof NodeInterface && $node->bundle() === 'org_page') {
          $default_entity = $node;
        }
      }

      $form['items'][$i]['org'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $i === 0 ? $this->t('Organization') : $this->t('Organization @num', ['@num' => $i + 1]),
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['org_page']],
        '#tags' => FALSE,
        '#default_value' => $default_entity,
        '#required' => FALSE,
      ];
    }

    // Buttons inside the fieldset.
    $form['items']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another'),
      '#submit' => ['::addMoreSubmit'],
      '#limit_validation_errors' => [['items']],
    ];

    if ($num_rows > 1) {
      $form['items']['remove_last'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove last'),
        '#submit' => ['::removeLastSubmit'],
        '#limit_validation_errors' => [['items']],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $this->syncStateFromFormValues($form_state);
    $form_state->set('num_rows', (int) $form_state->get('num_rows') + 1);
    $form_state->setRebuild(TRUE);
  }

  public function removeLastSubmit(array &$form, FormStateInterface $form_state) {
    $this->syncStateFromFormValues($form_state);
    $num_rows = max(1, (int) $form_state->get('num_rows') - 1);
    $form_state->set('num_rows', $num_rows);

    $nids = (array) $form_state->get('excluded_org_nids');
    $form_state->set('excluded_org_nids', array_slice($nids, 0, $num_rows));
    $form_state->setRebuild(TRUE);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->syncStateFromFormValues($form_state);

    $nids = array_values(array_unique(array_filter(
      (array) $form_state->get('excluded_org_nids'),
      static fn($v) => (int) $v > 0
    )));

    $clean = [];
    if ($nids) {
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        if ($node instanceof NodeInterface && $node->bundle() === 'org_page') {
          $clean[] = (int) $node->id();
        }
      }
    }

    $form_state->set('excluded_org_nids', $clean);
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nids = array_values(array_unique(array_map('intval', (array) $form_state->get('excluded_org_nids'))));
    $this->configFactory()->getEditable(self::CONFIG_NAME)
      ->set('excluded_org_ids', $nids)
      ->save();

    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Hidden organizations list has been saved.'));
  }

  private function syncStateFromFormValues(FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('items', []);
    $nids = [];
    foreach ($values as $key => $row) {
      if (!is_numeric($key)) {
        continue;
      }
      $val = $row['org'] ?? NULL;
      $nid = is_array($val) ? (int) ($val['target_id'] ?? 0) : (int) $val;
      if ($nid > 0) {
        $nids[] = $nid;
      }
    }
    $form_state->set('excluded_org_nids', $nids);
  }

}
