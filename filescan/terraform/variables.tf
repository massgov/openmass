variable "name_prefix" {
  type = string
  default = "massgov-filescan"
}

variable "av_definitions_bucket_name" {
  type = string
  default = "clamav-definitions.digital.mass.gov"
}

variable "chamber_namespace" {
  type = string
  default = "tf/massgov-filescan-deployment"
}

variable "tags" {
  type = map(string)
  default = {}
}
