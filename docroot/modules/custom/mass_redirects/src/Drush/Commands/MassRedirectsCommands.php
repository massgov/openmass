<?php

namespace Drupal\mass_redirects\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Url;
use Drupal\Driver\Exception\Exception;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\mass_redirects\Form\MoveRedirectsForm;
use Drupal\mayflower\Helper;
use Drupal\redirect\RedirectRepository;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MassRedirectsCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
    #[Autowire(service: 'redirect.repository')]
    protected RedirectRepository $redirectRepository
  ) {
    parent::__construct();
  }

  /**
   * Re-point entity usages that point via a redirect.
   *
   * Use --simulate to get a report, and skip healing.
   *
   * @command ma:heal-references-to-trash
   * @field-labels
   *   success: Success
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
   * @default-fields success,parent_id,parent_type,parent_bundle,parent_published,usage_id,usage_type,field,method,from_id,from_type,to_id,to_type
   * @aliases mah
   * @filter-default-field from_id
   */
  public function healReferences($options = ['format' => 'table']): RowsOfFields {
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
            if ($usage) {
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
            'success' => 'No',
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
          if (!Drush::simulate()) {
            // Re-point this usage.
            if ($this->heal($usage, $result)) {
              $result['success'] = 'Yes';
            }
          }
          $rows[] = $result;
        }
      }

    }
    return new RowsOfFields($rows);
  }

  public function heal(ContentEntityInterface $entity, array $data): bool {
    $changed = $saved = FALSE;
    $options = ['absolute' => TRUE];

    $field_name = $data['field'];
    $list = $entity->get($field_name);
    $uri_old = 'entity:' . $data['from_type'] . '/' . $data['from_id'];
    $uri_new = 'entity:' . $data['from_type'] . '/' . $data['to_id'];
    foreach ($list as $delta => $item) {
      switch (get_class($item)) {
        case DynamicLinkItem::class:
          $values[$delta] = $item->getValue();
          $item_uri = $item->get('uri')->getString();
          $item_uri_path = parse_url($item_uri, PHP_URL_PATH);
          if ($item_uri == $uri_old) {
            $values[$delta]['uri'] = $uri_new;
            $changed = TRUE;
          }
          elseif ($item_uri_path == Url::fromUri($uri_old)->toString()) {
            $values[$delta]['uri'] = Url::fromUri($uri_new, $options)->toString();
            $changed = TRUE;
          }
          break;

        case EntityReferenceItem::class:
          $values[$delta] = $item->getValue();
          if ($item->get('target_id')->getString() == $data['from_id']) {
            $values[$delta]['target_id'] = $data['to_id'];
            $changed = TRUE;
          }
          break;

        case TextLongItem::class:
        case TextWithSummaryItem::class:
          $values[$delta] = $item->getValue();
          $value = $item->getValue()['value'];
          // First check for the entity ID
          if (str_contains($value, $data['from_id'])) {
            $replaced = str_replace($data['from_id'], $data['to_id'], $value);
            $value = $replaced;
            $values[$delta]['value'] = $replaced;
            $changed = TRUE;
          }
          // Next check for the link. We want relative links not
          // absolute so domain mismatch isn't an issue.
          if (str_contains($value, Url::fromUri($uri_old)->toString())) {
            $replaced = str_replace(Url::fromUri($uri_old)->toString(), Url::fromUri($uri_new)->toString(), $value);
            $value = $replaced;
            $values[$delta]['value'] = $replaced;
            $changed = TRUE;
          }

          // Check for the linkit values.
          if (str_contains($value, 'data-entity-uuid')) {

            $storage_old = $this->entityTypeManager->getStorage($data['from_type']);

            if ($storage_old) {
              $entity_old = $storage_old->load($data['from_id']);
              if ($entity_old) {
                if (str_contains($value, $entity_old->uuid())) {
                  $dom = Html::load($value);
                  $xpath = new \DOMXPath($dom);
                  foreach ($xpath->query('//a[@data-entity-type and @data-entity-uuid]') as $element) {
                    if ($element->getAttribute('data-entity-uuid') == $entity_old->uuid()) {
                      // Parse link href as url,
                      // extract query and fragment from it.
                      $href_url = parse_url($element->getAttribute('href'));
                      $anchor = empty($href_url["fragment"]) ? '' : '#' . $href_url["fragment"];
                      $query = empty($href_url["query"]) ? '' : '?' . $href_url["query"];

                      $storage_new = $this->entityTypeManager->getStorage($data['to_type']);
                      if ($storage_new) {
                        $entity_new = $storage_new->load($data['to_id']);
                        if ($entity_new) {
                          $substitution = \Drupal::service('plugin.manager.linkit.substitution');
                          $url = $substitution
                            ->createInstance('canonical')
                            ->getUrl($entity_new);
                          $element->setAttribute('data-entity-uuid', $entity_new->uuid());
                          $element->setAttribute('href', $url->toString() . $query . $anchor);
                          $changed = TRUE;
                        }
                      }
                    }
                  }
                  if ($changed) {
                    $replaced = Html::serialize($dom);
                    $value = $replaced;
                    $values[$delta]['value'] = $replaced;
                  }
                }
              }
            }
          }

          // Check if there are manually entered html links.
          $old_url = str_replace('---unpublished', '', Url::fromUri($uri_old)->toString());
          if (str_contains($value, $old_url)) {
            $replaced = str_replace($old_url, Url::fromUri($uri_new)->toString(), $value);
            $values[$delta]['value'] = $replaced;
            $changed = TRUE;
          }
          break;
      }

      // Update the field values if any changes were made.
      if ($changed) {
        if (method_exists($entity, 'setRevisionLogMessage')) {
          $entity->setNewRevision();
          $entity->setRevisionLogMessage('Revision created to fix redirects.');
          $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        }
        $entity->set($field_name, $values);
        $entity->save();
        $saved = TRUE;
      }

    }
    return $saved;
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
