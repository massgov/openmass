<?php

declare(strict_types=1);

namespace Drupal\trashbin;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;

/**
 * Preserves redirects only when content is permanently purged.
 *
 * Incoming redirects must stay attached to unpublished/trashed content while
 * it can be republished (DP-49254). Permanent deletion transfers working
 * redirects to surviving destinations and removes broken or ambiguous links.
 */
final class TrashbinPurgeRedirects {

  public function __construct(
    private Connection $database,
    private EntityTypeManagerInterface $entityTypeManager,
    private AliasManagerInterface $aliasManager,
    private PathValidatorInterface $pathValidator,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Plans redirect transfer and cleanup without changing content or redirects.
   *
   * @return array{updates: array, originals: array, create: array, preserve: array, remove: array, warnings: array}
   *   Updates, checks, bridges, retained records, removals, and warnings.
   */
  public function prepare(EntityInterface $entity): array {
    $plan = [
      'updates' => [],
      'originals' => [],
      'create' => [],
      'preserve' => [],
      'remove' => [],
      'warnings' => [],
    ];
    if (!in_array($entity->getEntityTypeId(), ['node', 'media'], TRUE) || !$this->entityTypeManager->hasDefinition('redirect')) {
      return $plan;
    }

    $base = '/' . $entity->getEntityTypeId() . '/' . $entity->id();
    $paths = [$base => ''];
    $public_aliases = [];
    $aliases = $this->entityTypeManager->getStorage('path_alias')->loadByProperties(['path' => $base]);
    foreach ($aliases as $alias) {
      $paths[$alias->getAlias()] = '';
      // Match MoveRedirectsForm::shortenUrl(): DP-49254 stores the replacement
      // on the public alias, without the unpublished suffix. Never claim an
      // alias which has since been assigned to different content.
      if ($entity->getEntityTypeId() === 'node') {
        $public = str_replace('---unpublished', '', $alias->getAlias());
        $owner = $this->aliasManager->getPathByAlias($public, $alias->language()->getId());
        if ($owner === $public || $owner === $base) {
          $paths[$public] = '';
          if ($public !== $alias->getAlias()) {
            $public_aliases[$public] = TRUE;
          }
        }
      }
    }
    if ($entity->getEntityTypeId() === 'media') {
      foreach (array_keys($paths) as $path) {
        $paths[$path . '/download'] = '/download';
      }
    }

    $storage = $this->entityTypeManager->getStorage('redirect');
    $query = $storage->getQuery()->accessCheck(FALSE);
    $sources = array_map(fn($path) => ltrim($path, '/'), array_keys($paths));
    $matches = $query->orConditionGroup()
      ->condition('redirect_source__path', $sources, 'IN');
    // Include every subpath and query/fragment form that Redirect's deletion
    // hook can remove, as well as aliases which disappear with the entity.
    foreach (array_keys($paths) as $path) {
      foreach (['', '/', '?', '#'] as $suffix) {
        $matches->condition('redirect_redirect__uri', 'internal:' . $path . $suffix, $suffix === '' ? '=' : 'STARTS_WITH');
      }
    }
    foreach (['', '/', '?', '#'] as $suffix) {
      $matches->condition('redirect_redirect__uri', 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id() . $suffix, $suffix === '' ? '=' : 'STARTS_WITH');
    }
    $redirects = $storage->loadMultiple($query->condition($matches)->execute());
    $automatic = [];
    foreach ($redirects as $redirect) {
      if (isset($public_aliases['/' . $redirect->getSource()['path']])
        && !$redirect->getSource()['query']
        && $redirect->getRedirect()['uri'] === 'internal:' . $base
        && empty($redirect->getRedirect()['options'])) {
        $automatic[$redirect->id()] = TRUE;
      }
    }

    // All traversal state belongs to this plan, never to the shared service.
    $context = [
      'paths' => $paths,
      'automatic' => $automatic,
      'read' => $redirects,
      'failed' => [],
      'removed' => [],
    ];
    $processed = [];
    $bridges = [];
    while ($redirects) {
      $redirect = reset($redirects);
      $id = $redirect->id();
      unset($redirects[$id]);
      $processed[$id] = TRUE;
      $original = $redirect->getRedirect();
      $language = $redirect->language()->getId();
      $source = '/' . $redirect->getSource()['path'];
      $incoming = $this->ownedSuffix($this->localPath($original, $language), $paths);
      $affected = $incoming !== NULL || isset($paths[$source]);
      if (!$affected && !isset($context['failed'][$id])) {
        continue;
      }
      $allow_self = isset($automatic[$id]);
      $result = $this->resolve($original, $language, $context, [(string) $id => TRUE], $allow_self);
      // Only failed branches need independent cleanup checks. Healthy chains
      // have already been validated and need no second traversal here.
      $redirects += array_diff_key($context['failed'], $processed);
      if (isset($result['error'])) {
        $plan['remove'][$id] = $result['error'];
        continue;
      }
      $resolved = $result['destination'];
      if (!$affected) {
        continue;
      }
      if ($allow_self && $resolved === $original) {
        $plan['preserve'][$id] = $redirect->toArray();
        // This record does not authorize new bridges back to the deleted node.
        continue;
      }
      if ($resolved !== $original) {
        $plan['updates'][$id] = $resolved;
      }

      // Preserve system URLs and aliases after their path_alias entities are
      // removed. Only a source with an explicit replacement authorizes these
      // bridges; unrelated subpaths are never mapped to a guessed target.
      if (isset($paths[$source]) && !$redirect->getSource()['query']) {
        foreach ($paths as $path => $suffix) {
          if ($suffix !== $paths[$source]) {
            continue;
          }
          $values = [
            'redirect_source' => ['path' => ltrim($path, '/'), 'query' => []],
            'redirect_redirect' => $resolved,
            'language' => $language,
            'status_code' => $redirect->getStatusCode(),
          ];
          $key = Redirect::generateHash(ltrim($path, '/'), [], $language);
          $bridges[$key][serialize($result['target'])] = $values;
        }
      }
    }

    foreach ($bridges as $key => $candidates) {
      $values = reset($candidates);
      $existing = $storage->loadByProperties(['redirect_source__path' => $values['redirect_source']['path']]);
      // An existing source may have language/query-specific semantics. Do not
      // add a new fallback underneath it. Its own record is handled above.
      if ($existing) {
        continue;
      }
      if (count($candidates) > 1) {
        $plan['warnings'][] = 'Conflicting replacements for /' . $values['redirect_source']['path'] . '; no URL bridge created.';
        continue;
      }
      $plan['create'][$key] = $values;
    }
    $this->removeStrandedIncomingRedirects($plan, $context);
    $plan['originals'] = array_map(fn($redirect) => $this->snapshot($redirect), $context['read']);
    return $plan;
  }

  /**
   * Removes upstream links only if their destination stops working as well.
   */
  private function removeStrandedIncomingRedirects(array &$plan, array &$context): void {
    $storage = $this->entityTypeManager->getStorage('redirect');
    $checked = [];
    while ($remaining = array_diff_key($plan['remove'], $checked)) {
      $context['removed'] = $plan['remove'];
      foreach (array_keys($remaining) as $id) {
        $checked[$id] = TRUE;
        $source = '/' . $context['read'][$id]->getSource()['path'];
        $query = $storage->getQuery()->accessCheck(FALSE);
        $matches = $query->orConditionGroup();
        foreach (['internal:' . $source, 'entity:' . ltrim($source, '/')] as $uri) {
          foreach (['', '/', '?', '#'] as $suffix) {
            $matches->condition('redirect_redirect__uri', $uri . $suffix, $suffix === '' ? '=' : 'STARTS_WITH');
          }
        }
        foreach ($storage->loadMultiple($query->condition($matches)->execute()) as $incoming) {
          $incoming_id = $incoming->id();
          if (isset($plan['remove'][$incoming_id]) || isset($plan['preserve'][$incoming_id]) || isset($plan['updates'][$incoming_id])) {
            continue;
          }
          $context['read'][$incoming_id] = $incoming;
          $resolved = $this->resolve($incoming->getRedirect(), $incoming->language()->getId(), $context, [(string) $incoming_id => TRUE]);
          if (isset($resolved['error'])) {
            $plan['remove'][$incoming_id] = $resolved['error'];
          }
        }
      }
    }
  }

  /**
   * Applies redirect changes and entity deletion in one database transaction.
   */
  public function delete(EntityInterface $entity, array $plan): void {
    $transaction = $this->database->startTransaction();
    $storage = $this->entityTypeManager->hasDefinition('redirect') ? $this->entityTypeManager->getStorage('redirect') : NULL;
    try {
      if ($storage) {
        if ($plan['originals']) {
          $this->database->select('redirect', 'r')
            ->fields('r', ['rid'])
            ->condition('rid', array_keys($plan['originals']), 'IN')
            ->forUpdate()
            ->execute()
            ->fetchCol();
        }
        $storage->resetCache(array_keys($plan['originals']));
        foreach ($plan['originals'] as $id => $original) {
          $redirect = $storage->load($id);
          if (!$redirect || $this->snapshot($redirect) !== $original) {
            throw new UnsafePurgeRedirectException('Redirect ' . $id . ' changed during purge; retry this entity.');
          }
        }
        foreach ($plan['updates'] as $id => $destination) {
          $redirect = $storage->load($id);
          $redirect->set('redirect_redirect', $destination);
          $redirect->save();
        }
        $storage->delete($storage->loadMultiple(array_keys($plan['remove'])));
      }
      $entity->delete();
      // Entity deletion also removes aliases. Create bridges after that
      // cleanup so an alias-related hook cannot remove them.
      foreach ($plan['create'] as $values) {
        $storage->create($values)->save();
      }
      // Redirect's entity deletion hook removes records targeting this node.
      // Restore the allowed public-alias records, including their IDs/UUIDs,
      // after all entity and alias cleanup, in the same transaction.
      foreach ($plan['preserve'] as $id => $values) {
        $storage->resetCache([$id]);
        if (!$storage->load($id)) {
          $storage->create($values)->save();
        }
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      $storage?->resetCache();
      $this->entityTypeManager->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);
      $this->aliasManager->cacheClear();
      throw $e;
    }
  }

  /**
   * Checks chains, rewriting only references to paths being deleted.
   *
   * @return array
   *   A destination and its validated target, or an error/ambiguity result.
   */
  private function resolve(array $destination, string $language, array &$context, array $visited, bool $allow_self = FALSE): array {
    $uri = $destination['uri'];
    if (preg_match('@^https?://@i', $uri)) {
      return ['destination' => $destination, 'target' => $destination];
    }
    $path = $this->localPath($destination, $language);
    if ($path === NULL) {
      return ['error' => 'Unsupported redirect destination ' . $uri . '.'];
    }
    $requested_path = str_starts_with($uri, 'internal:')
      ? UrlHelper::parse(substr($uri, strlen('internal:')))['path']
      : '/' . Url::fromUri($uri)->getInternalPath();
    $candidates = [$requested_path, $path];
    $suffix = $this->ownedSuffix($path, $context['paths']);
    if ($suffix !== NULL) {
      foreach ($context['paths'] as $candidate => $candidate_suffix) {
        if ($candidate_suffix === $suffix) {
          $candidates[] = $candidate;
        }
      }
    }

    $redirects = $this->entityTypeManager->getStorage('redirect')->loadByProperties([
      'redirect_source__path' => array_map(fn($candidate) => ltrim($candidate, '/'), array_unique($candidates)),
    ]);
    $context['read'] += $redirects;
    $by_source = [];
    foreach ($redirects as $redirect) {
      if (isset($context['removed'][$redirect->id()])) {
        continue;
      }
      $redirect_language = $redirect->language()->getId();
      if ($language !== LanguageInterface::LANGCODE_NOT_SPECIFIED && !in_array($redirect_language, [$language, LanguageInterface::LANGCODE_NOT_SPECIFIED], TRUE)) {
        continue;
      }
      // Automatic old-alias -> original-entity records are incoming links,
      // not evidence of an onward replacement for that same entity route.
      if ($suffix === '' && isset($context['automatic'][$redirect->id()])) {
        continue;
      }
      $by_source[$redirect->getSource()['path']][$redirect_language][] = $redirect;
    }
    // Different source paths can intentionally have different replacements.
    // Prefer the exact destination path, then its unaliased system path. Only
    // fall back to the other addresses when neither has an explicit redirect.
    foreach (array_unique([$requested_path, $path]) as $preferred) {
      $source = ltrim($preferred, '/');
      if (isset($by_source[$source])) {
        $by_source = [$source => $by_source[$source]];
        break;
      }
    }
    $results = [];
    $failures = [];
    foreach ($by_source as $languages) {
      if ($language === LanguageInterface::LANGCODE_NOT_SPECIFIED && array_diff(array_keys($languages), [$language])) {
        if ($suffix === NULL) {
          return ['destination' => $destination, 'target' => $destination];
        }
        return ['error' => 'Language-dependent replacement for ' . $path . '.', 'ambiguous' => TRUE];
      }
      $choices = $languages[$language] ?? $languages[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      foreach ($choices as $choice) {
        if ($choice->getSource()['query']) {
          if ($suffix === NULL) {
            return ['destination' => $destination, 'target' => $destination];
          }
          return ['error' => 'Query-dependent replacement for ' . $path . '.', 'ambiguous' => TRUE];
        }
      }
      $redirect = reset($choices);
      if (isset($visited[$redirect->id()])) {
        $context['failed'][$redirect->id()] = $redirect;
        $failures[] = ['error' => 'Redirect loop at ' . $redirect->getSource()['path'] . '.'];
        continue;
      }
      $next = $this->resolve($redirect->getRedirect(), $language, $context, $visited + [$redirect->id() => TRUE]);
      if (isset($next['error'])) {
        $context['failed'][$redirect->id()] = $redirect;
        if (!empty($next['ambiguous'])) {
          return $next;
        }
        $failures[] = $next;
        continue;
      }
      // Compare final targets without flattening the destination we save.
      // Different healthy intermediate paths can reach the same final page.
      $target = $this->transferOptions($destination, $next['target']);
      $resolved = $suffix === NULL ? $destination : $this->transferOptions($destination, $next['destination']);
      $results[serialize($target)] = ['destination' => $resolved, 'target' => $target];
    }
    if (count($results) > 1) {
      return ['error' => 'Conflicting replacements for ' . $path . '.', 'ambiguous' => TRUE];
    }
    if ($results) {
      return reset($results);
    }
    if ($failures) {
      return reset($failures);
    }
    if ($suffix !== NULL) {
      // This exception is allowed only for the initial automatic public-alias
      // record. Recursive calls still reject missing onward replacements.
      if ($allow_self) {
        return ['destination' => $destination, 'target' => $destination];
      }
      return ['error' => 'No onward replacement for ' . $path . '.'];
    }
    $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($path);
    if (!$url || !$url->isRouted()) {
      return ['error' => 'Missing local replacement ' . $path . '.'];
    }
    foreach (['node', 'media'] as $type) {
      $id = $url->getRouteParameters()[$type] ?? NULL;
      if ($id !== NULL) {
        $target = $this->entityTypeManager->getStorage($type)->load($id);
        if (!$target) {
          return ['error' => 'Missing replacement ' . $path . '.'];
        }
      }
    }
    // Normalize only the comparison target. The saved destination keeps its
    // surviving alias, query string, fragment, and options exactly as stored.
    $parts = UrlHelper::parse($uri);
    $options = $destination['options'] ?? [];
    $query = ($options['query'] ?? []) + $parts['query'];
    $fragment = $options['fragment'] ?? $parts['fragment'];
    if ($query) {
      $options['query'] = $query;
    }
    if ($fragment !== '') {
      $options['fragment'] = $fragment;
    }
    $target = $destination;
    $target['uri'] = 'internal:/' . $url->getInternalPath();
    $target['options'] = $options;
    return ['destination' => $destination, 'target' => $target];
  }

  /**
   * Carries request options across a path removed by this purge.
   */
  private function transferOptions(array $original, array $replacement): array {
    $parts = UrlHelper::parse($original['uri']);
    $options = $original['options'] ?? [];
    $query = ($options['query'] ?? []) + $parts['query'];
    $fragment = $options['fragment'] ?? $parts['fragment'];
    $next_parts = UrlHelper::parse($replacement['uri']);
    $next_options = $replacement['options'] ?? [];
    if ($this->configFactory->get('redirect.settings')->get('passthrough_querystring')) {
      $next_options['query'] = ($next_options['query'] ?? []) + $next_parts['query'] + $query;
    }
    else {
      unset($options['query']);
    }
    if (!isset($next_options['fragment']) && $next_parts['fragment'] === '' && $fragment !== '') {
      $next_options['fragment'] = $fragment;
    }
    $replacement['options'] = $next_options + $options;
    if (empty($replacement['options']['query'])) {
      unset($replacement['options']['query']);
    }
    return $replacement;
  }

  /**
   * Returns an unaliased local path, including media's download subpath.
   */
  private function localPath(array $destination, string $language): ?string {
    $uri = $destination['uri'];
    if (str_starts_with($uri, 'entity:')) {
      return '/' . Url::fromUri($uri)->getInternalPath();
    }
    if (!str_starts_with($uri, 'internal:/') || str_starts_with($uri, 'internal://')) {
      return NULL;
    }
    $path = UrlHelper::parse(substr($uri, strlen('internal:')))['path'];
    $resolved = $this->aliasManager->getPathByAlias($path, $language);
    if ($resolved === $path && str_ends_with($path, '/download')) {
      $resolved = $this->aliasManager->getPathByAlias(substr($path, 0, -9), $language) . '/download';
    }
    return $resolved;
  }

  /**
   * Identifies routes belonging to the purged entity, with a path boundary.
   */
  private function ownedSuffix(?string $path, array $paths): ?string {
    if ($path === NULL) {
      return NULL;
    }
    foreach ($paths as $owned => $suffix) {
      if ($path === $owned) {
        return $suffix;
      }
      if ($suffix === '' && str_starts_with($path, $owned . '/')) {
        return substr($path, strlen($owned));
      }
    }
    return NULL;
  }

  /**
   * Compares persisted redirect semantics without SQL scalar type differences.
   */
  private function snapshot(Redirect $redirect): array {
    return [
      'source' => [
        'path' => $redirect->getSource()['path'],
        'query' => (array) $redirect->getSource()['query'],
      ],
      'destination' => $redirect->getRedirect() + ['title' => NULL, 'options' => []],
      'language' => $redirect->language()->getId(),
      'status' => (int) $redirect->getStatusCode(),
      'uuid' => $redirect->uuid(),
    ];
  }

}
