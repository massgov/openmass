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
   * Check if the form alteration is applicable for the given entity.
   *
   * @param mixed $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if applicable, FALSE otherwise.
   */
  public function isApplicable(mixed $entity): bool {
    if (!$entity instanceof NodeInterface) {
      return FALSE;
    }

    return $entity->bundle() !== 'api_service_card';
  }

  /**
   * Entrypoint called by the thin procedural hook.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if (!$this->isApplicable($entity)) {
      return;
    }

    $account = $this->currentUser;
    $is_admin = $account->hasPermission('administer redirects') || $account->hasPermission('administer site configuration');
    // Only users with this permission can create/delete. Others may still view the list.
    $can_manage = $account->hasPermission('manage friendly redirects');

    // Hide the stock Redirects component for non-admins (if present).
    if (!$is_admin && isset($form['path']['redirect'])) {
      $form['path']['redirect']['#access'] = FALSE;
    }

    $prefix_options = $this->prefixManager->getPrefixOptions();

    $wrapper_id = 'mass-friendly-redirects-wrapper';

    $form['mass_friendly_redirects'] = [
      '#type' => 'details',
      '#title' => $this->t('Friendly URLs'),
      '#group' => 'group_page_info',
      '#weight' => '99',
      '#open' => FALSE,
      '#tree' => TRUE,
      '#attributes' => ['id' => $wrapper_id],
    ];
    // Attach UI behaviors (confirm dialog for delete, etc.).
    $form['#attached']['library'][] = 'mass_friendly_redirects/ui';

    if ($can_manage) {
      $form['mass_friendly_redirects']['help'] = [
        '#markup' => '<p>' . $this->t('Create a friendly URL when you need a web address that’s easy to share and remember. See our <a href="https://www.mass.gov/kb/friendly-URLs" target="_blank">Knowledge Base for friendly URL best practices</a>.') . '</p>',
      ];

      $form['mass_friendly_redirects']['help_prefix'] = [
        '#markup' => $this->t('<p>Create one friendly URL. Pick an approved prefix (usually your org’s acronym) and a short, clear keyword(s). Avoid unfamiliar acronyms. May take up to 35 minutes to work.</p><p>URL format: mass.gov/prefix/keyword(s)</p>'),
      ];

      $form['mass_friendly_redirects']['prefix'] = [
        '#type' => 'select2',
        '#multiple' => FALSE,
        '#title' => $this->t('Prefix'),
        '#options' => $prefix_options,
        '#empty_option' => NULL,
        '#select2' => [
          'placeholder' => $this->t('Start typing to find a prefix'),
          'allowClear' => FALSE,
          'width' => '400',
        ],
        '#description' => $this->t('Choose the prefix for the URL. To request a new prefix, <a href="https://www.mass.gov/kb/servicenow" target="_blank">contact us in ServiceNow</a>.'),
      ];

      $form['mass_friendly_redirects']['suffix'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Keyword(s)'),
        '#maxlength' => 255,
        '#description' => $this->t('Lowercase only (all case variations will work after it is created). Use hyphens for separators. Do not include leading or trailing slashes.'),
      ];

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

        // Force empty defaults for this rebuild so the UI looks reset.
        $form['mass_friendly_redirects']['prefix']['#default_value'] = NULL;
        $form['mass_friendly_redirects']['suffix']['#default_value'] = '';
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
    }

    // Existing redirects table (filtered by role/prefix).
    $headers = [$this->t('Friendly URL')];
    if ($can_manage) {
      $headers[] = $this->t('Operations');
    }
    $form['mass_friendly_redirects']['existing'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No friendly redirects found for this page.'),
    ];
    $form['mass_friendly_redirects']['actions']['limit_notice'] = [
      '#type' => 'item',
      '#access' => FALSE,
      '#markup' => $this->t('This page has a prefix-scoped friendly URL. Only one is allowed. Delete the existing one to change it. If you recently changed it, it may take up to 35 minutes to work.'),
    ];

    $existing_redirects = static::loadNodeRedirects($this->etm, $this->aliasManager, $entity, $prefix_options);

    foreach ($existing_redirects as $rid => $row) {
      $form['mass_friendly_redirects']['existing'][$rid]['source'] = ['#markup' => '<code>/' . htmlspecialchars($row['source']) . '</code>'];
      if ($can_manage) {
        // Group an operations list (Edit) with a link-styled AJAX Delete.
        $form['mass_friendly_redirects']['existing'][$rid]['ops'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['mfr-ops', 'links', 'inline']],
        ];
        $form['mass_friendly_redirects']['existing'][$rid]['ops']['delete'] = [
          '#type' => 'submit',
          '#value' => t('Delete'),
          '#name' => 'mfr_delete_' . $rid,
          '#submit' => [static::class . '::deleteRedirect'],
          '#limit_validation_errors' => [],
          '#ajax' => [
            // Custom event. Do NOT use default click here.
            'event' => 'mfr-confirmed',
            'callback' => [static::class, 'ajax'],
            'wrapper'  => $wrapper_id,
            'progress' => ['type' => 'throbber'],
          ],
          '#attributes' => [
            'class' => ['mfr-delete', 'button', 'button--small'],
            'data-confirm' => t('Delete "/@src"? This may break links if people are using this URL.', ['@src' => $row['source']]),
          ],
        ];
      }
    }

    // If this page already has a friendly URL, hide the Add button and show
    // a simple note instead. We enforce a single friendly URL per page in the
    // UI to keep things predictable for editors.
    if ($can_manage && !empty($existing_redirects)) {
      if (isset($form['mass_friendly_redirects']['actions']['add'])) {
        $form['mass_friendly_redirects']['actions']['add']['#access'] = FALSE;
      }
    }

    // Hide the current-URLs heading and table when there are no friendly URLs
    // for this page, so editors don't see an empty box.
    if (empty($existing_redirects)) {
      if (isset($form['mass_friendly_redirects']['target_display'])) {
        $form['mass_friendly_redirects']['target_display']['#access'] = FALSE;
      }
      if (isset($form['mass_friendly_redirects']['existing'])) {
        $form['mass_friendly_redirects']['existing']['#access'] = FALSE;
      }
    }

    if ($can_manage && !empty($existing_redirects)) {
      if (isset($form['mass_friendly_redirects']['prefix'])) {
        $form['mass_friendly_redirects']['prefix']['#access'] = FALSE;
      }
      if (isset($form['mass_friendly_redirects']['suffix'])) {
        $form['mass_friendly_redirects']['suffix']['#access'] = FALSE;
      }
      if (isset($form['mass_friendly_redirects']['actions']['add'])) {
        $form['mass_friendly_redirects']['actions']['add']['#access'] = FALSE;
      }
      $form['mass_friendly_redirects']['actions']['limit_notice']['#access'] = TRUE;
      $form['mass_friendly_redirects']['help_prefix']['#access'] = FALSE;
    }
  }

  /**
   * FAPI validate callback.
   */
  public static function validate(array &$form, FormStateInterface $form_state): void {
    // Only users with manage permission can create/update friendly URLs.
    if (!\Drupal::currentUser()->hasPermission('manage friendly redirects')) {
      return;
    }
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\mass_friendly_redirects\Service\PrefixManager $prefixMgr */
    $prefixMgr = \Drupal::service('mass_friendly_redirects.prefix_manager');

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
    if ($suffix === '') {
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

      // Build a link to the redirects report filtered by this source path.
      $report_link = Link::fromTextAndUrl(
        t('See report showing where this URL is used.'),
        Url::fromUserInput('/admin/ma-dash/reports/redirects', [
          'query' => ['redirect_source__path' => '/' . $source],
        ])
      )->toString();
      $report_link .= ' You can remove it from that page if needed.';

      $plain = t('A friendly URL for "/@src" already exists.', ['@src' => $source]);

      // Attach the report link directly to the field error so editors see the
      // guidance right where the error appears.
      $form_state->setErrorByName(
        'mass_friendly_redirects][suffix',
        Markup::create($plain . ' ' . $report_link)
      );

      // Also show a status message with destination details (if available)
      // plus the same report link.
      if ($link_markup) {
        \Drupal::messenger()->addError(Markup::create(
          $plain . ' ' .
          t('(Currently points to @link.)', ['@link' => $link_markup]) . ' ' .
          $report_link
        ));
      }
      else {
        \Drupal::messenger()->addError(Markup::create($plain . ' ' . $report_link));
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
    if (!\Drupal::currentUser()->hasPermission('manage friendly redirects')) {
      return;
    }
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

    \Drupal::messenger()->addStatus(t('Added friendly URL "/@src" → this page.', ['@src' => $source]));
  }

  /**
   * Ajax callback to refresh the Friendly URLs section.
   */
  public static function ajax(array &$form, FormStateInterface $form_state) {
    $form['mass_friendly_redirects']['#open'] = TRUE;
    return $form['mass_friendly_redirects'];
  }

  /**
   * AJAX delete handler for a single redirect row.
   */
  public static function deleteRedirect(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal::currentUser()->hasPermission('manage friendly redirects')) {
      \Drupal::messenger()->addError(t('You do not have permission to delete friendly URLs.'));
      $form_state->setRebuild(TRUE);
      return;
    }
    // Discover redirect id from element parents; the numeric parent is the rid.
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'] ?? $trigger['#parents'] ?? [];
    $rid = NULL;
    foreach (array_reverse($parents) as $p) {
      if (is_numeric($p)) {
        $rid = (int) $p;
        break;
      }
    }
    if (!$rid) {
      $form_state->setRebuild(TRUE);
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    /** @var \Drupal\redirect\Entity\Redirect|null $redirect */
    $redirect = $storage->load($rid);
    if ($redirect) {
      $src_item = $redirect->get('redirect_source')->first();
      $src_path = $src_item ? (string) $src_item->get('path')->getString() : '';
      $redirect->delete();
      \Drupal::messenger()->addStatus(t('Deleted friendly URL "/@src".', ['@src' => $src_path ?: $rid]));
    }
    else {
      \Drupal::messenger()->addWarning(t('The selected friendly URL no longer exists.'));
    }

    // Rebuild so the table refreshes via the same AJAX callback/wrapper.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Load redirects that point to this node, filtered by allowed prefixes.
   */
  private static function loadNodeRedirects(
    EntityTypeManagerInterface $etm,
    AliasManagerInterface $aliasManager,
    NodeInterface $node,
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
    // Redirects UI to manage non-friendly redirects). To avoid huge OR
    // condition groups (and "too many tables" joins)
    $allowed = array_values($prefix_options);
    if (!$allowed) {
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

      // Filter by allowed prefixes here instead of in SQL to avoid massive
      // OR condition groups and excessive JOINs when many prefixes exist.
      // Only treat a redirect as a "friendly URL" if it has the prefix PLUS
      // at least one additional segment (e.g. "dua/claimants"), not just the
      // bare prefix like "dua" or "dua/".
      $allowed_match = FALSE;
      foreach ($allowed as $p) {
        if ($p === '') {
          continue;
        }
        $prefix_with_slash = $p . '/';
        if (str_starts_with($path, $prefix_with_slash) && strlen($path) > strlen($prefix_with_slash)) {
          $allowed_match = TRUE;
          break;
        }
      }
      if (!$allowed_match) {
        continue;
      }

      $rows[$r->id()] = ['source' => $path];
    }

    return $rows;
  }

}
