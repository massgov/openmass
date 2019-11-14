<?php

/**
 * @file
 * Functions to be shared for mass_flagging module.
 */

/**
 * String output of module's help text.
 *
 * @return string
 *   String to be used in a render array.
 */
function get_mass_flagging_help_text() {
  $output = '';
  $output .= '<h2>' . t('About') . '</h2>';
  $output .= '<p>' . t('Handles multiple flagging scenarios like watching and flagging.') . '</p>';
  $output .= '<h3>' . t('Watching') . '</h3>';
  $output .= '<h4>' . t('What is watching?') . '</h4>';
  $output .= '<p>' . t('Watching content allows you to get email notifications whenever changes occur to that page. For example, if someone else creates a new draft or publishes a revision, you will get an email notification if you are a watcher on that page.') . '</p>';
  $output .= '<h4>' . t('How do I watch a page?') . '</h4>';
  $output .= '<p>' . t('There are currently two ways to become a watcher:') . '</p>';
  $output .= '<dl>';
  $output .= '<dt>' . t('Automatically - ') . '</dt>';
  $output .= '<dd>' . t('If you have taken an action on a page (i.e. creating a draft or publishing a revision) you are automatically added as a watcher.') . '</dd>';
  $output .= '<dt>' . t('Manually - ') . '</dt>';
  $output .= '<dd>' . t('On each page, below the Edit menu on the top right there is a link that says “Watch”. If you click this link you will start receiving notifications moving forward.') . '</dd>';
  $output .= '</dl>';
  $output .= '<h4>' . t('Who gets notifications?') . '</h4>';
  $output .= '<p>' . t('Only watchers for a particular page will get notifications on changes once a page is published. If you are the user who creates the draft or publishes a revision, you will not receive a notification, even if you are a watcher.') . '</p>';
  $output .= '<h4>' . t('How do I stop getting email notifications?') . '</h4>';
  $output .= '<p>' . t('If you no longer want to receive notifications when changes occur, please go to the page in question and below the Edit menu on the top right you will see an  “Unwatch” link. Click it to no longer get emails. You will also see these unwatch links in our automated notification emails.') . '</p>';
  $output .= '<p>' . t('Currently, you cannot permanently remove yourself as a watcher for a piece of content you edit. We are however, looking into possible ways to incorporate this into a future revisions.') . '</p>';
  return $output;
  return $output;
}
