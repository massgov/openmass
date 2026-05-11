<?php

namespace Drupal\mass_org_access;

use Drupal\Core\State\StateInterface;

/**
 * Toggles mass_org_access enforcement.
 *
 * Default is OFF (Release 1: ship the field, drush backfill, and reports
 * without restricting writes). Setting the switch to a truthy value
 * flips the gate on for Release 2 without redeploying code.
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

  public function __construct(
    private readonly StateInterface $state,
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

}
