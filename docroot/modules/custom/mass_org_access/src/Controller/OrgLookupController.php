<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\mass_org_access\OrgAccessChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns Permission Group terms (label + tid) for given org_page NIDs.
 *
 * Used by the JS that augments field_content_organization when authors add
 * organizations to field_organizations. The lookup itself lives in
 * OrgAccessChecker::ownerGroupTermsForOrg() — the SAME derivation the bulk
 * backfill (drush moab) uses — so the live edit form and the bulk job stay in
 * lock-step. For each org_page NID we return the user_organization terms on
 * that org_page's own field_content_organization, curated by the content team,
 * so an org_page with no Permission Groups silently yields an empty result.
 */
class OrgLookupController extends ControllerBase {

  public function __construct(
    private readonly OrgAccessChecker $orgAccessChecker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('mass_org_access.org_access_checker'));
  }

  /**
   * Endpoint access — bound to the host entity the caller is editing.
   *
   * The lookup exposes org-page → Permission Group mappings, so it must not
   * be open to any authenticated user. The JS passes the entity it is editing
   * (entity_type + entity_id, or entity_type + bundle for new content); access
   * is granted only when the caller can actually update that entity (or create
   * that bundle). Only node and media are accepted.
   */
  public function access(AccountInterface $account, Request $request): AccessResultInterface {
    $deny = AccessResult::forbidden()->addCacheContexts(['url.query_args', 'user.permissions']);

    if (!$account->isAuthenticated()) {
      return $deny;
    }
    $entity_type = (string) $request->query->get('entity_type', '');
    if (!in_array($entity_type, ['node', 'media'], TRUE)) {
      return $deny;
    }

    $entity_id = $request->query->get('entity_id');
    if ($entity_id !== NULL && $entity_id !== '') {
      $entity = $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);
      if (!$entity) {
        return $deny;
      }
      return $entity->access('update', $account, TRUE)->addCacheContexts(['url.query_args']);
    }

    // New content: require create access to the requested bundle.
    $bundle = (string) $request->query->get('bundle', '');
    if ($bundle === '') {
      return $deny;
    }
    return $this->entityTypeManager()
      ->getAccessControlHandler($entity_type)
      ->createAccess($bundle, $account, [], TRUE)
      ->addCacheContexts(['url.query_args']);
  }

  /**
   * Looks up Owner Groups terms for the requested org_page nodes.
   *
   * For each NID we load the org_page and return the user_organization
   * terms on its own field_content_organization. The set is returned per
   * NID.
   *
   * Response shape:
   * {
   *   "orgs": {
   *     "<org_page_nid>": [
   *       {"tid": 99, "label": "EOTSS"},
   *       {"tid": 88, "label": "Government"}
   *     ],
   *     ...
   *   }
   * }
   *
   * NIDs whose org_page has no Permission Groups return an empty array — the
   * JS treats those as "no terms to add".
   */
  public function lookup(Request $request): JsonResponse {
    $raw = (array) $request->query->all('org_page_nids');
    $nids = array_values(array_unique(array_filter(array_map('intval', $raw))));
    if (empty($nids)) {
      return new JsonResponse(['orgs' => []]);
    }

    $result = [];
    foreach ($nids as $nid) {
      $result[(string) $nid] = array_values(
        $this->orgAccessChecker->ownerGroupTermsForOrg($nid)
      );
    }

    return new JsonResponse(['orgs' => $result]);
  }

}
