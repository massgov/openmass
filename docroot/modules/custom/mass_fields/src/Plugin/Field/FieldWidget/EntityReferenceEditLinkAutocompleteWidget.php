<?php

namespace Drupal\mass_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mass_content\Entity\Bundle\node\ContactInformationBundle;
use Drupal\mass_content\Entity\Bundle\node\PersonBundle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to alter original entity reference autocomplete tags widget.
 */
class EntityReferenceEditLinkAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->userStorage = $container->get('entity_type.manager')->getStorage('user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $referencedEntities = $items->referencedEntities();
    $referencedEntity = $referencedEntities[$delta] ?? NULL;

    if (!$referencedEntity) {
      return parent::formElement($items, $delta, $element, $form, $form_state);
    }

    if (!$referencedEntity instanceof ContactInformationBundle && !$referencedEntity instanceof PersonBundle) {
      return parent::formElement($items, $delta, $element, $form, $form_state);
    }

    $user = $this->userStorage->load($this->currentUser->id());
    /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
    if (!$referencedEntity->access('update', $user)) {
      return parent::formElement($items, $delta, $element, $form, $form_state);
    }

    $element += [
      '#attached' => [
        'library' => ['mass_fields/reference.field'],
      ],
    ];

    $link = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['reference-edit-link-wrapper form-item'],
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Open and Edit (new tab)'),
        '#url' => $referencedEntity->toUrl('edit-form'),
        '#attributes' => [
          'class' => ['button reference-edit-link'],
          'target' => '_blank',
        ],
      ],
    ];

    return parent::formElement($items, $delta, $element, $form, $form_state) + ['_link' => $link];
  }

}
