// Configure the AWS provider.
provider "aws" {
  region = "us-east-1"
}

// Setup remote state storage in an S3 bucket, and inform terraform about
// the bucket that is used so we can reference it from other places.

// Configure the Terraform backend to store state in S3.
terraform {
  backend "s3" {
    bucket         = "terraform.secure.digital.mass.gov"
    key            = "terraform/state/massgov.filescan.tfstate"
    region         = "us-east-1"
    dynamodb_table = "terraform"
  }
}
