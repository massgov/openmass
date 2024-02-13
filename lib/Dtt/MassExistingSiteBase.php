<?php

namespace MassGov\Dtt;

use PNX\DrupalTestUtils\Traits\ExpectsCacheableResponseTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

class MassExistingSiteBase extends ExistingSiteBase {
  use ExpectsCacheableResponseTrait;

  protected static array $uncacheableDynamicPagePatterns = [
    '/admin/.*',
    '/*edit.*',
  ];

  /**
   * {@inheritdoc}
   */
  protected function drupalGet($path, array $options = [], array $headers = []): string {
    $response = parent::drupalGet($path, $options, $headers);
    $this->detectUncacheableResponse($path, $options);
    return $response;
  }
}
