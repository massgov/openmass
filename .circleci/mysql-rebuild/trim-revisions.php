<?php

/**
 * @file
 * Removes old revisions from the CI database image.
 *
 * Keeps, for every node and media item, the default revision and every
 * revision saved after it, plus revisions that scheduled transitions point at
 * and, for nodes, revisions entity hierarchy points at. Keeps the paragraph
 * revisions those revisions (or the current field data) reference, and the
 * moderation states of everything kept. Revision tables are rebuilt by copying
 * the kept rows into a new table, so InnoDB frees the space instead of leaving
 * empty pages in the old file. Usage rows of removed revisions go too. Fails
 * if the trim left a reference to a revision that is gone.
 *
 * Usage: drush php:script .circleci/mysql-rebuild/trim-revisions.php [dry-run]
 */

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

$dry_run = in_array('dry-run', $extra ?? [], TRUE);
$db = \Drupal::database();
$etm = \Drupal::entityTypeManager();
$efm = \Drupal::service('entity_field.manager');
$started = microtime(TRUE);

$log = function (string $message) use ($started) {
  printf("[%5ds] %s\n", microtime(TRUE) - $started, $message);
};

/**
 * Returns [table => revision column] for every revision table of a type.
 */
$revision_tables = function (string $entity_type_id) use ($etm, $efm): array {
  $entity_type = $etm->getDefinition($entity_type_id);
  $storage = $etm->getStorage($entity_type_id);
  assert($storage instanceof SqlContentEntityStorage);
  $mapping = $storage->getTableMapping();
  $key = $entity_type->getKey('revision');
  $tables = [$entity_type->getRevisionTable() => $key];
  if ($entity_type->getRevisionDataTable()) {
    $tables[$entity_type->getRevisionDataTable()] = $key;
  }
  foreach ($efm->getFieldStorageDefinitions($entity_type_id) as $definition) {
    if ($definition->isRevisionable() && $mapping->requiresDedicatedTableStorage($definition)) {
      $tables[$mapping->getDedicatedRevisionTableName($definition)] = 'revision_id';
    }
  }
  return $tables;
};

$keep_table = fn(string $entity_type_id) => "trim_keep_$entity_type_id";
$count = fn(string $table) => (int) $db->query("SELECT COUNT(*) FROM $table")->fetchField();

/**
 * Adds the revision ids a query returns to the keep list of a type.
 *
 * Not INSERT IGNORE: that would also turn bad values into warnings, and only
 * duplicates are expected here.
 */
$keep = function (string $entity_type_id, string $select, array $args = []) use ($db, $keep_table): void {
  $db->query("INSERT INTO {$keep_table($entity_type_id)} (rid) SELECT dt.id FROM ($select) dt (id) ON DUPLICATE KEY UPDATE rid = dt.id", $args);
};

foreach (['node', 'media', 'paragraph', 'content_moderation_state'] as $entity_type_id) {
  $db->query("DROP TABLE IF EXISTS {$keep_table($entity_type_id)}");
  $db->query("CREATE TABLE {$keep_table($entity_type_id)} (rid INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB");
}

// Nodes and media: the default revision and everything saved after it
// (forward drafts).
foreach (['node', 'media'] as $entity_type_id) {
  $entity_type = $etm->getDefinition($entity_type_id);
  $id = $entity_type->getKey('id');
  $rev = $entity_type->getKey('revision');
  $keep($entity_type_id, "SELECT r.$rev FROM {$entity_type->getRevisionTable()} r JOIN {$entity_type->getBaseTable()} b ON b.$id = r.$id WHERE r.$rev >= b.$rev");
  $keep($entity_type_id, "SELECT entity_revision_id FROM scheduled_transition WHERE entity__target_type = :type AND entity_revision_id IS NOT NULL", [':type' => $entity_type_id]);
  $log("$entity_type_id: keep {$count($keep_table($entity_type_id))} of {$count($entity_type->getRevisionTable())} revisions");
}
foreach ($db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'nested_set\\_%'")->fetchCol() as $table) {
  $keep('node', "SELECT revision_id FROM $table WHERE revision_id > 0");
}
$log("node: keep {$count($keep_table('node'))} with entity hierarchy");

// Paragraphs: defaults, plus whatever a kept revision or current data points
// at. Paragraphs nest, so repeat until nothing new turns up.
$keep_paragraph = $keep_table('paragraph');
$keep('paragraph', "SELECT revision_id FROM paragraphs_item");
// [host type, table, column, whether the table holds host revisions].
$references = [];
foreach ($efm->getFieldMapByFieldType('entity_reference_revisions') as $host_type => $fields) {
  $storage = $etm->getStorage($host_type);
  if (!$storage instanceof SqlContentEntityStorage) {
    continue;
  }
  $mapping = $storage->getTableMapping();
  $host_storage_definitions = $efm->getFieldStorageDefinitions($host_type);
  foreach (array_keys($fields) as $field_name) {
    $definition = $host_storage_definitions[$field_name] ?? NULL;
    if (!$definition || $definition->getSetting('target_type') !== 'paragraph') {
      continue;
    }
    if (!$mapping->requiresDedicatedTableStorage($definition)) {
      throw new \RuntimeException("$host_type.$field_name stores paragraph references in a shared table, which this script does not read.");
    }
    $column = $mapping->getFieldColumnName($definition, 'target_revision_id');
    $data_table = $mapping->getDedicatedDataTableName($definition);
    $keep('paragraph', "SELECT $column FROM $data_table WHERE $column IS NOT NULL");
    $references[] = [$host_type, $data_table, $column, FALSE];
    if ($definition->isRevisionable() && $etm->getDefinition($host_type)->isRevisionable()) {
      $references[] = [$host_type, $mapping->getDedicatedRevisionTableName($definition), $column, TRUE];
    }
  }
}
foreach ($references as [$host_type, $table, $column, $revisions]) {
  if ($revisions && $host_type !== 'paragraph') {
    $keep('paragraph', "SELECT t.$column FROM $table t JOIN {$keep_table($host_type)} k ON k.rid = t.revision_id WHERE t.$column IS NOT NULL");
  }
}
do {
  $before = $count($keep_paragraph);
  foreach ($references as [$host_type, $table, $column, $revisions]) {
    if ($revisions && $host_type === 'paragraph') {
      $keep('paragraph', "SELECT t.$column FROM $table t JOIN $keep_paragraph k ON k.rid = t.revision_id WHERE t.$column IS NOT NULL");
    }
  }
  $log("paragraph: keep {$count($keep_paragraph)} of {$count('paragraphs_item_revision')} revisions");
} while ($count($keep_paragraph) > $before);

// Moderation states of kept revisions, plus the current ones.
$keep('content_moderation_state', "SELECT revision_id FROM content_moderation_state");
foreach (['node', 'media'] as $entity_type_id) {
  $keep('content_moderation_state', "SELECT c.revision_id FROM content_moderation_state_field_revision c JOIN {$keep_table($entity_type_id)} k ON k.rid = c.content_entity_revision_id WHERE c.content_entity_type_id = :type", [':type' => $entity_type_id]);
}
$log("content_moderation_state: keep {$count($keep_table('content_moderation_state'))} of {$count('content_moderation_state_revision')} revisions");

/**
 * Counts references to revisions that do not exist.
 *
 * Covers the current revision of every entity and the paragraph references of
 * current data and of kept revisions. Run before and after the trim: the
 * production data may already have a few, but the trim must not add any.
 */
$dangling = function () use ($db, $etm, $references, $keep_table): array {
  $counts = [];
  foreach (['node', 'media', 'paragraph', 'content_moderation_state'] as $entity_type_id) {
    $entity_type = $etm->getDefinition($entity_type_id);
    $rev = $entity_type->getKey('revision');
    $counts["$entity_type_id current revision"] = (int) $db->query("SELECT COUNT(*) FROM {$entity_type->getBaseTable()} b LEFT JOIN {$entity_type->getRevisionTable()} r ON r.$rev = b.$rev WHERE r.$rev IS NULL")->fetchField();
  }
  foreach ($references as [$host_type, $table, $column, $revisions]) {
    $kept = $revisions ? "JOIN {$keep_table($host_type)} k ON k.rid = t.revision_id" : '';
    $counts["$table.$column"] = (int) $db->query("SELECT COUNT(*) FROM $table t $kept LEFT JOIN paragraphs_item_revision p ON p.revision_id = t.$column WHERE t.$column IS NOT NULL AND p.revision_id IS NULL")->fetchField();
  }
  return $counts;
};
$dangling_before = $dry_run ? [] : $dangling();

$rebuild = function (string $table, string $select) use ($db, $dry_run, $count, $log) {
  if ($dry_run) {
    $kept = (int) $db->query("SELECT COUNT(*) FROM ($select) x")->fetchField();
    $log(sprintf('%-60s %10d -> %10d', $table, $count($table), $kept));
    return;
  }
  $db->query("DROP TABLE IF EXISTS trim_new");
  $db->query("CREATE TABLE trim_new LIKE $table");
  $db->query("INSERT INTO trim_new $select");
  $db->query("RENAME TABLE $table TO trim_old, trim_new TO $table");
  $db->query("DROP TABLE trim_old");
  // A fresh table has no saved statistics until the background thread gets
  // to it, and the image is committed before that happens. Without them the
  // optimizer sees zero rows and picks bad plans for the content views.
  // ANALYZE reports a failure as a result row, not as an SQL error.
  foreach ($db->query("ANALYZE TABLE $table")->fetchAll() as $row) {
    if (strcasecmp($row->Msg_type, 'error') === 0) {
      throw new \RuntimeException("ANALYZE TABLE $table failed: $row->Msg_text");
    }
  }
  $log("$table: {$count($table)} rows");
};

foreach (['node', 'media', 'paragraph', 'content_moderation_state'] as $entity_type_id) {
  foreach ($revision_tables($entity_type_id) as $table => $column) {
    $rebuild($table, "SELECT t.* FROM $table t JOIN {$keep_table($entity_type_id)} k ON k.rid = t.$column");
  }
}

// Usage rows recorded for revisions that are gone.
$conditions = [];
foreach (['node', 'media', 'paragraph'] as $entity_type_id) {
  $conditions[] = "(e.source_type = '$entity_type_id' AND NOT EXISTS (SELECT 1 FROM {$keep_table($entity_type_id)} k WHERE k.rid = e.source_vid))";
}
$rebuild('entity_usage', 'SELECT e.* FROM entity_usage e WHERE e.source_vid = 0 OR NOT (' . implode(' OR ', $conditions) . ')');

if (!$dry_run) {
  foreach ($dangling() as $label => $after) {
    if ($after > $dangling_before[$label]) {
      throw new \RuntimeException("$label: $after references to missing revisions after the trim, {$dangling_before[$label]} before.");
    }
  }
  $log('No references to removed revisions.');
}

foreach (['node', 'media', 'paragraph', 'content_moderation_state'] as $entity_type_id) {
  $db->query("DROP TABLE {$keep_table($entity_type_id)}");
}
$log($dry_run ? 'Dry run, nothing changed.' : 'Done.');
