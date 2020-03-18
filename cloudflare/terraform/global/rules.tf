locals {
  all_domains = concat(var.www_domains, var.edit_domains)
}

/**
 * Note: The priority of these rules is chosen intentionally.
 *
 * Cloudflare's API respects setting page rule priority only if the priority is.
 */
resource "cloudflare_page_rule" "default_www" {
  zone_id = var.zone_id
  count    = length(var.www_domains)
  target   = "${element(var.www_domains, count.index)}/*"
  priority = count.index
  actions {
    // This setting is only here because we need some action for the page rule.
    // It won't actually be respected, because the Browser TTL action can only
    // INCREASE the TTL seen by the browser, never decrease it.
    // See our worker for where browser TTL is actually set.
    browser_cache_ttl = 60
  }
}

resource "cloudflare_page_rule" "default_edit" {
  zone_id = var.zone_id
  count    = length(var.edit_domains)
  target   = "${element(var.edit_domains, count.index)}/*"
  priority = length(var.www_domains) + count.index
  actions {
    cache_level    = "aggressive"
  }
}

