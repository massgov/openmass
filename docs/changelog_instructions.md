# Changelog Instructions

When PRs are added to this repo, several things happen automatically. One is to test for the presence of a correctly named and formatted changlog file. This document explains how to create that file.

## Before you submit a PR

1. Make a copy of `changelogs/template.yml` and use the Jira ticket or issue number as the file name (for example: `DP-1234.yml` or `1234.yml`).  *Note: the changelog file must be a YAML file*

1. In the new changelog file add the following items:

```
  Type: 
    - description: 
      issue: 
```
1. **Replace** the word "Type" with the appropriate item from this list:

 -  `Added` (for new features)
 -  `Changed` (for changes to existing functionality)
 -  `Deprecated` (for soon-to-be removed features)
 -  `Removed` (for removed features)
 -  `Fixed` (for bug fixes)
 - `Security` (for a vulnerability)

1. Indent `- description:` with 2 spaces:

 ```
  	- description: Describe the change. If you need multiple lines, start the first line with the following "|-" characters.
  ```

1. Indent "issue:" 4 spaces, so it lines up directly under the "d" of "description." For the issue number, enter the same number you used for the file name:

  ```
    issue: Add a Jira ticket or issue number, (e.g. DP-12345 or 1234)
  ```

**Example of a complete, correct changelog entry:**

  ```
  Fixed:
    - description: Fixes scrolling on edit pages in Safari.
      issue: 133
  ```

- Commit the file and open your PR.