provider "cloudflare" {
  # email pulled from $CLOUDFLARE_EMAIL
  # api_key pulled from $CLOUDFLARE_API_KEY
  # account_id pulled from $CLOUDFLARE_ACCOUNT_ID
  version = "2.4.0"
}

resource "cloudflare_zone_settings_override" "default" {
  zone_id = var.zone_id
  settings {
    always_online = "off"
    brotli        = "on"

    # this is called Standard in the UI.
    cache_level              = "aggressive"
    development_mode         = "off"
    security_level           = "medium"
    automatic_https_rewrites = "off"
    mirage                   = "off"
    polish                   = "off"

    # not sure about this value.
    # https://support.cloudflare.com/hc/en-us/articles/200168276
    browser_cache_ttl           = 1800
    sort_query_string_for_cache = "on"
    waf                         = "on"
    email_obfuscation           = "off"
    server_side_exclude         = "off"
    hotlink_protection          = "off"
    minify {
      css  = "off"
      js   = "off"
      html = "off"
    }
  }
}

