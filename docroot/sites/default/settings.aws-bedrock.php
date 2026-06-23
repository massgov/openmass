<?php


// Check in env var is defined. If not - we can define it with Drupal admin UI.
if (!empty(getenv('AWS_BEDROCK_ACCESS_KEY_ID'))) {
  $config['aws.profile.bedrock']['aws_access_key_id'] = getenv('AWS_BEDROCK_ACCESS_KEY_ID');
}

if (!empty(getenv('AWS_BEDROCK_SECRET_ACCESS_KEY'))) {
  $config['aws.profile.bedrock']['aws_secret_access_key'] = getenv('AWS_BEDROCK_SECRET_ACCESS_KEY');
}

if (!empty(getenv('AWS_BEDROCK_ROLE_ARN'))) {
  $config['aws.profile.bedrock']['aws_role_arn'] = getenv('AWS_BEDROCK_ROLE_ARN');
}

if (!empty(getenv('AWS_BEDROCK_ROLE_SESSION_NAME'))) {
  $config['aws.profile.bedrock']['aws_role_session_name'] = getenv('AWS_BEDROCK_ROLE_SESSION_NAME');
}

if (!empty(getenv('AWS_BEDROCK_REGION'))) {
  $config['aws.profile.bedrock']['region'] = getenv('AWS_BEDROCK_REGION');
}

