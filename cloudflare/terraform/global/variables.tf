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

variable "edit_domains" {
  type = list(string)
  default = [
    "edit.mass.gov",
    "edit.stage.mass.gov",
    "editcf.digital.mass.gov",
  ]
}

variable "www_domains" {
  type = list(string)
  default = [
    "www.mass.gov",
    "stage.mass.gov",
    "wwwcf.digital.mass.gov",
  ]
}

