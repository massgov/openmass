provider "cloudflare" {
  version = "~> 1.0"

  # email pulled from $CLOUDFLARE_EMAIL
  # token pulled from $CLOUDFLARE_TOKEN
  use_org_from_zone = var.domain
}

// Setup remote state storage in an S3 bucket, and inform terraform about
// the bucket that is used so we can reference it from other places.

// Note: Moshe had to use `aws-vault --backend file` instead of aws-vault`

// Configure the Terraform backend to store state in S3.
terraform {
  backend "s3" {
    bucket         = "application-configurations"
    key            = "terraform/state/massgov.cloudflare.tfstate"
    region         = "us-east-1"
    dynamodb_table = "terraform"
  }
}

// ====== START DNS ========

resource "cloudflare_record" "editcf" {
  domain  = var.domain
  name    = "editcf.digital"
  value   = "52.55.144.180"
  type    = "A"
  proxied = true
}

resource "cloudflare_record" "mass_gov" {
  domain  = var.domain
  name    = "mass.gov"
  value   = "170.63.206.57"
  type    = "A"
  proxied = true
}

resource "cloudflare_record" "wwwcf" {
  domain  = var.domain
  name    = "wwwcf.digital"
  value   = "52.55.144.180"
  type    = "A"
  proxied = true
}

// ====== START SETTINGS ========

resource "cloudflare_zone_settings_override" "test" {
  name = var.domain
  settings {
    always_online = "off"
    brotli        = "off"

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

// ====== START WORKER ========

resource "cloudflare_worker_script" "default_worker" {
  name    = "serviceworker"
  content = file("../dist/worker.js")
}

// The worker script for Prod
resource "cloudflare_worker_route" "default_route" {
  zone        = var.domain
  pattern     = "*.mass.gov/*"
  script_name = "serviceworker"
}

// The worker for Stage. Currently same as Prod. Change 'script_name' when testing worker on stage.
// This pattern is more specific so should be selected for edit pages over the above route per https://developers.cloudflare.com/workers/api/route-matching/.
resource "cloudflare_worker_route" "stage_route" {
  zone        = var.domain
  pattern     = "*.stage.mass.gov/*"
  script_name = "serviceworker"
}

// ====== START STAGE PAGE RULES (for testing, have higher priority) ========

resource "cloudflare_page_rule" "edit_main" {
  zone     = var.domain
  target   = "*.stage.mass.gov/*"
  priority = 101

  actions {
    bypass_cache_on_cookie = "SSESS.*|SESS.*|NO_CACHE|PERSISTENT_LOGIN_.*"
    cache_level            = "cache_everything"
    browser_cache_ttl      = 60
    edge_cache_ttl         = 1800
  }
}

resource "cloudflare_page_rule" "edit_alerts_json" {
  zone     = var.domain
  target   = "*.stage.mass.gov/alerts*"
  priority = 109

  actions {
    bypass_cache_on_cookie = "SSESS.*|SESS.*|NO_CACHE|PERSISTENT_LOGIN_.*"
    cache_level            = "cache_everything"
    browser_cache_ttl      = 60
    edge_cache_ttl         = 60
  }
}

resource "cloudflare_page_rule" "edit_alerts_jsonapi_node_alert" {
  zone     = var.domain
  target   = "*.stage.mass.gov/jsonapi/node/alert*"
  priority = 110

  actions {
    bypass_cache_on_cookie = "SSESS.*|SESS.*|NO_CACHE|PERSISTENT_LOGIN_.*"
    cache_level            = "cache_everything"
    browser_cache_ttl      = 60
    edge_cache_ttl         = 60
  }
}

// ====== START PROD PAGE RULES ========

resource "cloudflare_page_rule" "www_main" {
  zone     = var.domain
  target   = "*.mass.gov/*"
  priority = 1

  actions {
    bypass_cache_on_cookie = "SSESS.*|SESS.*|NO_CACHE|PERSISTENT_LOGIN_.*"
    cache_level            = "cache_everything"
    browser_cache_ttl      = 60
    edge_cache_ttl         = 1800
  }
}

resource "cloudflare_page_rule" "www_alerts_json" {
  zone     = var.domain
  target   = "*.mass.gov/alerts*"
  priority = 9

  actions {
    bypass_cache_on_cookie = "SSESS.*|SESS.*|NO_CACHE|PERSISTENT_LOGIN_.*"
    cache_level            = "cache_everything"
    browser_cache_ttl      = 60
    edge_cache_ttl         = 60
  }
}

resource "cloudflare_page_rule" "www_alerts_jsonapi_node_alert" {
  zone     = var.domain
  target   = "*.mass.gov/jsonapi/node/alert*"
  priority = 10

  actions {
    bypass_cache_on_cookie = "SSESS.*|SESS.*|NO_CACHE|PERSISTENT_LOGIN_.*"
    cache_level            = "cache_everything"
    browser_cache_ttl      = 60
    edge_cache_ttl         = 60
  }
}

# @todo - we don't yet automate IP whitelist. when we do, use Github Contents API to get data.
//resource "cloudflare_zone_lockdown" "endpoint_lockdown" {
//  zone_id     = "${var.zone_id}"
//  paused      = "false"
//  description = "Restrict access to these endpoints to requests from a known IP address"
//  urls = [
//    "edit.mass.gov",
//  ]
//  configurations = [
//    {
//      "target" = "ip"
//      "value" = "198.51.100.4"
//    },
//  ]
//}
