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
   * Endpoint access — authenticated users with `access content` only.
   *
   * Mirrors the widget itself, which is visible to any role that can
   * edit a host entity. Anonymous traffic is blocked.
   *
   * @todo Should we check "update" access for the entity instead?
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated() && $account->hasPermission('access content'))
      ->cachePerPermissions();
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
