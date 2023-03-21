<?php

namespace Drupal\scheduler_media;

use Drupal\media\Entity\MediaType;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaTypeForm;
use Drupal\media\MediaForm;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manipulates entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 *
 * @internal
 */
class EntityTypeInfo implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The scheduler media manager service.
   *
   * @var \Drupal\scheduler_media\SchedulerMediaManager
   */
  protected $schedulerMediaManager;

  /**
   * EntityTypeInfo constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   Date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler service.
   * @param \Drupal\scheduler_media\SchedulerMediaManager $scheduler_media_manager
   *   Scheduler media manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, ConfigFactory $config_factory, DateFormatter $date_formatter, ModuleHandler $module_handler, SchedulerMediaManager $scheduler_media_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->config = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
    $this->schedulerMediaManager = $scheduler_media_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('scheduler_media.manager')
    );
  }

  /**
   * Gets the "extra fields" for a bundle.
   *
   * @return array
   *   A nested array of 'pseudo-field' elements. Each list is nested within the
   *   following keys: entity type, bundle name, context (either 'form' or
   *   'display'). The keys are the name of the elements as appearing in the
   *   renderable array (either the entity form or the displayed entity). The
   *   value is an associative array:
   *   - label: The human readable name of the element. Make sure you sanitize
   *     this appropriately.
   *   - description: A short description of the element contents.
   *   - weight: The default weight of the element.
   *   - visible: (optional) The default visibility of the element. Defaults to
   *     TRUE.
   *   - edit: (optional) String containing markup (normally a link) used as the
   *     element's 'edit' operation in the administration interface. Only for
   *     'form' context.
   *   - delete: (optional) String containing markup (normally a link) used as
   *     the element's 'delete' operation in the administration interface. Only
   *     for 'form' context.
   *
   * @see hook_entity_extra_field_info()
   */
  public function entityExtraFieldInfo() {
    $return = [];

    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    foreach ($types as $type) {
      $publishing_enabled = $this->schedulerMediaManager->isDefaultSetting($type, 'publish_enable');
      $unpublishing_enabled = $this->schedulerMediaManager->isDefaultSetting($type, 'unpublish_enable');
      if ($publishing_enabled || $unpublishing_enabled) {
        $return['media'][$type->get('type')]['form']['scheduler_media_settings'] = [
          'label' => t('Scheduler Dates'),
          'description' => t('Fieldset containing Scheduler Publish-on and Unpublish on date input fields'),
          'weight' => 20,
        ];
      }
    }

    return $return;
  }

  /**
   * Alters bundle forms to enforce revision handling.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   *
   * @see hook_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof MediaTypeForm) {
      $type = $form_object->getEntity();
      $form['scheduler_media'] = [
        '#type' => 'details',
        '#title' => t('Scheduler'),
        '#weight' => 35,
        '#group' => 'additional_settings',
      ];

      // Publishing options.
      $form['scheduler_media']['publish'] = [
        '#type' => 'details',
        '#title' => t('Publishing'),
        '#weight' => 1,
        '#group' => 'scheduler_media',
        '#open' => TRUE,
      ];
      $form['scheduler_media']['publish']['scheduler_publish_enable'] = [
        '#type' => 'checkbox',
        '#title' => t('Enable scheduled publishing for this content type'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'publish_enable'),
      ];
      $form['scheduler_media']['publish']['scheduler_publish_touch'] = [
        '#type' => 'checkbox',
        '#title' => t('Change content creation time to match the scheduled publish time'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'publish_touch'),
        '#states' => [
          'visible' => [
            ':input[name="scheduler_publish_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['scheduler_media']['publish']['scheduler_publish_required'] = [
        '#type' => 'checkbox',
        '#title' => t('Require scheduled publishing'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'publish_required'),
        '#states' => [
          'visible' => [
            ':input[name="scheduler_publish_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['scheduler_media']['publish']['scheduler_publish_revision'] = [
        '#type' => 'checkbox',
        '#title' => t('Create a new revision on publishing'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'publish_revision'),
        '#states' => [
          'visible' => [
            ':input[name="scheduler_publish_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['scheduler_media']['publish']['advanced'] = [
        '#type' => 'details',
        '#title' => t('Advanced options'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="scheduler_publish_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['scheduler_media']['publish']['advanced']['scheduler_publish_past_date'] = [
        '#type' => 'radios',
        '#title' => t('Action to be taken for publication dates in the past'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'publish_past_date'),
        '#options' => [
          'error' => t('Display an error message - do not allow dates in the past'),
          'publish' => t('Publish the content immediately after saving'),
          'schedule' => t('Schedule the content for publication on the next cron run'),
        ],
      ];

      // Unpublishing options.
      $form['scheduler_media']['unpublish'] = [
        '#type' => 'details',
        '#title' => t('Unpublishing'),
        '#weight' => 2,
        '#group' => 'scheduler_media',
        '#open' => TRUE,
      ];
      $form['scheduler_media']['unpublish']['scheduler_unpublish_enable'] = [
        '#type' => 'checkbox',
        '#title' => t('Enable scheduled unpublishing for this content type'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'unpublish_enable'),
      ];
      $form['scheduler_media']['unpublish']['scheduler_unpublish_required'] = [
        '#type' => 'checkbox',
        '#title' => t('Require scheduled unpublishing'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'unpublish_required'),
        '#states' => [
          'visible' => [
            ':input[name="scheduler_unpublish_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['scheduler_media']['unpublish']['scheduler_unpublish_revision'] = [
        '#type' => 'checkbox',
        '#title' => t('Create a new revision on unpublishing'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'unpublish_revision'),
        '#states' => [
          'visible' => [
            ':input[name="scheduler_unpublish_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['scheduler_media']['media_edit_layout'] = [
        '#type' => 'details',
        '#title' => t('Node edit page layout'),
        '#weight' => 3,
        '#group' => 'scheduler_media',
        // The #states processing only caters for AND and does not do OR. So to set
        // the state to visible if either of the boxes are ticked we use the fact
        // that logical 'X = A or B' is equivalent to 'not X = not A and not B'.
        '#states' => [
          '!visible' => [
            ':input[name="scheduler_publish_enable"]' => ['!checked' => TRUE],
            ':input[name="scheduler_unpublish_enable"]' => ['!checked' => TRUE],
          ],
        ],
      ];
      // @todo Worthwhile to port this to D8 now form displays are configurable?
      $form['scheduler_media']['media_edit_layout']['scheduler_fields_display_mode'] = [
        '#type' => 'radios',
        '#title' => t('Display scheduling options as'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'fields_display_mode'),
        '#options' => [
          'vertical_tab' => t('Vertical tab'),
          'fieldset' => t('Separate fieldset'),
        ],
        '#description' => t('Use this option to specify how the scheduling options will be displayed when editing a media.'),
      ];
      $form['scheduler_media']['media_edit_layout']['scheduler_expand_fieldset'] = [
        '#type' => 'radios',
        '#title' => t('Expand fieldset or vertical tab'),
        '#default_value' => $this->schedulerMediaManager->isDefaultSetting($type, 'expand_fieldset'),
        '#options' => [
          'when_required' => t('Expand only when a scheduled date exists or when a date is required'),
          'always' => t('Always open the fieldset or vertical tab'),
        ],
      ];

      $form['#entity_builders'][] = [EntityTypeInfo::class, 'bundleFormSubmitSettings'];
    }
    elseif ($form_object instanceof MediaForm) {
      $bundle = $form_object->getEntity()->bundle();
      $type = \Drupal::entityTypeManager()->getStorage('media_type')
        ->load($form_object->getEntity()->bundle());

      $publishing_enabled = $this->schedulerMediaManager->isDefaultSetting($type, 'publish_enable');
      $unpublishing_enabled = $this->schedulerMediaManager->isDefaultSetting($type, 'unpublish_enable');

      $media = $form_object->getEntity();

      // Hide these by default so they can't be changed.
      $form['publish_state']['#access'] = FALSE;
      $form['unpublish_state']['#access'] = FALSE;

      // If neither publishing nor unpublishing are enabled for this media type then
      // the only thing to do is remove the fields from the form, then exit.
      if (!$publishing_enabled && !$unpublishing_enabled) {
        unset($form['publish_on']);
        unset($form['unpublish_on']);
        return;
      }

      $date_only_allowed = $this->schedulerMediaManager->setting('allow_date_only');

      // publish_on date is required if the content type option is set and the
      // media is being created or it currently has a scheduled publishing date.
      $publishing_required = $this->schedulerMediaManager->isDefaultSetting($type, 'publish_required')
       && ($media->isNew() || (!$media->isPublished() && !empty($media->publish_on->value)));

      $unpublishing_required = $this->schedulerMediaManager->isDefaultSetting($type, 'unpublish_required')
       && ($media->isNew() || $media->isPublished() || !empty($media->publish_on->value));

      // Create a 'details' field group to wrap the scheduling fields, and expand it
      // if publishing or unpublishing is required, if a date already exists or the
      // fieldset is configured to be always expanded.
      $has_data = !empty($media->publish_on->value) || !empty($media->unpublish_on->value);
      $always_expand = $this->schedulerMediaManager->isDefaultSetting($type, 'expand_fieldset') === 'always';
      $expand_details = $publishing_required || $unpublishing_required || $has_data || $always_expand;

      // Create the group for the fields.
      $form['scheduler_media_settings'] = [
        '#type' => 'details',
        '#title' => t('Scheduling options'),
        '#open' => $expand_details,
        '#weight' => 35,
        '#attributes' => ['class' => ['scheduler_media-form']],
        '#optional' => FALSE,
      ];

      // Attach the fields to group.
      $form['unpublish_on']['#group'] = 'scheduler_media_settings';
      $form['publish_on']['#group'] = 'scheduler_media_settings';

      $form['publish_state']['#group'] = 'scheduler_media_settings';
      $form['unpublish_state']['#group'] = 'scheduler_media_settings';

      // Show the field group as a vertical tab if this option is enabled.
      $use_vertical_tabs = $this->schedulerMediaManager->isDefaultSetting($type, 'fields_display_mode') === 'vertical_tab';
      if ($use_vertical_tabs) {
        $form['scheduler_media_settings']['#group'] = 'advanced';
      }

      // Define the descriptions depending on whether the time can be skipped.
      $descriptions = [];
      if ($date_only_allowed) {
        $descriptions['format'] = t('Enter a date. The time part is optional.');
        // Show the default time so users know what they will get if they do not
        // enter a time.
        $default_time = strtotime($this->schedulerMediaManager->setting('default_time'));
        $descriptions['default'] = t('The default time is @default_time.', [
          '@default_time' => $this->dateFormatter->format($default_time, 'custom', 'H:i:s'),
        ]);
      }
      else {
        $descriptions['format'] = t('Enter a date and time.');
      }

      if (!$publishing_required) {
        $descriptions['blank'] = t('Leave the date blank for no scheduled publishing.');
      }

      $form['publish_on']['#access'] = $publishing_enabled;
      $form['publish_on']['widget'][0]['value']['#required'] = $publishing_required;
      $form['publish_on']['widget'][0]['value']['#description'] = Xss::filter(implode(' ', $descriptions));

      if (!$unpublishing_required) {
        $descriptions['blank'] = t('Leave the date blank for no scheduled unpublishing.');
      }
      else {
        unset($descriptions['blank']);
      }

      $form['unpublish_on']['#access'] = $unpublishing_enabled;
      $form['unpublish_on']['widget'][0]['value']['#required'] = $unpublishing_required;
      $form['unpublish_on']['widget'][0]['value']['#description'] = Xss::filter(implode(' ', $descriptions));

      if (!$this->currentUser->hasPermission('schedule publishing of nodes')) {
        // Do not show the scheduler_media fields for users who do not have permission.
        $form['scheduler_media_settings']['#access'] = FALSE;

        // @todo Find a more elegant solution for bypassing the validation of
        // scheduler_media fields when the user does not have permission.
        // @see https://www.drupal.org/media/2651448
        $form['publish_on']['widget'][0]['value']['#required'] = FALSE;
        $form['unpublish_on']['widget'][0]['value']['#required'] = FALSE;
      }

      // Check which widget type is set for the scheduler_media fields, and give a warning
      // if the wrong one has been set and provide a hint and link to fix it.
      $storage_form_display = $form_state->getStorage()['form_display'];
      $content = $storage_form_display->get('content');
      $pluginDefinitions = $storage_form_display->get('pluginManager')->getDefinitions();
      $correct_widget_id = 'datetime_timestamp_no_default';
      $fields = [
        'publish_on' => $publishing_enabled,
        'unpublish_on' => $unpublishing_enabled,
      ];
      foreach ($fields as $field => $enabled) {
        $actual_widget_id = $content[$field]['type'];
        if ($enabled && $actual_widget_id != $correct_widget_id) {
          \Drupal::messenger()->addWarning(t('The widget for field %field is incorrectly set to %wrong. This should be changed to %correct by an admin user via Field UI <a href="@link">content type form display</a> :not_available', [
            '%field' => (string) $form[$field]['widget']['#title'],
            '%correct' => (string) $pluginDefinitions[$correct_widget_id]['label'],
            '%wrong' => (string) $pluginDefinitions[$actual_widget_id]['label'],
            ':not_available' => $this->moduleHandler->moduleExists('field_ui') ? '' : ('(' . t('not available') . ')'),
          ]), FALSE);
        }
      }
    }
    elseif ($form_id == 'devel_generate_form_content') {

      // Add an extra column to the media_types table to show which type are enabled
      // for scheduled publishing and unpublishing.
      $publishing_enabled_types = array_keys(_scheduler_media_get_scheduler_media_enabled_media_types('publish'));
      $unpublishing_enabled_types = array_keys(_scheduler_media_get_scheduler_media_enabled_media_types('unpublish'));

      $form['media_types']['#header']['scheduler_media'] = t('Scheduler settings');

      foreach (array_keys($form['media_types']['#options']) as $type) {
        $items = [];
        if (in_array($type, $publishing_enabled_types)) {
          $items[] = t('Enabled for publishing');
        }
        if (in_array($type, $unpublishing_enabled_types)) {
          $items[] = t('Enabled for unpublishing');
        }
        if (empty($items)) {
          $scheduler_media_settings = t('None');
        }
        else {
          $scheduler_media_settings = [
            'data' => [
              '#theme' => 'item_list',
              '#items' => $items,
            ],
          ];
        }
        $form['media_types']['#options'][$type]['scheduler_media'] = $scheduler_media_settings;
      }

      // Add form items to specify what proportion of generated medias should have a
      // publish-on and unpublish-on date assigned. See hook_media_presave() for the
      // code which sets the media values.
      $form['scheduler_media_publishing'] = [
        '#type' => 'number',
        '#title' => t('Publishing date for Scheduler'),
        '#description' => t('Enter the percentage of randomly selected Scheduler-enabled medias to be given a publish-on date. Enter 0 for none, 100 for all. The date and time will be random within the range starting at media creation date, up to a time in the future matching the same span as selected above for media creation date.'),
        '#default_value' => 50,
        '#required' => TRUE,
        '#min' => 0,
        '#max' => 100,
      ];
      $form['scheduler_media_unpublishing'] = [
        '#type' => 'number',
        '#title' => t('Unpublishing date for Scheduler'),
        '#description' => t('Enter the percentage of randomly selected Scheduler-enabled medias to be given an unpublish-on date. Enter 0 for none, 100 for all. The date and time will be random within the range starting at the later of media creation date and publish-on date, up to a time in the future matching the same span as selected above for media creation date.'),
        '#default_value' => 50,
        '#required' => TRUE,
        '#min' => 0,
        '#max' => 100,
      ];
    }
  }

  /**
   * Redirect content entity edit forms on save, if there is a pending revision.
   *
   * When saving their changes, editors should see those changes displayed on
   * the next page.
   *
   * @param string $entity_type
   *   The entity type to operate on.
   * @param \Drupal\media\Entity\MediaType $type
   *   A MediaType entity that has the third party settings.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function bundleFormSubmitSettings($entity_type, MediaType $type, array &$form, FormStateInterface $form_state) {
    $type->setThirdPartySetting('scheduler_media', 'expand_fieldset', $form_state->getValue('scheduler_expand_fieldset'));
    $type->setThirdPartySetting('scheduler_media', 'fields_display_mode', $form_state->getValue('scheduler_fields_display_mode'));
    $type->setThirdPartySetting('scheduler_media', 'publish_enable', $form_state->getValue('scheduler_publish_enable'));
    $type->setThirdPartySetting('scheduler_media', 'publish_past_date', $form_state->getValue('scheduler_publish_past_date'));
    $type->setThirdPartySetting('scheduler_media', 'publish_required', $form_state->getValue('scheduler_publish_required'));
    $type->setThirdPartySetting('scheduler_media', 'publish_revision', $form_state->getValue('scheduler_publish_revision'));
    $type->setThirdPartySetting('scheduler_media', 'publish_touch', $form_state->getValue('scheduler_publish_touch'));
    $type->setThirdPartySetting('scheduler_media', 'unpublish_enable', $form_state->getValue('scheduler_unpublish_enable'));
    $type->setThirdPartySetting('scheduler_media', 'unpublish_required', $form_state->getValue('scheduler_unpublish_required'));
    $type->setThirdPartySetting('scheduler_media', 'unpublish_revision', $form_state->getValue('scheduler_unpublish_revision'));
  }

}
