<?php

namespace Drupal\mass_hierarchy;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionCompletedTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MassHierarchyBatch {

  use ViewsBulkOperationsActionCompletedTrait;

  public static function finishedWrapper($success, array $results, array $operations): ?RedirectResponse {
    if ($success) {
      \Drupal::messenger()->addWarning(t('Unpublishing pages in bulk can break breadcrumbs on published children of those pages. It is important that you review the <a href=":url">Pages with no published parents report</a> to find and fix any issues.', [':url' => '/admin/ma-dash/reports/parent-missing']));
    }
    return self::finished($success, $results, $operations);
  }

}
