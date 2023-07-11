<?php

namespace Drupal\mass_redirects\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Driver\Exception\Exception;
use Drupal\mass_redirects\Form\MoveRedirectsForm;
use Drupal\mayflower\Helper;
use Drupal\redirect\RedirectRepository;
use Drush\Commands\DrushCommands;

class MassRedirectsCommands extends DrushCommands {

  /**
   * EntityCommands constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
    protected RedirectRepository $redirectRepository
  ) {
  }

  /**
   * Re-point entity usages that point via a redirect.
   *
   * @command ma:heal
   * @field-labels
   *   parent_id: Parent id
   *   parent_type: Parent type
   *   parent_bundle: Parent Bundle
   *   parent_published: Parent Published
   *   usage_id: Usage Id
   *   usage_type: Usage type
   *   field: Field
   *   method: Method
   *   from_id: From id
   *   from_type: From type
   *   to_id: To Id
   *   to_type: To Type
   * @default-fields parent_id,parent_type,parent_bundle,parent_published,usage_id,usage_type,field,method,from_id,from_type,to_id,to_type
   * @aliases mah
   * @filter-default-field from_id
   */
  public function heal($options = ['format' => 'table']): RowsOfFields {
    $rows = [];

    // Get all usages that point to a trashed node.
    $sql = <<<EOD
SELECT * FROM entity_usage eu
INNER JOIN content_moderation_state_field_data cmsfd ON eu.`target_id` = cmsfd.content_entity_id AND eu.`target_type` = cmsfd.content_entity_type_id
WHERE cmsfd.moderation_state = 'trash' AND cmsfd.content_entity_type_id = 'node'
ORDER BY 'eu.source_id DESC'
EOD;

    // Get any redirects for the trashed node.
    $records = $this->connection->query($sql)->fetchAll();
    foreach ($records as $record) {
      $entity = $this->entityTypeManager->getStorage($record->content_entity_type_id)
        ->load($record->content_entity_id);
      $short_url = MoveRedirectsForm::shortenUrl($entity);
      if ($redirects = $this->redirectRepository->findBySourcePath($short_url)) {
        foreach ($redirects as $redirect) {
          $url = $redirect->getRedirectUrl();
          if ($url->isExternal()) {
            continue;
          }

          try {

            $usage = $this->entityTypeManager->getStorage($record->source_type)->load($record->source_id);

            if ($record->source_type == 'paragraph') {
              if (Helper::isParagraphOrphan($usage)) {
                continue;
              }
            }

            if (in_array(EntityPublishedInterface::class, class_implements($usage)) && !$usage->isPublished()) {
              // Usage is unpublished so don't bother fixing.
              continue;
            }
            if (str_starts_with($record->field_name, 'computed')) {
              // We can't edit computed fields.
              continue;
            }

            $parent = $usage;
            // Climb up to find a non-paragraph parent.
            while (method_exists($parent, 'getParentEntity')) {
              $parent = $parent->getParentEntity();
              if (!$parent->isPublished()) {
                continue 2;
              }
            }
          }
          catch (Exception) {
            continue;
          }

          $parameters = $url->getRouteParameters();
          if ($parameters['node'] == $entity->id()) {
            // This is a self-redirect.
            continue;
          }
          try {
            $to = $this->entityTypeManager->getStorage('node')->load($parameters['node']);
            if (in_array(EntityPublishedInterface::class, class_implements($to)) && !$to->isPublished()) {
              // Don't re-point to unpublished.
              continue;
            }
          }
          catch (\Exception) {
            continue;
          }

          $result = [
            'parent_id' => $parent->id(),
            'parent_type' => $parent->getEntityTypeId(),
            'parent_bundle' => $parent->bundle(),
            'parent_published' => $parent->isPublished() ? 'TRUE' : 'FALSE',
            'usage_id' => $record->source_id,
            'usage_type' => $record->source_type,
            'field' => $record->field_name,
            'method' => $record->method,
            'from_id' => $record->target_id,
            'from_type' => $record->target_type,
            'to_id' => $parameters['node'],
            'to_type' => 'node',
          ];
          // We can re-point this usage.
          $rows[] = $result;
          \Drupal::queue('mass_redirects_repoint')->createItem($result);
        }
      }

    }
    return new RowsOfFields($rows);
  }

  /**
   * An example of the table output format.
   *
   * @param array $options
   *   An associative array of options whose values
   *   come from cli, aliases, config, etc.
   *
   * @field-labels
   *   group: Group
   *   token: Token
   *   name: Name
   * @default-fields group,token,name
   *
   * @command mass_redirects:token
   * @aliases token
   *
   * @filter-default-field name
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Returns table formatted output.
   */
  public function token($options = ['format' => 'table']) {
    $all = \Drupal::token()->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }

}
