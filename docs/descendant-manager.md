# The Descendant Manager

### Purpose

The Descendant Manager indexes content using pre-defined field traversal paths in node type .yml
files to determine relationships.

Specific use cases:
- Determine the service that the page is in so that we can show the correct “login” links in the header, which we define on that service page.
The service may or may not be directly connected to the page where we want to show the login link. It may be a grandparent. We may want to follow links upward and not strictly parent child relationships.
- Populate “Pages linking here” in Drupal.
- Populate web metrics database with parent child relationships so that we can measure traffic to children (which represents all ancestors no matter what the depth).
In this case, only the parent and child relationships are used, not linking.


These relationships are grouped into three types:

- Parents
- Children
- Linking Pages

### Relationship Types

- The `Parents` relationship indicates that the base entity will be considered
  hierarchically lesser than any entity referenced in the fields.
  - If a `node.type.[name].yml` file has fields specified in the `parents` group
    the node referenced by these fields will be considered the "Parents" of the
    node that references them
- The `Children` relationship indicates that the base entity will be considered
  hierarchically greater than any entity referenced in the fields specified in
  its grouping.
  - If a `node.type.[name].yml` file has fields specified in the `children` group
    the node referenced by these fields will be considered the "Children" of the
    node that references them
- The `Linking Pages` relationship indicates that the base entity links out to
  any entity referenced by the fields in its grouping.
  - If a `node.type.[name].yml` file has fields specified in the `linking_pages` group
    the entities referenced by those fields will display the base node in
    their "Pages Linking Here" tab.

### Field Traversal Paths

Field traversal paths are the route the Descendant Manager must take to get from
one node to another. So if `node/456` links to `node/123` through `field_link1` the Descendant Manager
would need to know to look up the information in `field_link1` of `node/456` to create a relationship between those 2 nodes.

We do this by defining traversal paths in the `node.type.[name].yml` file. In
most cases where an entity reference or link field makes a direct reference to
the entity we are intending to reference simply adding the field name to the
group which we need the field to show up in will suffice:

```
mass_content_api:
  parents: null
  children: null
  linking_pages:
    - field_link1
```

In the above instance we are saying the content type has no parents or children
but any content in `field_link1` is considered "Linking Page" content and should
appear in the "Pages Linking Here" tab of the corresponding referenced entity.

When we have fields with multiple levels of field reference, we set up a full
traversal path for the Descendant Manager. Instances of multi-level reference
can be fields that reference a Paragraph with an entity reference field to a
node or a link field. These full traversal paths look like this:

```
mass_content_api:
  parents: null
  children: null
  linking_pages:
    - field_paragraph_ref>field_link
```

Here we tell the Descendant Manager to first look for the `field_paragraph_ref`
field on our base node, then find the `field_link` field on the Paragraph.

Full traversal paths can be however long they need to be to get to the field
that contains the desired reference.

In some cases there will be multiple entity reference or link fields in the full
traversal path that we wish to capture:

```
mass_content_api:
  parents: null
  children: null
  linking_pages:
    - field_paragraph_ref>*
```

When that is the case we can use the `*` (asterisk) character to tell the
Descendant Manager to look for entity references and links on all fields in the
current path. Using the above; the Descendant Manager would first traverse
through the `field_paragraph_ref` field to the Paragraph entity then search its
fields selecting _all_ available entity reference and link fields.

**Note: The asterisk terminates at one level, so if you need to traverse past
the level indicated by the asterisk you will need to specify each link or entity reference field
individually.**

Further examples and usage of field traversal paths can be found in most node type `.yml`
files. (conf/drupal/config/node.type.[content type].yml)

### Adding and updating content types

When a new content type is added its field traversal paths must also be manually
added to the corresponding `node.type.[name].yml` file. This is only necessary
if you anticipate the node type will be used for generating relationships via
the Descendant Manager or authors will need to be aware of any pages linked to
or from the content.

When a field traversal path is updated (not necessarily when a new one is
added) via the node type's `.yml` file you _must_ write a HOOK_deploy_NAME() hook to
re-queue all content of the type being updated. This ensures the Descendant
Manager processes these node relationships.

An example HOOK_deploy_NAME() hook follows:

```
function mass_content_api_deploy_queue_nodes_for_save() {
  $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

  $bundles = ['service_page', 'org_page'];

  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundles, 'IN')
    ->sort('nid')
    ->execute();
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\QueueInterface $queue */
  $descendant_queue = $queue_factory->get('mass_content_api_descendant_queue');
  // The descendant queue requires full node loads, which we'd like to batch.
  // Queue these up in chunks of 150 so they run through faster.
  foreach (array_chunk($nids, 150) as $chunk) {
    $descendant_queue->createItem((object) ['ids' => $chunk]);
  }
  drush_print('Queued ' . count($nids) . ' nodes for re-indexing to Descendants table.');
}
```
### Descendant Manager queue

To see how many items still need to be processed by descendant manager, run:

```
drush queue:list
```
To process all items in the queue, run:

```
drush queue:run mass_content_api_descendant_queue
```

There are options to limit the number of items handled at once:  --items-limit=500 or --time-limit=30

If there is a large number of items to process, consider batching them with a command like this:
```
watch -n 45 ddev drush queue:run mass_content_api_descendant_queue --time-limit=30
```
