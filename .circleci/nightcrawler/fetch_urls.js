const cp = require('child_process');

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
async function runQuery(alias, query) {
  const child = cp.spawn('drush', [alias, 'sql-cli']);
  return new Promise((resolve, reject) => {
    let stdout = Buffer.from('');
    let stderr = Buffer.from('');

    child.stdout.on('data', data => stdout = Buffer.concat([stdout, data]));
    child.stderr.on('data', data => stderr = Buffer.concat([stderr, data]));
    child.once('exit', (code, signal) => {
      if(code === 0) {
        resolve(
          stdout.toString('utf8')
            .trim()// Strip trailing newlines.
            .split('\n') // Explode on newline.
            .slice(1) // Cut off the header.
            .map(row => row.split('\t')) // Split columns
        );
      }
      else {
        reject(`Exit with error code: ${code}. Message: ${stdout.toString()}`)
      }
    });
    child.once('error', err => {
      reject(err);
    });
    child.stdin.write(query, () => {
      child.stdin.end();
    })
  });
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
function fetchSamplesForType(alias, type, sampleSize) {
  var query = "SELECT alias, CONCAT('/node/', nid) AS entity FROM node_field_data n INNER JOIN path_alias u ON CONCAT('/node/', n.nid) = u.path WHERE n.status = 1 AND type = '" + type + "' LIMIT " + sampleSize;

  return runQuery(alias, query).then(function(rows) {
    return rows.map(function(row) {
      return row[0] || row[1];
    })
  })
}

module.exports = {
  fetchTypes,
  fetchSamplesForType
}
