provider "cloudflare" {
  # email pulled from $CLOUDFLARE_EMAIL
  # token pulled from $CLOUDFLARE_TOKEN
  use_org_from_zone = var.domain

  // This is locked to a specific version to avoid an issue where page rules
  // ended up missing TTL settings in 1.14.0.
  version = "1.16.0"
}

locals {
  // Extract the correct domains by looking at the workspace.
  edit_domain = var.edit_domains[terraform.workspace]
  www_domain  = var.www_domains[terraform.workspace]
}

// ======== START DNS RECORDS =========
resource "cloudflare_record" "edit_dns" {
  domain  = var.domain
  name    = local.edit_domain
  value   = var.balancer_ip
  type    = "A"
  proxied = true
}

resource "cloudflare_record" "www_dns" {
  domain  = var.domain
  name    = local.www_domain
  value   = var.balancer_ip
  type    = "A"
  proxied = true
}

// ========== START WORKER SCRIPTS ==========
resource "cloudflare_worker_script" "worker" {
  name    = "serviceworker-${terraform.workspace}"
  content = file("../../dist/worker.js")
}

resource "cloudflare_worker_route" "edit_route" {
  zone        = var.domain
  pattern     = "${local.edit_domain}/*"
  script_name = cloudflare_worker_script.worker.name
}

resource "cloudflare_worker_route" "www_route" {
  zone        = var.domain
  pattern     = "${local.www_domain}/*"
  script_name = cloudflare_worker_script.worker.name
}

// ========== START FIREWALL RULES =========
resource "cloudflare_firewall_rule" "www_block" {
  zone_id     = var.zone_id
  action      = "block"
  description = "Block suspicious requests for ${local.www_domain}"
  filter_id   = cloudflare_filter.www_block.id
}

resource "cloudflare_filter" "www_block" {
  // This expression blocks requests to /user, /user/*, /admin, /admin/*, and everything that's not
  // a GET, OPTIONS, or HEAD request.
  zone_id    = var.zone_id
  expression = <<EXPR
    (
      http.host eq "${local.www_domain}" and (
        http.request.uri.path matches "^/user($|/)" or
        http.request.uri.path matches "^/admin($|/)" or
        not http.request.method in {"GET" "OPTIONS" "HEAD"}
      )
    )
EXPR

}

