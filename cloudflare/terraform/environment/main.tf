provider "cloudflare" {
  # email pulled from $CLOUDFLARE_EMAIL
  # api_key pulled from $CLOUDFLARE_API_KEY
  # account_id pulled from $CLOUDFLARE_ACCOUNT_ID
  version = "2.4.0"
}

locals {
  // Extract the correct domains by looking at the workspace.
  edit_domain = var.edit_domains[terraform.workspace]
  www_domain  = var.www_domains[terraform.workspace]
}

// ======== START DNS RECORDS =========
resource "cloudflare_record" "edit_dns" {
  zone_id = var.zone_id
  name    = local.edit_domain
  value   = var.BALANCER_IP
  type    = "A"
  proxied = true
}

resource "cloudflare_record" "www_dns" {
  zone_id = var.zone_id
  name    = local.www_domain
  value   = var.BALANCER_IP
  type    = "A"
  proxied = true
}

// ========== START WORKER SCRIPTS ==========
resource "cloudflare_worker_script" "worker" {
  name    = "serviceworker-${terraform.workspace}"
  content = file("../../dist/worker.js")
}

resource "cloudflare_worker_route" "edit_route" {
  zone_id = var.zone_id
  pattern     = "${local.edit_domain}/*"
  script_name = cloudflare_worker_script.worker.name
}

resource "cloudflare_worker_route" "www_route" {
  zone_id = var.zone_id
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


resource "cloudflare_firewall_rule" "edit_block_bots" {
  action = "block"
  filter_id = cloudflare_filter.edit_block_bots.id
  zone_id = var.zone_id
  description = "Block ${local.edit_domain} for bots."
}
resource "cloudflare_filter" "edit_block_bots" {
  zone_id = var.zone_id
  expression = <<EXPR
(http.host eq "${local.edit_domain}" and cf.client.bot)
EXPR
}

