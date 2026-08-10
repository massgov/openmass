<?php

/**
 * @file
 * Amazon SES credential overrides.
 *
 * When explicit SES credentials are not supplied, the AWS SDK credential
 * provider chain is used (for example, an IAM role attached to the runtime).
 */

if (!empty(getenv('AWS_SES_ACCESS_KEY_ID'))) {
  $config['aws.profile.ses']['aws_access_key_id'] = getenv('AWS_SES_ACCESS_KEY_ID');
}

if (!empty(getenv('AWS_SES_SECRET_ACCESS_KEY'))) {
  $config['aws.profile.ses']['aws_secret_access_key'] = getenv('AWS_SES_SECRET_ACCESS_KEY');
}

if (!empty(getenv('AWS_SES_ROLE_ARN'))) {
  $config['aws.profile.ses']['aws_role_arn'] = getenv('AWS_SES_ROLE_ARN');
}

if (!empty(getenv('AWS_SES_ROLE_SESSION_NAME'))) {
  $config['aws.profile.ses']['aws_role_session_name'] = getenv('AWS_SES_ROLE_SESSION_NAME');
}

if (!empty(getenv('AWS_SES_REGION'))) {
  $config['aws.profile.ses']['region'] = getenv('AWS_SES_REGION');
}
