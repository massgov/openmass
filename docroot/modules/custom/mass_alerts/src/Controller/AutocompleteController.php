<?php

namespace Drupal\mass_alerts\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;

/**
 * Defines a route controller for entity autocomplete form elements.
 */
class AutocompleteController extends ControllerBase {

  /**
   * Handler for autocomplete request.
   */
  public function handleAutocomplete(Request $request, $field_name, $count) {
    $results = [];

    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      $typed_string = Tags::explode($input);
      $typed_string = Unicode::strtolower(array_pop($typed_string));

      // Look up users by partial email address match.
      $query = \Drupal::service('entity.query')->get('user');
      $uids = $query->condition($field_name, '%' . $typed_string . '%', 'LIKE')
        ->sort('mail', 'ASC')
        ->range(0, $count)
        ->execute();

      foreach ($uids as $uid) {
        $user = user_load($uid);
        $results[] = [
          'value' => $user->getEmail(),
          'label' => $user->getEmail(),
        ];
      }
    }

    return new JsonResponse($results);
  }

}
