/*
PROBLEM: Sets of records exist in the file_managed table where all records in the set
refer to the same underlying file.
RESULT 1: If any one record in a set of duplicates has status 0, Drupal's normal
file delete routine will delete the file, even though it may be used.
RESULT 2: If more than one record in a set has status 0, the file delete routine
will choke on the second record because the file does not exist.

Confirmation query:
SELECT DISTINCT fm1.* from file_managed fm1
JOIN file_managed fm2 on fm2.fid != fm1.fid AND fm2.uri = fm1.uri
WHERE fm1.status = 0;

This set of SQL statements removes any duplicate records with status 0.
It does NOT remove duplicate records with status 1, which is much harder, as it
requires references to the duplicate records to be updated and consolidated.

For safety, records are not deleted if they are referenced by any of
- the file_usage table
- the media__field_upload_file table
- the paragraph__field_downloads table

*/

-- This statement deletes duplicate, bad records:
--   record points to same file as other records in the table
--   record has status 0 (temporary)
--   record has no file_usage entry, and no file field references.
DELETE fm1 from file_managed fm1
JOIN file_managed fm2 on fm2.fid > fm1.fid AND fm2.uri = fm1.uri
LEFT JOIN file_usage fu ON fu.fid = fm1.fid
LEFT JOIN media__field_upload_file m ON m.field_upload_file_target_id = fm1.fid
LEFT JOIN paragraph__field_downloads pd ON pd.field_downloads_target_id = fm1.fid
WHERE fm1.status = 0 AND fu.count IS NULL AND m.entity_id IS NULL AND pd.entity_id IS NULL;

-- Run a second time in case the "good" record has a lower fid.
-- We don't run with a <> operator, as that would delete all members of set if all have status 0.
DELETE fm1 from file_managed fm1
JOIN file_managed fm2 on fm2.fid < fm1.fid AND fm2.uri = fm1.uri
LEFT JOIN file_usage fu ON fu.fid = fm1.fid
LEFT JOIN media__field_upload_file m ON m.field_upload_file_target_id = fm1.fid
LEFT JOIN paragraph__field_downloads pd ON pd.field_downloads_target_id = fm1.fid
WHERE fm1.status = 0 AND fu.count IS NULL AND m.entity_id IS NULL AND pd.entity_id IS NULL;

-- After this, as applied, we should have no sets of duplicates where any record has status 0.
