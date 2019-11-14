# mass_theme

`mass_theme` is a custom theme that [integrates with our pattern library, Mayflower](../../../../docs/Mayflower.md), through the use of a [custom module called mayflower](../../../modules/custom/mayflower).

## How this theme works

## Non-Mayflower Workflow
If not using Mayflower during the theming process, this theme is configured to use Gulp,Node, NPM and NVM.  Follow these steps to work with the theme:
 * Navigate to `mass_theme` to execute all the code compiling commands
 * Run `npm install` to ensure all theme dependencies are installed.  If you run into install issues, you may want to remove `/node_modules` directory first and then execute `npm install`.
 * Run `nvm install` to install the version of node specified in `.nvmrc`.  This ensures all developers use the same version of node to avoid conflicts.
 * To compile the theme run `npm run build`.  This command will run all gulp tasks (i.e. compile, compress, move, clean, lint, etc.).  If you only want to compile Sass, you can run `npm run compile`.  For compressing assets run `npm run compress`, etc.

Note:  The first 3 commands above don't need to be ran every time, only when you first start up the project or if you update your tools/dependencies.

## Mayflower Workflow
In general, `mass_theme` handles the templating for global elements (i.e. header, footer, menu) and for node layout.  For most content types, there is a node template which closely follows the format of either the corresponding Mayflower `@templates` or `@pages` pattern for a given page type.

These node templates often break the node-level data structure (i.e. `$variables[]`) into smaller data structures, which are then sent to the node's included  `@organisms` and `@molecules` templates.

See the included Mayflower `@organisms` or `@molecules` pattern `.json` data files for an example populated data structure, and read the Mayflower pattern `.md` documentation files to learn more about the data structure (i.e. data types, required vs optional variables/properties).

You can find these files inside of the `mass_theme/patterns` directory, which contains symlinks to the [Mayflower Artifacts](../../../../docs/Mayflower.md#mayflower-artifacts) composer package directories. The `.json` and `.md` files should appear alongside of the `.twig` markup file for a given pattern. *If you ever have a question or if you'd like to update some documentation, we'd welcome [your contribution](https://github.com/jesconstantine/mayflower/blob/355c57243f820f4137d48184e2dc31129f75367c/.github/CONTRIBUTING.md)!*

### Using mayflower module to get data

`mass_theme.theme` implements node-level preprocess hooks to construct the node-level data structure, which is sent to the node template in `$variables[]`.

This node preprocess hook follows the "recipe" of included child patterns from the page type's `@templates` or `@pages` template.  These child patterns are configured in the preprocess hook and then "prepared" by the mayflower module.

#### Configuring patterns

Patterns can often be configured, based on their usage on a given page type.  These options often correspond to pattern variants and certain variable values in Mayflower.

You can see what options are available by reading the `pattern~variant.json` data files and `.md` documentation for a child pattern.  You can learn which variants and options are being applied to a pattern on a given page type by consulting the `.json` data file for that page type.  *Learn more about variants, or psuedo-patterns in [Pattern Lab documentation](http://patternlab.io/docs/pattern-pseudo-patterns.html).*

Pattern config is set in a `mass_theme.theme` preprocess hook by creating a `pattern_options` array.  For example:

```php
//  .../themes/custom/mass_theme/mass_theme.theme
// mass_theme_preprocess_node_*()

$compHeading_options = [
  'title' => 'My Heading',
  'sub' => TRUE,
];
```

In the above example, we configure the `comp-heading` pattern by setting the option `'sub' => TRUE` to indicate that in this instance, we want to use the `comp-heading~subheading` pattern variant.

We are also passing the string that we want to use to populate the `compHeading.title` variable.  `'My Heading'` could be a variable set to the value of a field or field label, or it could be a hard coded string that we set, given the context of the page (design) and content type (implementation).

#### Preparing patterns

Once a pattern is configured, the next step is to "prepare" the pattern to get the necessary data structure.  For example:

```php
// .../themes/custom/mass_theme/mass_theme.theme

use Drupal\mayflower\Prepare\Atoms;

// mass_theme_preprocess_node_*()

$variables[compHeading] = Atoms::prepareCompHeading($compHeading_options);
```

In the example above we are invoking the `Atoms::prepareCompHeading` method, passing the options that we set [above](#configuring-patterns).  We are able to use this method, because we've declared usage of the mayflower module Atoms class with the [use statement](https://www.drupal.org/docs/develop/coding-standards/namespaces) `use Drupal\mayflower\Prepare\Atoms`.

The mayflower module prepare methods return the necessary data structure for a given pattern, based on the passed options and, when necessary, `$entity` object (often `$node` but could be other Drupal entities).  *Learn more about [mayflower module](../../../modules/custom/mayflower).*

### Simple Twig rendering engine

`mass_theme` uses Simple Twig, a [custom rendering engine](../../engines/simpler_twig/simpler_twig.engine) that tells Drupal to look for and use templates with the extension `.twig`.  This allows us to use the actual templates files from Mayflower (Pattern Lab) which use the `.twig` extension.

## Resources

 * [Mayflower project](https://github.com/massgov/mayflower)
 * [Working with Drupal and Mayflower](../../../../docs/Mayflower.md)
 * [Mayflower module](../../../modules/custom/mayflower)
 * [Drupal 8 Theming Guide](https://www.drupal.org/docs/8/theming)
