<?php

namespace Drupal\mass_org_access;

use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Toggles mass_org_access enforcement.
 *
 * Default is OFF — the field, drush backfill, and reports ship without
 * restricting writes. Setting the switch to a truthy value flips the
 * gate on without redeploying code.
 *
 * Two layers — env var wins, State fallback:
 * - MASS_ORG_ACCESS_ENFORCE=1|true|yes|on (case-insensitive). Best for
 *   production / staging — set once per environment and forget.
 * - State key `mass_org_access.enforce` (bool). Best for ad-hoc toggles
 *   and tests, because State is shared between the PHP process and
 *   webserver (env-var changes in one process do not propagate).
 */
class OrgAccessSettings {

  private const ENV_VAR = 'MASS_ORG_ACCESS_ENFORCE';
  public const STATE_KEY = 'mass_org_access.enforce';

  /**
   * URL query parameter that reveals the Permission Groups field.
   *
   * The field shows when this parameter's value matches the secret in
   * DEBUG_SECRET_ENV, e.g. /node/123/edit?debug_show_pg=<secret>.
   */
  public const DEBUG_QUERY_PARAM = 'debug_show_pg';

  /**
   * Env var holding the secret the debug query parameter must equal.
   */
  public const DEBUG_SECRET_ENV = 'MASS_ORG_ACCESS_DEBUG_SECRET';

  public function __construct(
    private readonly StateInterface $state,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Returns TRUE when the org-based write gate should run.
   */
  public function isEnforcementEnabled(): bool {
    $env = getenv(self::ENV_VAR);
    if ($env !== FALSE && $env !== '') {
      return in_array(strtolower($env), ['1', 'true', 'yes', 'on'], TRUE);
    }
    return (bool) $this->state->get(self::STATE_KEY, FALSE);
  }

  /**
   * Returns TRUE when the Permission Groups field should be revealed.
   *
   * A troubleshooting aid for admins/devs: append
   * `?debug_show_pg=<secret>` to an edit URL, where `<secret>` matches the
   * MASS_ORG_ACCESS_DEBUG_SECRET environment variable. The secret in the URL
   * is the switch — there is no stored toggle. No secret configured (or an
   * empty/mismatched parameter) → always off. Compared in constant time.
   */
  public function isDebugModeEnabled(): bool {
    $secret = getenv(self::DEBUG_SECRET_ENV);
    if ($secret === FALSE || $secret === '') {
      return FALSE;
    }
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return FALSE;
    }
    $provided = (string) $request->query->get(self::DEBUG_QUERY_PARAM, '');
    return $provided !== '' && hash_equals($secret, $provided);
  }

}
