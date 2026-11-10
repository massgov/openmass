<?php

declare(strict_types=1);

namespace Drupal\trashbin;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
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
 * it can be republished (DP-49254). Only permanent deletion transfers them to
 * the replacement selected for the content's own public URL.
 */
final class TrashbinPurgeRedirects {

  /**
   * Redirect dependencies read while planning, for the concurrency check.
   *
   * @var array<int|string, array>
   */
  private array $dependencies = [];

  public function __construct(
    private Connection $database,
    private EntityTypeManagerInterface $entityTypeManager,
    private AliasManagerInterface $aliasManager,
    private PathValidatorInterface $pathValidator,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Builds a read-only plan; unsafe redirects prevent the whole entity purge.
   *
   * @return array{updates: array, originals: array, create: array}
   *   Field updates, originals for concurrency checks, and new URL bridges.
   */
  public function prepare(EntityInterface $entity): array {
    $this->dependencies = [];
    $plan = [
      'updates' => [],
      'originals' => [],
      'create' => [],
    ];
    if (!in_array($entity->getEntityTypeId(), ['node', 'media'], TRUE) || !$this->entityTypeManager->hasDefinition('redirect')) {
      return $plan;
    }

    $base = '/' . $entity->getEntityTypeId() . '/' . $entity->id();
    $paths = [$base => ''];
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

    foreach ($redirects as $redirect) {
      $original = $redirect->getRedirect();
      $language = $redirect->language()->getId();
      $source = '/' . $redirect->getSource()['path'];
      $incoming = $this->ownedSuffix($this->localPath($original, $language), $paths);
      if ($incoming === NULL && !isset($paths[$source])) {
        continue;
      }

      $resolved = $this->resolve($original, $language, $paths, [(string) $redirect->id() => TRUE]);
      $plan['originals'][$redirect->id()] = $this->snapshot($redirect);
      if ($resolved !== $original) {
        $plan['updates'][$redirect->id()] = $resolved;
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
          if (isset($plan['create'][$key]) && $plan['create'][$key]['redirect_redirect'] !== $resolved) {
            throw new UnsafePurgeRedirectException('Conflicting replacements for ' . $path . '.');
          }
          $plan['create'][$key] = $values;
        }
      }
    }

    foreach ($plan['create'] as $key => $values) {
      $existing = $storage->loadByProperties(['redirect_source__path' => $values['redirect_source']['path']]);
      // An existing source may have language/query-specific semantics. Do not
      // add a new fallback underneath it. Its own record is handled above.
      if ($existing) {
        unset($plan['create'][$key]);
      }
    }
    $plan['originals'] += $this->dependencies;
    return $plan;
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
      }
      $entity->delete();
      // Entity deletion also removes aliases. Create bridges after that
      // cleanup so an alias-related hook cannot remove them.
      foreach ($plan['create'] as $values) {
        $storage->create($values)->save();
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
   * Resolves a destination without relying on an alias about to be deleted.
   */
  private function resolve(array $destination, string $language, array $paths, array $visited): array {
    if (count($visited) > 20) {
      throw new UnsafePurgeRedirectException('Redirect chain exceeds 20 hops.');
    }
    $uri = $destination['uri'];
    if (preg_match('@^https?://@i', $uri)) {
      return $destination;
    }
    $path = $this->localPath($destination, $language);
    if ($path === NULL) {
      throw new UnsafePurgeRedirectException('Unsupported redirect destination ' . $uri . '.');
    }
    $parts = UrlHelper::parse($uri);
    $options = $destination['options'] ?? [];
    $query = ($options['query'] ?? []) + $parts['query'];
    $fragment = $options['fragment'] ?? $parts['fragment'];
    $candidates = [$path];
    $suffix = $this->ownedSuffix($path, $paths);
    if ($suffix !== NULL) {
      foreach ($paths as $candidate => $candidate_suffix) {
        if ($candidate_suffix === $suffix) {
          $candidates[] = $candidate;
        }
      }
    }
    else {
      $candidates[] = $this->aliasManager->getAliasByPath($path, $language);
      if (preg_match('@^/node/(\d+)$@', $path, $matches)) {
        $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
        if ($node instanceof EntityPublishedInterface && !$node->isPublished()) {
          // Follow DP-49254's public-alias replacement for other unpublished
          // nodes in the chain as well as the node being permanently deleted.
          $public = str_replace('---unpublished', '', $this->aliasManager->getAliasByPath($path, $language));
          $owner = $this->aliasManager->getPathByAlias($public, $language);
          if ($owner === $public || $owner === $path) {
            $candidates[] = $public;
          }
        }
      }
    }

    $redirects = $this->entityTypeManager->getStorage('redirect')->loadByProperties([
      'redirect_source__path' => array_map(fn($candidate) => ltrim($candidate, '/'), array_unique($candidates)),
    ]);
    $by_source = [];
    foreach ($redirects as $redirect) {
      $this->dependencies[$redirect->id()] = $this->snapshot($redirect);
      $redirect_language = $redirect->language()->getId();
      if ($language === LanguageInterface::LANGCODE_NOT_SPECIFIED && $redirect_language !== $language) {
        throw new UnsafePurgeRedirectException('Language-dependent replacement for ' . $path . '.');
      }
      if (!in_array($redirect_language, [$language, LanguageInterface::LANGCODE_NOT_SPECIFIED], TRUE)) {
        continue;
      }
      if ($redirect->getSource()['query']) {
        throw new UnsafePurgeRedirectException('Query-dependent replacement for ' . $path . '.');
      }
      // Automatic old-alias -> original-entity records are incoming links,
      // not evidence of an onward replacement for that same entity route.
      if ($suffix !== NULL && $this->ownedSuffix($this->localPath($redirect->getRedirect(), $language), $paths) === $suffix) {
        continue;
      }
      $by_source[$redirect->getSource()['path']][$redirect_language] = $redirect;
    }
    $results = [];
    foreach ($by_source as $languages) {
      $redirect = $languages[$language] ?? $languages[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      if (isset($visited[$redirect->id()])) {
        throw new UnsafePurgeRedirectException('Redirect loop at ' . $redirect->getSource()['path'] . '.');
      }
      $next = $this->resolve($redirect->getRedirect(), $language, $paths, $visited + [$redirect->id() => TRUE]);
      $next_parts = UrlHelper::parse($next['uri']);
      $next_options = $next['options'] ?? [];
      if ($this->configFactory->get('redirect.settings')->get('passthrough_querystring')) {
        $next_options['query'] = ($next_options['query'] ?? []) + $next_parts['query'] + $query;
      }
      else {
        unset($options['query']);
      }
      if (!isset($next_options['fragment']) && $next_parts['fragment'] === '' && $fragment !== '') {
        $next_options['fragment'] = $fragment;
      }
      $next['options'] = $next_options + $options;
      if (empty($next['options']['query'])) {
        unset($next['options']['query']);
      }
      $results[serialize($next)] = $next;
    }
    if (count($results) > 1) {
      throw new UnsafePurgeRedirectException('Conflicting replacements for ' . $path . '.');
    }
    if ($results) {
      return reset($results);
    }
    if ($suffix !== NULL) {
      throw new UnsafePurgeRedirectException('No onward replacement for ' . $path . '.');
    }
    $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($path);
    if (!$url || !$url->isRouted()) {
      throw new UnsafePurgeRedirectException('Missing local replacement ' . $path . '.');
    }
    foreach (['node', 'media'] as $type) {
      $id = $url->getRouteParameters()[$type] ?? NULL;
      if ($id !== NULL) {
        $target = $this->entityTypeManager->getStorage($type)->load($id);
        if (!$target || ($target instanceof EntityPublishedInterface && !$target->isPublished())) {
          throw new UnsafePurgeRedirectException('Missing or unpublished replacement ' . $path . '.');
        }
      }
    }
    // Store the system path, so purging an alias elsewhere cannot strand this
    // redirect. Url validation includes route parameter conversion (existence).
    $destination['uri'] = 'internal:/' . $url->getInternalPath();
    if ($query) {
      $options['query'] = $query;
    }
    if ($fragment !== '') {
      $options['fragment'] = $fragment;
    }
    $destination['options'] = $options;
    return $destination;
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
