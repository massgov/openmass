variable "zone_id" {
  default     = "0ed5703243ed917ae6df0f7ddc638d7d"
  description = "Mass.gov Zone ID at Cloudflare."
  type        = string
}

variable "domain" {
  default     = "mass.gov"
  description = "The root domain."
  type        = string
}

variable "balancer_ip" {
  default     = "52.55.144.180"
  description = "Load balancer IP."
  type        = string
}

variable "www_domains" {
  type = map(string)
  default = {
    cf    = "wwwcf.digital.mass.gov"
    stage = "stage.mass.gov"
    prod  = "www.mass.gov"
  }
}

variable "edit_domains" {
  type = map(string)
  default = {
    cf    = "editcf.digital.mass.gov"
    stage = "edit.stage.mass.gov"
    prod  = "edit.mass.gov"
  }
}

