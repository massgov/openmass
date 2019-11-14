provider "aws" {
  region = "us-east-1"
}

terraform {
  backend "s3" {
    bucket               = "application-configurations"
    key                  = "terraform/state/massgov.cloudflare.environment.tfstate"
    workspace_key_prefix = "terraform/state/workspaces"
    region               = "us-east-1"
    dynamodb_table       = "terraform"
  }
}

locals {
  state_bucket = "application-configurations"
}

