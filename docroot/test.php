<?php


use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Url;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;

$options = ['absolute' => TRUE];
$storage = \Drupal::entityTypeManager()->getStorage('node');
/** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
if (!$entity = $storage->load('704138')) {
  // Not a valid source anymore. Maybe it got deleted.
  return FALSE;
}
// Get all the Fields that we need to change in this source entity.
$query = \Drupal::database()->select('entity_usage', 'eu')
  ->fields('eu', ['method', 'field_name', 'source_type'])
  ->fields('mmsd', ['sourceid1', 'destid1'])
  ->condition('eu.target_type', 'node')
  ->condition('eu.source_id', '704137')
  ->condition('eu.source_type', 'node');
// MW: This is eliminating entities that we want to update.
// ->condition('eu.source_vid', $entity->getLoadedRevisionId());
$query->addField('eu', 'target_id', 'reference_value_old');
$query->addField('mmsd', 'destid1', 'reference_value_new');
// $query->addField('n', 'type', 'content_type');
$query->innerJoin('migrate_map_service_details', 'mmsd', "eu.target_id=mmsd.sourceid1 AND eu.target_type = 'node'");
// $query->innerJoin('node', 'n', 'mmsd.sourceid1=n.nid');
$refs = $query->execute()->fetchAll();

// Now update those fields. Different field types have
// different approach for updating.
foreach ($refs as $ref) {
  $values = [];
  $field_name = $ref->field_name;
  $list = $entity->get($field_name);
  $uri_new = 'entity:node/' . $ref->reference_value_new;
  $uri_old = 'entity:node/' . $ref->reference_value_old;
  foreach ($list as $delta => $item) {
    switch (get_class($item)) {
      case DynamicLinkItem::class:
        $values[$delta] = $item->getValue();
        // Only update the delta that was migrated
        // (when there are multiple values).
        // Each if() is a different type of DynamicLinkItem
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
        if ($item->get('target_id')->getString() == $ref['reference_value_old']) {
          $values[$delta]['target_id'] = $ref['reference_value_new'];
          $changed = TRUE;
        }
        break;
      case TextLongItem::class:
      case TextWithSummaryItem::class:
        $values[$delta] = $item->getValue();
        // First check for the entity ID
        if (str_contains($item->getString(), $ref['reference_value_old'])) {
          $replaced = str_replace($ref['reference_value_old'], $ref['reference_value_new'], $item->getString());
          $values[$delta]['value'] = $replaced;
          $changed = TRUE;
        }
        // Next check for the link. We want relative links not
        // absolute so domain mismatch isn't an issue.
        if (str_contains($item->getString(), Url::fromUri($uri_old)->toString())) {
          $replaced = str_replace(Url::fromUri($uri_old)->toString(), Url::fromUri($uri_new)->toString(), $item->getString());
          $values[$delta]['value'] = $replaced;
          $changed = TRUE;
        }
        break;
      default:
        return FALSE;
    }
  }
}
if ($changed) {
  // In case this is a service_details node,
  // we need to update the migrated version.
  $field_name = ($field_name == 'field_service_detail_links_5') ? 'field_info_details_related' : $field_name;
  dump('here');
  dump($values);
}
