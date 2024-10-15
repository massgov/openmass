<?php

namespace MassGov\Dtt;

use PNX\DrupalTestUtils\Traits\ExpectsCacheableResponseTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

class MassExistingSiteBase extends ExistingSiteBase {
  use ExpectsCacheableResponseTrait;

  protected static array $uncacheableDynamicPagePatterns = [
    'admin/.*',
    '/*edit.*',
    'user/logout.*',
    // @todo Upgrade to drupal 10.2 and then remove the line below.
    // That gets us the fix from https://www.drupal.org/project/drupal/issues/2352175.
    '/collections.*',
  ];

  /**
   * {@inheritdoc}
   */
  protected function drupalGet($path, array $options = [], array $headers = []): string {
    $response = parent::drupalGet($path, $options, $headers);
    $this->detectUncacheableResponse($path, $options);
    return $response;
  }

  protected function setUp(): void {
    parent::setUp();
    // Cause tests to fail if an error is sent to Drupal logs.
    $this->failOnLoggedErrors();
  }


}
