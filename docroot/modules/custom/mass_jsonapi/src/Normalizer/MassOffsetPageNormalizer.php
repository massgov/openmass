<?php

namespace Drupal\mass_jsonapi\Normalizer;

use Drupal\Core\Routing\RequestContext;
use Drupal\jsonapi\Normalizer\OffsetPageNormalizer;
use Drupal\jsonapi\Query\OffsetPage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Customizable max limit of items.
 *
 * @package Drupal\mass_jsonapi\Normalizer
 */
class MassOffsetPageNormalizer extends OffsetPageNormalizer {


  /**
   * An array of paths and their maximum item count.
   *
   * @var array
   */
  private $sizeMax;

  /**
   * A request for determining the current path.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  private $requestContext;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $size_max, RequestContext $requestContext) {
    $this->sizeMax = $size_max;
    $this->requestContext = $requestContext;
  }

  /**
   * {@inheritdoc}
   */
  protected function expand($data) {
    if (!is_array($data)) {
      throw new BadRequestHttpException('The page parameter needs to be an array.');
    }

    $expanded = $data + [
      OffsetPage::OFFSET_KEY => OffsetPage::DEFAULT_OFFSET,
      OffsetPage::SIZE_KEY => $this->getMax(),
    ];

    if ($expanded[OffsetPage::SIZE_KEY] > $this->getMax()) {
      $expanded[OffsetPage::SIZE_KEY] = $this->getMax();
    }

    return $expanded;
  }

  /**
   * Lookup max item count by path, and fallback to 50 if not customized.
   *
   * @return int
   *   Max number of items.
   */
  protected function getMax() {
    $path = $this->requestContext->getPathInfo();
    return isset($this->sizeMax[$path]) ? $this->sizeMax[$path] : OffsetPage::SIZE_MAX;
  }

}
