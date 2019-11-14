<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains SocialLinkConstraint class.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Social Link allowed values.
 *
 * @Constraint(
 *   id = "SocialLink",
 *   label = @Translation("Social Link constraint", context = "Validation"),
 * )
 */
class SocialLinkConstraint extends Constraint {

  /**
   * Allowed values for Social Link.
   *
   * @var array
   */
  public $allowedValues;

  /**
   * Message shown when Social Link value is incorrect.
   *
   * @var string
   */
  public $message = '"%social_link_url" url with "%social_link_title" text is an invalid link value.';

  /**
   * SocialLink constructor.
   *
   * @param mixed $options
   *   Options to operate with.
   */
  public function __construct($options = NULL) {
    if (NULL !== $options && !is_array($options)) {
      $options = [
        'social_link' => $options,
      ];
    }

    parent::__construct($options);
    if (NULL === $this->allowedValues) {
      throw new MissingOptionsException(sprintf('Social Link must be given for constraint %s', __CLASS__), ['social_link']);
    }
  }

}
