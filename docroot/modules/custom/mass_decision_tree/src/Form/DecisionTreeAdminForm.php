<?php

namespace Drupal\mass_decision_tree\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mass_content_api\DescendantManagerInterface;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a decision tree admin form.
 */
class DecisionTreeAdminForm extends FormBase {
  use StringTranslationTrait;
  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Drupal\mass_content_api\DescendantManager definition.
   *
   * @var \Drupal\mass_content_api\DescendantManagerInterface
   */
  protected $descendantManager;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DecisionTreeAdminForm.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\mass_content_api\DescendantManagerInterface $descendant_manager
   *   The content api descendant manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(RendererInterface $renderer, DescendantManagerInterface $descendant_manager, EntityTypeManager $entity_type_manager) {
    $this->renderer = $renderer;
    $this->descendantManager = $descendant_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('descendant_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mass_decision_tree_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node = \Drupal::routeMatch()->getParameter('node');

    $form['#attached']['library'][] = 'mass_decision_tree/mass_decision_tree_form';

    // Branch link.
    $branch_url = Url::fromRoute('node.add', ['node_type' => 'decision_tree_branch'], [
      'query' => [
        'destination' => $node->toUrl('canonical')->toString(),
      ],
    ]);
    $branch_link = Link::fromTextAndUrl('Create new decision tree branch', $branch_url)->toRenderable();
    $form['create_branch'] = [
      '#markup' => '<div>' . \Drupal::service('renderer')->render($branch_link) . '</div>',
    ];

    // Conclusion link.
    $conclusion_url = Url::fromRoute('node.add', ['node_type' => 'decision_tree_conclusion'], [
      'query' => [
        'destination' => $node->toUrl('canonical')->toString(),
      ],
    ]);
    $conclusion_link = Link::fromTextAndUrl('Create new decision tree conclusion', $conclusion_url)->toRenderable();
    $form['create_conclusion'] = [
      '#markup' => '<div>' . \Drupal::service('renderer')->render($conclusion_link) . '</div>',
    ];

    $form['table-row'] = [
      '#type' => 'table',
      '#theme' => 'table__system',
      '#attributes' => [
        'id' => 'decisionTree',
      ],
      '#header' => [
        $this->t('Title'),
        $this->t('Type'),
        $this->t('Edit'),
        $this->t('Weight'),
        $this->t('Parent'),
      ],
      '#empty' => $this->t('Sorry, there are no children!'),
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'row-pid',
          'source' => 'row-id',
          'hidden' => TRUE,
          'limit' => FALSE,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ],
      ],
    ];

    $children = $this->descendantManager->getChildrenTree($node->id());
    foreach ($children as $child) {
      $info = [
        'child' => $child['id'],
        'depth' => 0,
        'index' => 0,
      ];
      $this->buildRow($form, $info);
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save All Changes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(&$form, &$info) {
    $nid = $info['child'];
    $child_node = $this->entityTypeManager->getStorage('node')->load($nid);

    if (!empty($child_node)) {
      $info['index']++;
      $types = [
        'decision_tree_branch' => $this->t('Branch'),
        'decision_tree_conclusion' => $this->t('Conclusion'),
      ];

      // TableDrag: Mark the table row as draggable.
      $form['table-row'][$info['index']]['#attributes']['class'][] = 'draggable';

      $form['table-row'][$info['index']]['#attributes']['data-type'] = strtolower($types[$child_node->bundle()]);

      // Add the parent as a data attribute if there is one.
      if (isset($info['parent']) && !empty($info['parent'])) {
        $form['table-row'][$info['index']]['#attributes']['data-parent'] = $info['parent'];
      }

      // Never let conclusions be parents.
      if ($child_node->bundle() === 'decision_tree_conclusion') {
        $form['table-row'][$info['index']]['#attributes']['class'][] = 'tabledrag-leaf';
      }

      // Indent item on load.
      if (isset($info['depth']) && $info['depth'] > 0) {
        $indentation = [
          '#theme' => 'indentation',
          '#size' => $info['depth'],
        ];
      }
      $text = '';
      if (isset($info['answer_text'])) {
        $text = '[' . $info['answer_text'] . ']';
      }

      // Title, type, and edit column data.
      $form['table-row'][$info['index']]['title'] = [
        '#markup' => '<a href="' . $child_node->toUrl()->toString() . '" class="decision-tree-form-title" id="' . $nid . '">' . $text . ' ' . $child_node->label() . '</a>',
        '#prefix' => !empty($indentation) ? \Drupal::service('renderer')->render($indentation) : '',
      ];

      $form['table-row'][$info['index']]['type'] = [
        '#markup' => $types[$child_node->bundle()],
      ];

      $form['table-row'][$info['index']]['edit'] = [
        '#markup' => '<a href="' . $child_node->toUrl('edit-form')->toString() . '">edit</a>',
      ];

      // This is hidden from #tabledrag array (above).
      // TableDrag: Weight column element.
      $form['table-row'][$info['index']]['weight'] = [
        '#parents' => ['table-row', $nid, 'weight'],
        '#type' => 'weight',
        '#title' => $this->t('Weight for ID @id', ['@id' => $nid]),
        '#title_display' => 'invisible',
        '#default_value' => -10,
        // Classify the weight element for #tabledrag.
        '#attributes' => [
          'class' => ['row-weight'],
        ],
      ];
      $form['table-row'][$info['index']]['parent']['id'] = [
        '#parents' => ['table-row', $nid, 'id'],
        '#type' => 'hidden',
        '#value' => $nid,
        '#attributes' => [
          'class' => ['row-id'],
        ],
      ];
      $form['table-row'][$info['index']]['parent']['pid'] = [
        '#parents' => ['table-row', $nid, 'pid'],
        '#type' => 'number',
        '#size' => 6,
        '#min' => 0,
        '#title' => $this->t('Parent ID'),
        '#default_value' => !empty($info['parent']) ? $info['parent'] : '',
        '#attributes' => [
          'class' => ['row-pid'],
        ],
      ];

      // Check for children and pass them in recursively.
      if ($child_node->bundle() === 'decision_tree_branch') {
        foreach ($child_node->field_multiple_answers as $answers) {
          $answer = $answers->entity;

          // Use now the entity to get the values you need.
          $answer_path = $answer->field_answer_path->target_id;

          $answer_text = $answer->field_answer_text->value;

          if ($answer_path) {
            $info['depth']++;
            $info['child'] = $answer_path;
            $info['answer_text'] = $answer_text ?? '';
            $info['parent'] = $nid;
            $this->buildRow($form, $info);
            $info['depth']--;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rows = $form_state->getValue('table-row');
    $kids = [];
    // Build out all our kids before we loop through them.
    foreach ($rows as $row) {
      if (!$row['pid']) {
        $kids[0][] = $row['id'];
      }
      else {
        $kids[$row['pid']][] = $row['id'];
      }
    }

    foreach ($kids as $parent_nid => $kid) {
      if ($parent_nid) {
        $node = Node::load($parent_nid);
        $order = [];
        $paragraphs = Helper::getReferencedEntitiesFromField($node, 'field_multiple_answers');
        foreach ($paragraphs as $p) {
          $p_node = Helper::getReferencedEntitiesFromField($p, 'field_answer_path');
          if (!empty($p_node)) {
            $p_nid = $p_node[0]->id();
          }
          $order[$p_nid] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
        }

        $node->field_multiple_answers = NULL;
        foreach ($kid as $nid) {
          if (isset($order[$nid])) {
            $node->field_multiple_answers[] = $order[$nid];
          }
          else {
            $paragraph = \Drupal::entityTypeManager()
              ->getStorage('paragraph')
              ->loadByProperties(['field_answer_path' => $nid]);
            $p_tmp = reset($paragraph);
            $p = $p_tmp->createDuplicate();
            $p->save();
            $node->field_multiple_answers[] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
          }
        }
        $node->setNewRevision();
        $node->setRevisionUserId(\Drupal::currentUser()->id());
        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $node->save();
      }
    }
    \Drupal::messenger()->addMessage($this->t('Your changes have been saved successfully!'), 'status');
  }

}
