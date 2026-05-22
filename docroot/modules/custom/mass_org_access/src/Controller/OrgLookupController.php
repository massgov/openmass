<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns Owner Groups terms (label + tid) for given org_page NIDs.
 *
 * Used by the JS that augments field_content_organization when authors add
 * organizations to field_organizations. For each org_page NID we reverse-
 * look-up the user_organization taxonomy terms whose
 * field_state_organization points at that node, then walk the hierarchy
 * up via loadAllParents() to include ancestor terms. The org_page node
 * itself is not the source of truth — only the taxonomy mapping is, so
 * an out-of-sync or unmapped org_page silently yields an empty result.
 */
class OrgLookupController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
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
   * For each NID we find user_organization terms whose
   * field_state_organization references it, then collect each matching
   * term plus all of its ancestors via loadAllParents(). The merged
   * unique set is returned per NID.
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
   * NIDs with no matching taxonomy term return an empty array — the JS
   * treats those as "no terms to add".
   */
  public function lookup(Request $request): JsonResponse {
    $raw = (array) $request->query->all('org_page_nids');
    $nids = array_values(array_unique(array_filter(array_map('intval', $raw))));
    if (empty($nids)) {
      return new JsonResponse(['orgs' => []]);
    }

    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $result = [];

    foreach ($nids as $nid) {
      $tids = $term_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', 'user_organization')
        ->condition('field_state_organization.target_id', $nid)
        ->execute();

      $collected = [];
      foreach ($tids as $tid) {
        // loadAllParents() returns the term itself and every ancestor
        // up the hierarchy, keyed by tid.
        foreach ($term_storage->loadAllParents($tid) as $parent_tid => $term) {
          if (!isset($collected[$parent_tid])) {
            $collected[$parent_tid] = [
              'tid' => (int) $parent_tid,
              'label' => $term->label(),
            ];
          }
        }
      }
      $result[(string) $nid] = array_values($collected);
    }

    return new JsonResponse(['orgs' => $result]);
  }

}
