
const Promise = require('bluebird')
const request = require('request-promise');
const cp = require('child-process-promise');

function fetchUrls(base, alias, sampleSize) {
  return fetchTypes(alias)
    .then(function(types) {
      return Promise.map(types, function(type) {
        return fetchNodeUrls(alias, type, sampleSize)
          .then(function(urls) {
            return urls.map(function(url) {
              return {
                url: base + url,
                group: ['node', 'node:' + type]
              }
            })
          })
      }, {concurrency: 2}).reduce(flattenResultArrays, [])
    })
}

module.exports = fetchUrls;

/**
 * Fetch an array of node types.
 *
 * @param string alias
 * @return Promise<Array<String>>
 */
function fetchTypes(alias) {
  var query = "SELECT type FROM node_field_data WHERE type NOT IN ('contact_information', 'fee', 'person', 'legacy_redirects', 'decision_tree_branch', 'decision_tree_conclusion') AND status = 1 GROUP BY type"

  return runQuery(alias, query)
    .then(function(rows) {
      return rows.map(function(row) {
        return row[0]
      })
    })
}

/**
 * Runs a query, returning result rows as an array of arrays.
 *
 * @param string alias
 *    The Drush site alias to pull from.
 * @param string query
 *    The query to run.
 */
function runQuery(alias, query) {
  // var promise = cp.spawn('drush', [alias, 'ssh', 'drush sql-cli'], {capture: ['stdout', 'stderr']})
  var promise = cp.spawn('drush', [alias, 'sql-cli'], {capture: ['stdout', 'stderr']})

  return promise
    .progress(function(child) {
      child.stdin.setEncoding('utf-8')
      child.stdin.write(query)
      child.stdin.end();
    })
    .then(function(result) {
      return result.stdout
        .trim() // Strip trailing newlines.
        .split('\n') // Explode on newline.
        .slice(1) // Cut off the header.
        .map(function(row) { return row.split('\t')}) // Explode each row on tabs.
    })
    .catch(function(result) {
      return Promise.reject('Error running SQL: ' + result.stderr);
    })
}

/**
 * Fetch a list of node URLs from the JSON API.
 *
 * @param string alias
 *    The Drush site alias to pull from.
 * @param string type
 *    The node type.
 * @param number sampleSize
 *    The number of nodes of each type to return.
 *
 * @return Promise<Array<String>>
 */
function fetchNodeUrls(alias, type, sampleSize) {
  var query = "SELECT alias, CONCAT('/node/', nid) AS entity FROM node_field_data n INNER JOIN path_alias u ON CONCAT('/node/', n.nid) = u.path WHERE n.status = 1 AND type = '" + type + "' LIMIT " + sampleSize;

  return runQuery(alias, query).then(function(rows) {
    return rows.map(function(row) {
      return row[0] || row[1];
    })
  })
}

function flattenResultArrays(prev, curr) {
  return prev.concat(curr);
}
