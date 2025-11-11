<?php

declare(strict_types=1);

namespace Drupal\mass_friendly_redirects\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\mass_friendly_redirects\Service\PrefixManager;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;
use Drupal\Core\Url;

final class NodeFriendlyRedirectsAlterer {
  use StringTranslationTrait;

  public function __construct(
    private AccountProxyInterface $currentUser,
    private EntityTypeManagerInterface $etm,
    private AliasManagerInterface $aliasManager,
    private LanguageManagerInterface $languageManager,
    private MessengerInterface $messenger,
    private PrefixManager $prefixManager,
  ) {}

  /**
   * Entrypoint called by the thin procedural hook.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if (!$entity instanceof NodeInterface) {
      return;
    }

    $account = $this->currentUser;
    $is_admin = $account->hasPermission('administer redirects') || $account->hasPermission('administer site configuration');
    $can_manage = $account->hasPermission('manage friendly redirects') || $is_admin;
    if (!$can_manage) {
      return;
    }

    // Hide the stock Redirects component for non-admins (if present).
    if (!$is_admin && isset($form['path']['redirect'])) {
      $form['path']['redirect']['#access'] = FALSE;
    }

    $prefix_options = $this->prefixManager->getPrefixOptions();

    $wrapper_id = 'mass-friendly-redirects-wrapper';

    $form['mass_friendly_redirects'] = [
      '#type' => 'details',
      '#title' => $this->t('Friendly URLs'),
      '#group' => 'advanced',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $form['mass_friendly_redirects']['help'] = [
      '#markup' => '<p>' . $this->t('Create friendly URLs scoped to approved prefixes. Use lowercase only when creating - friendly URLs will work in any case. Changes may take up to 35 minutes to appear due to caching.') . '</p><p>URL format: https://www.mass.gov/prefix/path</p>',
    ];

    $form['mass_friendly_redirects']['prefix'] = [
      '#type' => 'select',
      '#title' => $this->t('Prefix'),
      '#options' => $prefix_options,
      '#empty_option' => NULL,
      '#description' => $this->t('Choose the prefix for the URL. To request a new prefix, contact us in ServiceNow.'),
    ];

    $form['mass_friendly_redirects']['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path after prefix'),
      '#maxlength' => 255,
      '#placeholder' => $this->t('e.g. flu or vaccine-locations'),
      '#description' => $this->t('Lowercase only (all case variations will work after it is created). Use hyphens for separators. Do not include leading or trailing slashes.'),
    ];

    $alias = $this->aliasManager->getAliasByPath('/node/' . $entity->id());
    $form['mass_friendly_redirects']['target_display'] = [
      '#type' => 'item',
      '#title' => $this->t('Current friendly URLs for this page'),
    ];

    // If the previous request computed and submitted a redirect, clear inputs so
    // they don't re-trigger validation on subsequent node Save. Also override
    // any persisted user input on rebuild to visually reset the fields.
    $mfv = (array) $form_state->getValue('mass_friendly_redirects');
    if (!empty($mfv['_computed_source']) && !empty($mfv['_target_nid'])) {
      // Clear values in form state to avoid duplicate processing on Save.
      $form_state->unsetValue(['mass_friendly_redirects', '_computed_source']);
      $form_state->unsetValue(['mass_friendly_redirects', '_target_nid']);
      $form_state->setValue(['mass_friendly_redirects', 'prefix'], NULL);
      $form_state->setValue(['mass_friendly_redirects', 'suffix'], '');

      // Also clear the raw user input so FAPI doesn't repopulate from POST.
      $input = $form_state->getUserInput();
      if (isset($input['mass_friendly_redirects'])) {
        unset($input['mass_friendly_redirects']['prefix'], $input['mass_friendly_redirects']['suffix']);
        $form_state->setUserInput($input);
      }

      // Force empty defaults/values for this rebuild so the UI looks reset.
      $form['mass_friendly_redirects']['prefix']['#default_value'] = NULL;
      $form['mass_friendly_redirects']['prefix']['#value'] = NULL;
      $form['mass_friendly_redirects']['suffix']['#default_value'] = '';
      $form['mass_friendly_redirects']['suffix']['#value'] = '';
    }

    $form['mass_friendly_redirects']['actions'] = ['#type' => 'actions'];
    $form['mass_friendly_redirects']['actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Friendly URL'),
      '#submit' => [static::class . '::submit'],
      '#validate' => [static::class . '::validate'],
      '#limit_validation_errors' => [['mass_friendly_redirects']],
      '#ajax' => [
        'callback' => [static::class, 'ajax'],
        'wrapper' => $wrapper_id,
        'progress' => ['type' => 'throbber'],
      ],
    ];

    // Ensure our validation also runs on full form save (but it will no-op if empty).
    $form['#validate'][] = [static::class, 'validate'];
    // Ensure saving the node will also create/update the redirect when fields are provided.
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#submit'][] = [static::class, 'submit'];
    }

    // Existing redirects table (filtered by role/prefix).
    $headers = [$this->t('Friendly URL')];
    if ($is_admin) {
      $headers[] = $this->t('Operations');
    }
    $form['mass_friendly_redirects']['existing'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No friendly redirects found for this page.'),
    ];

    foreach (static::loadNodeRedirects($this->etm, $this->aliasManager, $entity, $is_admin, $prefix_options) as $rid => $row) {
      $form['mass_friendly_redirects']['existing'][$rid]['source'] = ['#markup' => '<code>/' . htmlspecialchars($row['source']) . '</code>'];
      if ($is_admin) {
        $form['mass_friendly_redirects']['existing'][$rid]['ops'] = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => t('Edit'),
              'url' => \Drupal\Core\Url::fromRoute('entity.redirect.edit_form', ['redirect' => $rid]),
            ],
            'delete' => [
              'title' => t('Delete'),
              'url' => \Drupal\Core\Url::fromRoute('entity.redirect.delete_form', ['redirect' => $rid]),
            ],
          ],
        ];
      }
    }
  }

  /**
   * FAPI validate callback.
   */
  public static function validate(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\mass_friendly_redirects\Service\PrefixManager $prefixMgr */
    $prefixMgr = \Drupal::service('mass_friendly_redirects.prefix_manager');
    $account = \Drupal::currentUser();
    $is_admin = $account->hasPermission('administer redirects') || $account->hasPermission('administer site configuration');

    $values = (array) $form_state->getValue('mass_friendly_redirects');
    $prefix_tid = $values['prefix'] ?? '';
    $suffix = (string) ($values['suffix'] ?? '');

    // If user didn't enter anything in our subform, skip validation.
    if (($prefix_tid === '' || $prefix_tid === NULL) && trim((string) $suffix) === '') {
      return;
    }

    $prefix_options = $prefixMgr->getPrefixOptions();

    // Resolve chosen prefix (required for everyone in this UI).
    if (!$prefix_tid || !isset($prefix_options[$prefix_tid])) {
      $form_state->setErrorByName('mass_friendly_redirects][prefix', t('Please pick a prefix.'));
      return;
    }
    $prefix = $prefix_options[$prefix_tid];

    // Normalize and validate suffix.
    $suffix = trim($suffix);
    $suffix = trim($suffix, "/ \t\n\r\0\x0B");
    if ($suffix === '' && !$is_admin) {
      $form_state->setErrorByName('mass_friendly_redirects][suffix', t('Please provide a path after the prefix.'));
      return;
    }

    // Lowercase enforcement.
    if ($suffix !== mb_strtolower($suffix)) {
      $form_state->setErrorByName('mass_friendly_redirects][suffix', t('Friendly URLs must be entered in lowercase. All case variations will work after it is created.'));
    }

    // Allowed chars: a-z, 0-9, hyphen and slashes; must not start or end with slash.
    if ($suffix !== '' && !preg_match('@^[a-z0-9][a-z0-9\-/]*$@', $suffix)) {
      $form_state->setErrorByName('mass_friendly_redirects][suffix', t('Only lowercase letters, numbers, slashes, and hyphens are allowed. Do not start or end with a slash.'));
    }

    // Prefix is required; build source with selected prefix.
    $source = ($suffix === '') ? $prefix : ($prefix . '/' . $suffix);
    $source = preg_replace('@/+@', '/', (string) $source);
    $source = trim($source, '/');

    if ($source === '') {
      $form_state->setErrorByName('mass_friendly_redirects][suffix', t('Source path cannot be empty.'));
      return;
    }
    if ($source !== mb_strtolower($source)) {
      $form_state->setErrorByName('mass_friendly_redirects][suffix', t('Friendly URLs must be lowercase.'));
    }

    // Warn if duplicate exists (compound sub-property).
    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source__path', $source)
      ->execute();

    if (!empty($ids)) {
      /** @var \Drupal\redirect\Entity\Redirect $existing */
      $existing = $storage->load(reset($ids));
      $dest_item = $existing->get('redirect_redirect')->first();
      $dest_uri = $dest_item ? (string) $dest_item->get('uri')->getString() : '';
      $alias_manager = \Drupal::service('path_alias.manager');
      $nid_in_use = NULL;
      $dest_path_display = '';
      if (str_starts_with($dest_uri, 'internal:/')) {
        $dest_path_display = substr($dest_uri, strlen('internal:'));
        $internal_path = $alias_manager->getPathByAlias($dest_path_display);
        if (preg_match('@^/node/(\\d+)$@', $internal_path, $m)) {
          $nid_in_use = (int) $m[1];
        }
      }
      elseif (str_starts_with($dest_uri, 'entity:node/')) {
        $nid_in_use = (int) substr($dest_uri, strlen('entity:node/'));
        $dest_path_display = $alias_manager->getAliasByPath('/node/' . $nid_in_use) ?: '/node/' . $nid_in_use;
      }
      elseif (str_starts_with($dest_uri, 'node/')) {
        $nid_in_use = (int) substr($dest_uri, strlen('node/'));
        $dest_path_display = $alias_manager->getAliasByPath('/node/' . $nid_in_use) ?: '/node/' . $nid_in_use;
      }
      else {
        // Fallback.
        $dest_path_display = $dest_uri;
      }
      // Build a safe link for the messenger (form errors escape HTML).
      $link_markup = '';
      if ($nid_in_use) {
        $href = $alias_manager->getAliasByPath('/node/' . $nid_in_use) ?: '/node/' . $nid_in_use;
        $link_markup = Link::fromTextAndUrl(t('this page'), Url::fromUserInput($href))->toString();
      }
      elseif ($dest_path_display) {
        $link_markup = '<code>' . htmlspecialchars($dest_path_display, ENT_QUOTES) . '</code>';
      }

      // Form errors escape HTML, so keep the field error plain-text for focus/accessibility…
      $plain = t('A redirect for "/@src" already exists.', ['@src' => $source]);
      $form_state->setErrorByName('mass_friendly_redirects][suffix', $plain);

      // …and add a separate messenger error with a clickable link.
      if ($link_markup) {
        \Drupal::messenger()->addError(Markup::create($plain . ' ' . t('(Currently points to @link.)', ['@link' => $link_markup])));
      }
      else {
        \Drupal::messenger()->addError($plain);
      }
      return;
    }

    // Stash computed values for submit.
    $form_state->setValue(['mass_friendly_redirects', '_computed_source'], $source);
    $form_state->setValue(['mass_friendly_redirects', '_target_nid'], $node->id());
  }

  /**
   * FAPI submit callback.
   */
  public static function submit(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('mass_friendly_redirects');
    $source = (string) ($values['_computed_source'] ?? '');
    $nid = (int) ($values['_target_nid'] ?? 0);
    if (!$source || !$nid) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source__path', $source)
      ->execute();

    $targetUri = 'node/' . $nid;

    if (!empty($ids)) {
      /** @var \Drupal\redirect\Entity\Redirect $r */
      $r = $storage->load(reset($ids));
      $changed = FALSE;

      // Compare using the stored URI string (compound field).
      $item = $r->get('redirect_redirect')->first();
      $currentUri = $item ? $item->get('uri')->getString() : '';

      if ($currentUri !== $targetUri) {
        $r->setRedirect($targetUri);
        $changed = TRUE;
      }
      if ((int) $r->getStatusCode() !== 301) {
        $r->setStatusCode(301);
        $changed = TRUE;
      }

      if ($changed) {
        $r->save();
        \Drupal::messenger()->addStatus(t('Updated redirect "/@src" to point here.', ['@src' => $source]));
      }
      else {
        \Drupal::messenger()->addStatus(t('Redirect "/@src" already points here.', ['@src' => $source]));
      }
      // Stay on the same form and rebuild the section.
      $form_state->setRebuild(TRUE);
      return;
    }

    // Create new redirect using Redirect 1.x API.
    $redirect = Redirect::create();
    // No leading slash
    $redirect->setSource($source, []);
    $redirect->setRedirect($targetUri);
    $redirect->setStatusCode(301);
    $redirect->set('language', \Drupal::languageManager()->getDefaultLanguage()->getId());
    $redirect->save();

    // Stay on the same form and rebuild the section.
    $form_state->setRebuild(TRUE);

    \Drupal::messenger()->addStatus(t('Added redirect "/@src" → this page.', ['@src' => $source]));
  }

  /**
   * Ajax callback to refresh the Friendly URLs section.
   */
  public static function ajax(array &$form, FormStateInterface $form_state) {
    return $form['mass_friendly_redirects'];
  }

  /**
   * Load redirects that point to this node, filtered for admin/editor views.
   */
  private static function loadNodeRedirects(
    EntityTypeManagerInterface $etm,
    AliasManagerInterface $aliasManager,
    NodeInterface $node,
    bool $is_admin,
    array $prefix_options,
  ): array {
    $storage = $etm->getStorage('redirect');
    $query = $storage->getQuery()->accessCheck(FALSE);

    // Always 301 for our UI.
    $query->condition('status_code', 301);

    // Destination: support multiple URI schemes that may exist in data.
    $nid = $node->id();
    $destGroup = $query->orConditionGroup()
      ->condition('redirect_redirect__uri', 'node/' . $nid)
      ->condition('redirect_redirect__uri', 'internal:/node/' . $nid)
      ->condition('redirect_redirect__uri', 'entity:node/' . $nid);

    // Include alias form if present (legacy data).
    $aliasPath = '/node/' . $nid;
    $alias = $aliasManager->getAliasByPath($aliasPath);
    if ($alias && $alias !== $aliasPath) {
      $destGroup->condition('redirect_redirect__uri', 'internal:' . $alias);
    }
    $query->condition($destGroup);

    // Restrict to allowed prefixes for everyone (admins can use the full
    // Redirects UI to manage non-friendly redirects). Keep Friendly URLs area
    // consistent between admins and editors.
    $allowed = array_values($prefix_options);
    if ($allowed) {
      $prefixGroup = $query->orConditionGroup();
      foreach ($allowed as $p) {
        if ($p === '') {
          continue;
        }
        // Match exact prefix or prefix/*.
        $prefixGroup->condition(
          $query->andConditionGroup()->condition('redirect_source__path', $p)
        );
        $prefixGroup->condition(
          $query->andConditionGroup()->condition('redirect_source__path', $p . '/%', 'LIKE')
        );
      }
      $query->condition($prefixGroup);
    }
    else {
      // No allowed prefixes configured => nothing to show in Friendly URLs.
      return [];
    }

    // Order by path so we don't sort in PHP.
    $query->sort('redirect_source__path', 'ASC');

    $ids = $query->execute();
    if (!$ids) {
      return [];
    }

    /** @var \Drupal\redirect\Entity\Redirect[] $redirects */
    $redirects = $storage->loadMultiple($ids);

    $rows = [];
    foreach ($redirects as $r) {
      // Get raw source path without triggering extra URL building.
      // Field is compound; path is stored in the 'path' property.
      $item = $r->get('redirect_source')->first();
      $path = $item ? (string) $item->get('path')->getString() : '';
      if ($path === '') {
        continue;
      }
      $rows[$r->id()] = ['source' => $path];
    }

    return $rows;
  }

}
