# How to map a content type to schema.org

Mapping content types to schema.org properties relies on the custom module `mass_schema_metatag`. The custom module itself depends on the contrib module `schema_metatag` as listed under dependencies in `mass_schema_metatag.info.yml`.

## Parent module structure

The custom module contains a `.yml` file and subfolders:

- `mass_schema_metatag.info.yml` contains brief module information that we see when we want to enable or disable a module (i.e. `/admin/modules`). As mentioned above, it has a dependency on `schema_metatag` contrib module. This latter already exists in the current codebase at the time of writing this guide.
- Each folder sibling to the `.yml` file, handles a given content type. Example: `mass_schema_government_service` and `mass_schema_apply_action` that maps to "Service Page" and "How-To Page" respectively.

```
│
├── mass_schema_metatag
│   ├── mass_schema_apply_action
│   ├── mass_schema_collection_page
│   ├── mass_schema_government_service
│   ├── mass_schema_metatag.info.yml
│   └── mass_schema_unit_price_specification
│  
```

## Mapping a content type

Mapping a content type starts with documented, discussed and agreed upon field mappings. In fact, the folders mentioned above are modules themselves since they have `.yml` file, `.module` file and a `src` folder. This latter contains the code that handles field mappings with schema.org types and properties. Once a new mapping is in place, make sure that it is enabled here `/admin/modules`; and that its enabled state is captured in configuration by running `drush config-export`.

## Example: Government Service

In our `mass` codebase, we map the http://schema.org/GovernmentService to the "Service Page" content type. To view the corresponding schema.org field mappings handled by code in the `Tag` folder, go to `/admin/config/search/metatag` and click on "Edit" for the corresponding content type that you are working on. In our example, the relevant path where to view fields and their values is `/admin/config/search/metatag/node__service_page`. Please note not all fields are mapped due to the way these content types were built; therefore, you may stumble on content types that will not fully map to schema.org.

## Government Service folder structure

```
│
├── mass_schema_government_service
│   ├── mass_schema_government_service.info.yml
│   ├── mass_schema_government_service.module
│   └── src
│       └── Plugin
│           └── metatag
│               ├── Group
│               │   └── SchemaGovernmentService.php
│               └── Tag
│                   ├── SchemaGovernmentServiceAreaServed.php
│                   ├── SchemaGovernmentServiceCategory.php
│                   ├── SchemaGovernmentServiceDescription.php
│                   ├── SchemaGovernmentServiceDisambiguatingDescription.php
│                   ├── SchemaGovernmentServiceId.php
│                   ├── SchemaGovernmentServiceLogo.php
│                   ├── SchemaGovernmentServiceName.php
│                   ├── SchemaGovernmentServicePotentialAction.php
│                   ├── SchemaGovernmentServiceRelatedLink.php
│                   ├── SchemaGovernmentServiceRelatedServices.php
│                   └── SchemaGovernmentServiceType.php
│
```

_Following the directory structure shown above, files live under the folders `Group` and `Tag`._

### Preprocess functions

It is worth noting here that some values may need pre-processing before they are handed over to the classes in the `Tag` folder. This is accomplished with the usage of `hook_tokens()`. For example, if a content type has a field with multiple values e.g. "Related Links", it important to pre-process these and get an array of these links. e.g. "link" and "url". Further validating can include a check whether a links is empty or not …etc. Additional token display can be edited or added by going to `/admin/structure/types/manage/<CONTENT_TYPE>/display/` . If you do not see "Token" as an available display, scroll down to the bottom of the page and click "CUSTOM DISPLAY SETTINGS" while you are on the "Default" display mode.

### Group folder

`Group`: Contains `SchemaGovernmentService.php` file that accomplishes the following:

- Declare a new name space under which the mapping for "GovernmentService" will be contained:

  ```
  namespace Drupal\mass_schema_government_service\Plugin\metatag\Group;
  ```

- Since the work in our custom `mass_schema_metatag` is an extension of the contrib module `schema_metatag`, we import the code that helps us with the grouping using:

  ```
  use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;
  ```

- Introduce a new class, which is an extension from the contrib module `schema_metatag`. For now, a placeholder:

  ```
  class SchemaGovernmentService extends SchemaGroupBase {
    // Nothing here yet. Just a placeholder class for a plugin.
  }
  ```

### Tag folder

The `tag` folder contains individual field mappings of the content type in question to schema.org properties.

### Example tag: `name`

In `SchemaGovernmentServiceName.php` we start with declaring a name space where all these fields/tags will belong to as follow:

```
namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;
```

We also extend the contrib module using by importing some code (i.e. classes and methods)

```
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
```

Now we create the tag `name` by extending the recently imported `SchemaGovernmentServiceName` as follow:

```
class SchemaGovernmentServiceName extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }
}

```

Note that now the class that handles the schema.org name of this type is called `SchemaGovernmentServiceName`, for the type you are creating, it may will have a different name e.g. `SchemaCoolTypeName`. The code snippet above fills out the `name` with the node title.

Please refer to other files in the `tag` folder to get more familiar how a field is mapped out.

## Notes:

- Some filed are straight forward to map out e.g. node title.
- Entity reference fields need further code crafting.
- Fields with multiple values entity reference or not may need graceful loop iteration over the multiple values.
