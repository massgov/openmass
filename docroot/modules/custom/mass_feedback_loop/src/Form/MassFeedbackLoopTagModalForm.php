<?php

namespace Drupal\mass_feedback_loop\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MassFeedbackLoopTagModalForm.
 */
class MassFeedbackLoopTagModalForm extends FormBase {

  /**
   * Custom service to fetch content used in feedback author interface.
   *
   * @var \Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher
   */
  protected $contentFetcher;

  /**
   * {@inheritdoc}
   */
  protected $allTags;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassFeedbackLoopContentFetcher $content_fetcher) {
    $this->contentFetcher = $content_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_feedback_loop.content_fetcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_feedback_loop_tag_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $action = NULL,
    $comment_id = NULL,
    $tag_id = NULL,
    $tag_unique_id = NULL
  ) {
    if ($action == 'add') {
      $this->buildAddTagModalForm($form, $comment_id);
    }
    else {
      $this->buildRemoveTagModalForm($form, $comment_id, $tag_id, $tag_unique_id);
    }

    return $form;
  }

  /**
   * Helper function to build Add Tag modal form.
   *
   * @param array $form
   *   Form array.
   * @param int $comment_id
   *   Comment ID number.
   */
  protected function buildAddTagModalForm(array &$form, $comment_id) {
    // Fetches tags.
    $this->setAllTags();

    // Builds list of tags.
    $tag_select_list = ['' => $this->t('- Select a tag -')] + $this->allTags;

    $form['select_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a tag'),
      '#options' => $tag_select_list,
      '#required' => TRUE,
    ];
    $form['add_tag'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add tag'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'use-ajax-submit',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];
    $form['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#ajax' => [
        'callback' => [$this, 'cancelModalFormAjax'],
        'event' => 'click',
      ],
    ];

    // Hidden values needed during form submission.
    $form['action'] = [
      '#type' => 'hidden',
      '#value' => 'add',
    ];
    $form['comment_id'] = [
      '#type' => 'hidden',
      '#value' => $comment_id,
    ];
  }

  /**
   * Helper function to build Remove Tag modal form.
   *
   * @param array $form
   *   Form array.
   * @param int $comment_id
   *   Comment ID number.
   * @param int $tag_id
   *   Tag ID.
   * @param int $tag_unique_id
   *   Tag unique ID.
   */
  protected function buildRemoveTagModalForm(array &$form, $comment_id, $tag_id, $tag_unique_id) {
    $form['text'] = [
      '#markup' => $this->t('Are you sure you want to remove this tag?'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];
    $form['remove_tag'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove tag'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'use-ajax-submit',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];
    $form['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#ajax' => [
        'callback' => [$this, 'cancelModalFormAjax'],
        'event' => 'click',
      ],
    ];

    // Hidden values needed during form submission.
    $form['action'] = [
      '#type' => 'hidden',
      '#value' => 'remove',
    ];
    $form['comment_id'] = [
      '#type' => 'hidden',
      '#value' => $comment_id,
    ];
    $form['tag_id'] = [
      '#type' => 'hidden',
      '#value' => $tag_id,
    ];
    $form['tag_unique_id'] = [
      '#type' => 'hidden',
      '#value' => $tag_unique_id,
    ];
  }

  /**
   * Custom modal submit function.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response object.
   */
  public function submitModalFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $action = $form_state->getValue('action');

    if ($action == 'add') {
      // 1. Get params required to add the tag.
      $feedback_id = $form_state->getValue('comment_id');
      $tag_id = $form_state->getValue('select_tag');

      // 2. Add tag remotely via API.
      $this->contentFetcher->addTag($feedback_id, $tag_id);
      $added_tags_unique_record_id = 0;
      // NOTE: On tag addition the feedback api unfortunately does not return the added tags record it.
      // @todo Remove the zero value above after feedback API fixes this.
      // See: https://jira.mass.gov/browse/DP-12364.
      // 3. Send newly added tag markup as ajax response so it can be shown on the front end.
      // 3.1 Creates link to Remove Tag via which the newly added tag can be removed.
      $url = Url::fromRoute(
            'mass_feedback_loop.open_modal_tag_form',
            [
              'action' => 'remove',
              'comment_id' => $feedback_id,
              'tag_id' => $tag_id,
              'tag_unique_id' => $added_tags_unique_record_id,
            ],
            [
              'attributes' => [
                'class' => [
                  'link-open-modal-remove-tag',
                  'use-ajax',
                ],
                'data-dialog-type' => 'modal',
                'title' => $this->t('Remove tag'),
              ],
            ]
        );
      $link = Link::fromTextAndUrl($this->t('Remove tag'), $url)->toString();

      // 3.2 Prepare the tag makup with the remove link.
      $unique_feedback_tag_id = "feedback-$feedback_id-tag-$tag_id";
      $tag_markup_to_append = [
        '#prefix' => '<li id="' . $unique_feedback_tag_id . '"><div class="button">',
        '#markup' => $this->allTags[$tag_id] . ' ' . $link,
        '#suffix' => '</div></li>',
      ];

      // 3.3 Send newly added tag markup as ajax response so it can be shown on the front end.
      $response->addCommand(new AppendCommand('.feedback-' . $feedback_id . '-tags-list', $tag_markup_to_append));

      // 4. Hide the "Not Tagged" value if it was being displayed.
      $response->addCommand(new CssCommand('#feedback-' . $feedback_id . '-not-tagged', ['display' => 'none']));
      // 5. Closes modal dialog box.
      $response->addCommand(new CloseModalDialogCommand());

      return $response;
    }
    elseif ($action == 'remove') {
      // 1. Get params required to remove the tag.
      $feedback_id = $form_state->getValue('comment_id');
      $tag_id = $form_state->getValue('tag_id');
      $tag_to_remove_unique_record_id = $form_state->getValue('tag_unique_id');

      // 2. Remove tag if all required params are there, or stay silent
      // For now we proceed with removal of tag only when we have the requried unique record value.
      // Feedback API should return with after adding of a tag too, not just with feedback items.
      // See: https://jira.mass.gov/browse/DP-12364
      if ($tag_to_remove_unique_record_id != 0) {
        // Removes tag remotely via API.
        $this->contentFetcher->removeTag($feedback_id, $tag_id, $form_state->getValue('tag_unique_id'));

        $unique_feedback_tag_id = "feedback-$feedback_id-tag-$tag_id";
        $response->addCommand(new CssCommand('#' . $unique_feedback_tag_id, ['display' => 'none']));
      }

      // 3. Close the modal dialog box.
      $response->addCommand(new CloseModalDialogCommand());
      return $response;
    }

  }

  /**
   * Custom modal close function.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response object.
   */
  public function cancelModalFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  private function setAllTags() {
    $this->allTags = $this->contentFetcher->fetchAllTags();
    return $this;
  }

}
