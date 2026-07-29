<?php

declare(strict_types=1);

namespace Drupal\mass_org_access\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\mass_org_access\Form\OrgMappingMatrixForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the CSV template, the import log, and the matrix export.
 */
class OrgMappingImportController implements ContainerInjectionInterface {

  public function __construct(
    private readonly PrivateTempStoreFactory $tempStoreFactory,
    private readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('tempstore.private'),
      $container->get('state'),
    );
  }

  /**
   * Returns a downloadable "nodeid,termid" CSV template.
   */
  public function template(): Response {
    $csv = "nodeid,termid\n1234,56\n1234,78\n";
    return new Response($csv, 200, [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="org-mapping-template.csv"',
    ]);
  }

  /**
   * Streams the current user's most recent import log.
   */
  public function log(): Response {
    $uri = $this->tempStoreFactory->get('mass_org_access')->get('import_log_uri');
    if (!is_string($uri) || $uri === '' || !file_exists($uri)) {
      throw new NotFoundHttpException();
    }
    return new Response((string) file_get_contents($uri), 200, [
      'Content-Type' => 'text/plain; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="org-mapping-import.log"',
    ]);
  }

  /**
   * Streams the saved matrix as a "nodeid,termid" CSV.
   */
  public function matrixCsv(): Response {
    $matrix = $this->state->get(OrgMappingMatrixForm::STATE_KEY, []);
    ksort($matrix);
    $lines = ['nodeid,termid'];
    foreach ($matrix as $nid => $tids) {
      foreach ((array) $tids as $tid) {
        $lines[] = (int) $nid . ',' . (int) $tid;
      }
    }
    return new Response(implode("\n", $lines) . "\n", 200, [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="org-mapping.csv"',
    ]);
  }

}
