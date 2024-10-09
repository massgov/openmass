const {danger, fail, warn} = require('danger');
const yaml = require('js-yaml');
const Ajv = require('ajv');
const {readFileSync} = require('fs');

// JSON Schema for changelog entries.
// @see https://json-schema.org/
const CHANGELOG_SCHEMA = {
  "definitions": {
    "ChangelogLine": {
      "description": "A single line in a changelog",
      "type": "object",
      "required": ["description", "issue"],
      "properties": {
        "description": {
          "type": "string"
        }
      }
    },
    "ChangelogGroup": {
      "description": "A grouping of changelog items",
      "type": "array",
      "items": {
        "$ref": "#/definitions/ChangelogLine"
      }
    }
  },
  "title": "ChangelogFile",
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "Added":  {"$ref": "#/definitions/ChangelogGroup" },
    "Changed":  { "$ref": "#/definitions/ChangelogGroup" },
    "Deprecated":  { "$ref": "#/definitions/ChangelogGroup" },
    "Removed": { "$ref": "#/definitions/ChangelogGroup" },
    "Fixed": { "$ref": "#/definitions/ChangelogGroup" },
    "Security": { "$ref": "#/definitions/ChangelogGroup" }
  },
  "anyOf": [
    {"required": ["Added"]},
    {"required": ["Changed"]},
    {"required": ["Deprecated"]},
    {"required": ["Removed"]},
    {"required": ["Fixed"]},
    {"required": ["Security"]}
  ]
}

// A list of all the changelog files that were touched during this change.
const changelogs = danger.git.fileMatch('changelogs/*.yml', '!**/template.yml');

// Check for a specific label "Awaiting Mayflower" can't merge until removed from the PR.
if (github.pr_labels = "Dependencies") {
  exit(EXIT_SUCCESS);
}

// Fail if no changelog was created.
if(!changelogs || !changelogs.created) {
  fail("Add a changelog YAML file to this PR");
}
else {
  // Validate the schema of each added changelog.
  const validator = new Ajv();
  changelogs.getKeyedPaths().created.forEach(function(file) {
    const contents = yaml.load(readFileSync(file));
    const valid = validator.validate(CHANGELOG_SCHEMA, contents);
    if(!valid) {
      fail(`Changelog is not valid the following item(s) needs to be fixed (${validator.errorsText()})`, file);
    }
  })
}

// Check for a specific label "Awaiting Mayflower" can't merge until removed from the PR.
// if (github.pr_labels = "Awaiting Mayflower Release") {
//   fail( "This PR needs to have a new Mayflower release (tag) before being merged into develop branch.");
// }

// Check for a specific label "Config Backport" and remind this PR needs to be merged before next release.
// if (github.pr_labels = "Config Backport") {
//   warn("This PR needs to be merged before the next release.");
// }
