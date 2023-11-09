# Performance Optimization and Profiling

## What to Optimize

There's no one size fits all answer to what needs to be optimized, but in general, here is the priority:

1. Anything in the "critical path" - any code that's executed even for cached pageviews is considered to be in the critical path. Tiny changes can have big impacts here.
2. Things that are executed frequently. For example, service and org pages are some of the most popular content on the site, and there are a lot of them. Optimizations on these pages are probably worthwhile because they will be used frequently. Similarly, things like metatags that are used on every page are high value.
3. Pages or elements that are extremely slow. Even if they're not viewed much, concurrent views to a single slow page can impact the response time for the whole site.

## Things to look for

- Repeated executions of the same functions, especially in custom code. If a function is being executed a lot of times on a single pageview (and is relatively slow), it's often worth tracing back and trying to reduce the number of calls. For example, we recently discovered that the `metatag_get_tags_from_route` function was being called 3x for each page because the static caching wasn't done right.
- Excessive calls to entity load functions. Entity loading is expensive, but necessary for the site to function. But there's a "right" way and a "wrong" way to load entities in Drupal:

  ```php
  // Bad. Runs 3 queries.
  $ids = [1, 2, 3];
  foreach($ids as $id) {
    $node = Node::load($id);
  }

  // Good.  Runs 1 query.
  $ids = [1, 2, 3];
  foreach (Node::loadMultiple($ids) as $node) {

  }
  ```

  You should aim to batch load entities wherever you're dealing with more than one entity. This goes for entity reference fields as well:

  ```php
  // Bad. Runs as many queries as there are items.
  foreach($node->field_my_reference as $item) {
    $itemEntity = $item->entity;
  }
  // Good. Runs 1 query.
  foreach($node->field_my_reference->referencedEntities() as $itemEntity) {

  }
  ```

- Expensive queries... Queries can be expensive for a few reasons:
  - They're doing more than simple equality checks (substring matching or regex).
  - They're querying a huge data set.
  - Indexes aren't being used effectively.
    Query optimization strategies is a little beyond the scope of this document, but there are a lot of good resources on the web. In general, you should keep an eye out for Views, which is capable of generating some really bad queries.
- Frequent updates of cached data. Caching is awesome! But if individual cache items are being updated very frequently, you may have a problem. Some of our early performance work on this site found a couple of contrib modules rewriting cache entries for every.single.pageview. This was triggering a lot of extra work, and even deadlocks as multiple processes tried to update the same thing. In your profiling, keep an eye out for cache set operations, and if the same item is updated twice on the same page, you may have a problem.

## Interpreting Results

There are several things to consider when you are profiling:

1. _Am I trying to profile as an anonymous or authenticated user?_ Usually, we care most about performance for anonymous users. If you need to profile as an authenticated user, you can use the [Blackfire Chrome extension](https://blackfire.io/docs/integrations/chrome) to profile the site while you are logged in.
2. _Do I want caches enabled or disabled?_ This depends on what you're testing. If you're trying to test how the page cache responds, you want to test with all of Drupal's caching layers enabled. More often, you will want to profile a page with page caching disabled to see how things respond in a "worst case scenario". Use `cache.backend.null` in `settings.local.php` to disable individual caches.
3. _Is this representative of how things will behave in production?_ Certain operations (class loading, for example) can be much slower locally than they will be in production. The rule of thumb is that if it involves filesystem access (like loading a file), it will probably be at least marginally faster in production due to Docker for Mac woes. Other operations, like database access, may be slower in production, since the database is not hosted on the same machine. So consider how what you're looking at may be different in production before making assumptions.

## Tools

### Blackfire

[Blackfire](https://blackfire.io) is a profiling tool for PHP applications. It lets you execute a web request, then show you all of the functions called during that request, as well as the amount of time each one took.

#### Installing and Running Blackfire

Follow the steps on [DDEV Blackfire page](https://ddev.readthedocs.io/en/stable/users/blackfire-profiling/). Configure the variables globally as suggested there. See the Mass.gov Blackfire subscription for credentials.

#### Sequel Pro

[Sequel Pro](https://www.sequelpro.com/) is a Mac OS X database tool to connect with the MySQL database. It allows us to connect our local, stage, and production databases to test the time it takes for a query to complete. To setup the stage and production database use the Acquia database details from those environments section to fill out the SSH tab.

Below is what you should see in the SSH tab. The pixelated areas are where you need to go to Acquia to get the environment details.

To get the environment details follow these steps:

1. Click on the environment in Acquia
2. Go to the `Databases` on the left column
3. Click the `Details` tab, where you will see the information you need to make the SSH connection.


The query in question can be found in different ways. Here are a few approaches to consider:

**Views**

```
1. Go to /admin/structure/views/settings
2. Turn on "Show the SQL query" and "Show performance statistics"
3. Go the view in the /admin/structure/views
4. Copy the Query
```

**Autocomplete (Linkit)**

```
1. In a code editor change the following lines:
			- docroot/core/lib/Drupal/Core/Entity/Query/Sql/Query.php (line 22) change protected $sqlQuery to public $sqlQuery
			-docroot/core/lib/Drupal/Core/Entity/Plugin/EntityReferenceSelection/
			DefaultSelection.php look for the "getReferenceableEntities" (line 241) add the following "dd(dpq($query->sqlQuery, TRUE));" under the $result = $query->execute();
2. Turn on the devel module "drush en devel" and "ddev restart"
3. Complete a search in autocomplete
4. Go to the terminal and "ddev ssh"
5. You will need to cd into the root level and cd /tmp directory
6. If you ls -ltr you should see a drupal-debug.txt file. `tail drupal-debug.txt` to see the new query.
```

In the Query toolbar take the old query and new query and compare the time each takes to complete. If the new query is taking significantly longer than the old query _it should not be merged into develop._ For example, if the original query completes in ms and the new query completes in seconds, once multiple content authors have logged in we _will_ see performance issues!

### Xdebug

Xdebug isn't a profiling tool, but if you know where a problem is happening in your code, you can set breakpoints using XDebug to trace it back. See [.env.example](../.ddev/.env.example) for setup instructions.

### Web Profiler

The Devel module comes with the "Web Profiler" submodule that you can enable. It will give you a nice toolbar at the bottom of the page showing things like memory usage and queries run on the page.
