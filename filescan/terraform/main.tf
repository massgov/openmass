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

resource "aws_s3_bucket_public_access_block" "av_definitions_bucket" {
  bucket                  = aws_s3_bucket.av_definitions_bucket.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

data "aws_iam_policy_document" "av_definitions_bucket_update_role" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["s3.amazonaws.com"]
    }
  }
}

data "aws_iam_policy_document" "av_definitions_bucket_update_policy" {
  statement {
    effect = "Allow"

    actions = [
      "logs:CreateLogGroup",
      "logs:CreateLogStream",
      "logs:PutLogEvents",
    ]

    resources = ["*"]
  }

  statement {
    effect = "Allow"

    actions = [
      "s3:GetObject",
      "s3:GetObjectTagging",
      "s3:PutObject",
      "s3:PutObjectTagging",
      "s3:PutObjectVersionTagging"
    ]

    resources = [
      "${aws_s3_bucket.av_definitions_bucket.arn}",
    ]
  }

  statement {
    effect = "Allow"

    actions = [
      "s3:ListBucket",
    ]

    resources = [
      "${aws_s3_bucket.av_definitions_bucket.arn}/*",
      "${aws_s3_bucket.av_definitions_bucket.arn}",
    ]
  }
}

resource "aws_iam_policy" "av_definitions_bucket_update" {
  name   = "${var.name_prefix}-bucket-antivirus-update-policy"
  policy = data.aws_iam_policy_document.av_definitions_bucket_update_policy.json
}

resource "aws_iam_role" "av_definitions_bucket_update" {
  name               = "${var.name_prefix}-bucket-antivirus-update-role"
  assume_role_policy = data.aws_iam_policy_document.av_definitions_bucket_update_role.json
}

resource "aws_iam_role_policy_attachment" "av_definitions_bucket_update" {
  role       = aws_iam_role.av_definitions_bucket_update.name
  policy_arn = aws_iam_policy.av_definitions_bucket_update.arn
}

module "av_definitions_bucket_update_lambda" {
  source      = "github.com/massgov/mds-terraform-common//lambda?ref=1.0.19"
  package     = "${path.module}/../dist/lambda.zip"
  name        = "${var.name_prefix}-bucket-antivirus-update"
  human_name  = "Mass.gov file scanning ClamAV definition update"
  runtime     = "python3.7"
  memory_size = 1024
  timeout     = 300
  handler     = "update.lambda_handler"
  environment = {
    variables = {
      AV_DEFINITION_S3_BUCKET = aws_s3_bucket.av_definitions_bucket.bucket
    }
  }
  tags = var.tags
}

resource "aws_cloudwatch_event_rule" "every_three_hours" {
  name                = "every-three-hours"
  description         = "Fires every three hours"
  schedule_expression = "rate(3 hours)"
}

resource "aws_cloudwatch_event_target" "check_av_every_three_hours" {
  rule      = aws_cloudwatch_event_rule.every_three_hours.name
  target_id = "lambda"
  arn       = module.av_definitions_bucket_update_lambda.function_arn
}

resource "aws_lambda_permission" "allow_cloudwatch_to_call_check_av" {
  statement_id  = "AllowExecutionFromCloudWatch"
  action        = "lambda:InvokeFunction"
  function_name = module.av_definitions_bucket_update_lambda.function_name
  principal     = "events.amazonaws.com"
  source_arn    = aws_cloudwatch_event_rule.every_three_hours.arn
}
