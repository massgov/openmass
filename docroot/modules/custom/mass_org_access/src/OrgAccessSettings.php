<?php

namespace Drupal\mass_org_access;

use Drupal\Core\State\StateInterface;

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
   * State key for the Permission Groups debug switch.
   */
  public const DEBUG_STATE_KEY = 'mass_org_access.debug_mode';

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

  /**
   * Returns TRUE when the Permission Groups field is shown to all editors.
   *
   * A troubleshooting aid: dropping the author-hiding wrapper lets editors
   * see which organizations are attached to a page. State-only (no env
   * layer) and OFF by default; toggled from the settings form.
   */
  public function isDebugModeEnabled(): bool {
    return (bool) $this->state->get(self::DEBUG_STATE_KEY, FALSE);
  }

}
