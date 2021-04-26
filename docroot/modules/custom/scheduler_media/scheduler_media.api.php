<?php

/**
 * @file
 * API documentation for the Scheduler module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Hook function to add media ids to the list being processed.
 *
 * This hook allows modules to add more media ids into the list being processed
 * in the current cron run. It is invoked during cron runs only. This function
 * is retained for backwards compatibility but is superceded by the more
 * flexible hook_scheduler_media_mid_list_alter().
 *
 * @param string $action
 *   The action being done to the media - 'publish' or 'unpublish'.
 *
 * @return array
 *   Array of media ids to add to the existing list of medias to be processed.
 */
function hook_scheduler_media_mid_list($action) {
  $mids = [];
  // Do some processing to add new media ids into $mids.
  return $mids;
}

/**
 * Hook function to manipulate the list of medias being processed.
 *
 * This hook allows modules to add or remove media ids from the list being
 * processed in the current cron run. It is invoked during cron runs only. It
 * can do everything that hook_scheduler_media_mid_list() does, plus more.
 *
 * @param array $mids
 *   An array of media ids being processed.
 * @param string $action
 *   The action being done to the media - 'publish' or 'unpublish'.
 *
 * @return array
 *   The full array of media ids to process, adjusted as required.
 */
function hook_scheduler_media_mid_list_alter(array &$mids, $action) {
  // Do some processing to add or remove media ids.
  return $mids;
}

/**
 * Hook function to deny or allow a media to be published.
 *
 * This hook gives modules the ability to prevent publication of a media at the
 * scheduled time. The media may be scheduled, and an attempt to publish it will
 * be made during the first cron run after the publishing time. If this hook
 * returns FALSE the media will not be published. Attempts at publishing will
 * continue on each subsequent cron run until this hook returns TRUE.
 *
 * @param \Drupal\media\MediaInterface $media
 *   The scheduled media that is about to be published.
 *
 * @return bool
 *   TRUE if the media can be published, FALSE if it should not be published.
 */
function hook_scheduler_media_allow_publishing(MediaInterface $media) {
  // If there is no 'approved' field do nothing to change the result.
  if (!isset($media->field_approved)) {
    $allowed = TRUE;
  }
  else {
    // Prevent publication of medias that do not have the 'Approved for
    // publication by the CEO' checkbox ticked.
    $allowed = !empty($media->field_approved->value);

    // If publication is denied then inform the user why. This message will be
    // displayed during media edit and save.
    if (!$allowed) {
      \Drupal::messenger()->addStatus(t('The content will only be published after approval by the CEO.'), FALSE);
    }
  }

  return $allowed;
}

/**
 * Hook function to deny or allow a media to be unpublished.
 *
 * This hook gives modules the ability to prevent unpublishing of a media at the
 * scheduled time. The media may be scheduled, and an attempt to unpublish it
 * will be made during the first cron run after the unpublishing time. If this
 * hook returns FALSE the media will not be unpublished. Attempts at unpublishing
 * will continue on each subsequent cron run until this hook returns TRUE.
 *
 * @param \Drupal\media\MediaInterface $media
 *   The scheduled media that is about to be unpublished.
 *
 * @return bool
 *   TRUE if the media can be unpublished, FALSE if it should not be unpublished.
 */
function hook_scheduler_media_allow_unpublishing(MediaInterface $media) {
  $allowed = TRUE;

  // Prevent unpublication of competition entries if not all prizes have been
  // claimed.
  if ($media->getType() == 'competition' && $items = $media->field_competition_prizes->getValue()) {
    $allowed = (bool) count($items);

    // If unpublication is denied then inform the user why. This message will be
    // displayed during media edit and save.
    if (!$allowed) {
      \Drupal::messenger()->addStatus(t('The competition will only be unpublished after all prizes have been claimed by the winners.'));
    }
  }

  return $allowed;
}

/**
 * @} End of "addtogroup hooks".
 */
