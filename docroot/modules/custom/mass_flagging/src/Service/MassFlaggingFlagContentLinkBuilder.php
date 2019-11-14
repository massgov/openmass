<?php

namespace Drupal\mass_flagging\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Class MassFlaggingFlagContentLinkBuilder.
 *
 * @package Drupal\mass_flagging\Service
 */
class MassFlaggingFlagContentLinkBuilder {

  /**
   * Default title of link to contact form for flagging content.
   */
  const LINK_TITLE = 'Flag';

  /**
   * Default contact form ID for flagging content.
   */
  const FORM_ID = 'flag_content';

  /**
   * Default reference field ID from contact form.
   */
  const FIELD_ID = 'field_content_flagged';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * Constructs a new MassFlaggingFlagContentLinkBuilder object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user account.
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  /**
   * Builds a link to the contact form for flagging content.
   *
   * Note: If providing a custom form or field, then both the $contact_form_id
   * and $reference_field_id params must be specified.
   *
   * @param int $id
   *   Entity ID.
   * @param string $link_title
   *   Title to be displayed as the link.
   * @param string $contact_form_id
   *   Contact form ID.
   * @param string $reference_field_id
   *   Reference field ID.
   *
   * @return array
   *   Render array containing link to the contact form for flagging content.
   */
  public function build($id = NULL, $link_title = NULL, $contact_form_id = NULL, $reference_field_id = NULL) {
    $link = [];

    if (!empty($id)) {
      $access = AccessResult::allowedIfHasPermission($this->currentUser, 'mass_flagging flag content');
      $link['#access'] = $access->isAllowed();
      $link['#type'] = 'link';

      $link['#title'] = $link_title ?: self::LINK_TITLE;
      $reference_field_id = $reference_field_id ?: self::FIELD_ID;
      $contact_form_id = $contact_form_id ?: self::FORM_ID;

      // Generate URL object for contact form for flagging content.
      $contact_form_url = Url::fromRoute('entity.contact_form.canonical', ['contact_form' => $contact_form_id], [
        // Set 'query' option for use by Prepopulate contrib module.
        // Will be used to pre-fill entity reference field in contact form.
        'query' => [
          'edit[' . $reference_field_id . ']' => $id,
        ],
        // Set 'attributes' option for URL.
        'attributes' => [
          'title' => t('Flag a piece of content that appears inappropriate or incorrect, and provide your reason for flagging it.'),
        ],
      ]);
      $link['#url'] = $contact_form_url;

      // Add cache metadata for the permission check.
      CacheableMetadata::createFromObject($access)
        ->applyTo($link);
    }

    return $link;
  }

}
