resource "aws_s3_bucket" "av_definitions_bucket" {
  bucket = var.av_definitions_bucket_name
  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm = "AES256"
      }
    }
  }
  tags = merge(var.tags, {
    Name               = var.av_definitions_bucket_name
    dataclassification = "na"
  })
  versioning {
    enabled = true
  }
}

resource "aws_s3_bucket_public_access_block" "data" {
  bucket                  = aws_s3_bucket.av_definitions_bucket.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}
