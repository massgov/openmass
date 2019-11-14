# Mayflower module

This Drupal 8 module provides the "glue code" between custom Drupal theme [mass_theme](../../../themes/custom/mass_theme) and pattern library [Mayflower](https://github.com/massgov/mayflower).  For more information, read the documentation about the [relationship between Drupal and Mayflower](../../../../docs/Mayflower.md).

## How this module works

In general, the mayflower module handles data retrieval, transformation, and structuring for Mayflower patterns.

### Classes

The mayflower module is made up of 4 classes: Atoms, Molecules, Organisms, Helper.  In the Atoms, Molecules, and Organisms classes, are various "prepare" methods which return the necessary data structure for their respective patterns.  The Helper class contains helper functions (often for data transformation) shared among the other class methods.

### Prepare methods

Many prepare methods have a similar signature and footprint.  They should all start with a docblock comment which summarizes the method, lists the accepted parameters, references the pattern twig template, and describes the return value.  *Learn more about [Drupal's documentation and comment standards](https://www.drupal.org/docs/develop/coding-standards/api-documentation-and-comment-standards).*

```php
// .../modules/custom/mayflower/src/Prepare/Atoms.php

/**
   * Returns the variables structure required to render comp heading.
   *
   * @param array $options
   *   An array with options like sub, centered, and color.
   *
   * @see @atoms/04-headings/comp-heading.twig
   *
   * @return array
   *   Returns correct array for compHeading:
   *    [
   *      "compHeading": [
   *         "title": "Employment",
   *         "sub": "",
   *         "color": "",
   *         "id": "employment",
   *         "centered": ""
   *      ],
   *    ].
   */
```

#### A simple prepare method

A method that only accepts options as an argument will read in values from the options array (where they exist), perform any necessary data transformation, and return the data structure.

Here is a simple prepare method (seen documented above):

```php
// .../modules/custom/mayflower/src/Prepare/Atoms.php

public static function prepareCompHeading(array $options) {
  return [
    'compHeading' => [
      "title" => isset($options['title']) ? $options['title'] : "Title",
      "sub" => isset($options['sub']) ? $options['sub'] : FALSE,
      "color" => isset($options['color']) ? $options['color'] : "",
      "id" => isset($options['title']) ? Helper::createIdTitle($options['title']) : "title",
      "centered" => isset($options['centered']) ? $options['centered'] : "",
    ],
  ];
}
```

*It's important not to assume an option has been passed by using `isset()`.*

#### A complex prepare method

Some prepare methods are more complex, because they have to get the data from an `$entity` and perform various transformations before the data structure can be returned.

```php
// .../modules/custom/mayflower/src/Prepare/Organisms.php

 /**
   * Returns the variables structure required to render a page banner.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param object $options
   *   The object that contains static data and other options.
   *
   * @see @organisms/by-template/page-banner.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [
   *      "bgWide":"/assets/images/placeholder/1600x400.png"
   *      "bgNarrow":"/assets/images/placeholder/800x400.png",
   *      "size": "large",
   *      "icon": null,
   *      "title": "Executive Office of Health and Human Services",
   *      "titleSubText": "(EOHHS)"
   *    ]
   */
   
  public static function preparePageBanner($entity, $options = NULL) {
    $pageBanner = [];
    ...
```

As you can tell from both the documentation and signature, this method accepts an additional argument `$entity` which refers to the Drupal entity containing the fields which we'll get data from.

##### Field maps

Many of these more complex methods will have a `$map` array, whose keys correspond to pattern variable names and whose values are an array of field machine names. 

Because we share patterns across our site, these field name arrays will grow as more Drupal entities (nodes, paragraphs, etc.) are implemented.

*Note: We are considering a future implementation where a field map is passed to the prepare method.*

```php
// .../modules/custom/mayflower/src/Prepare/Organisms.php

public static function preparePageBanner($entity, $options = NULL) {
...

// Create the map of all possible field names to use.
$map = [
  'title' => ['title'],
  'title_sub_text' => ['field_title_sub_text'],
  'bg_wide' => [
    'field_bg_wide',
    'field_service_bg_wide',
    'field_topic_bg_wide',
  ],
  'bg_narrow' => [
    'field_bg_narrow',
    'field_service_bg_narrow',
    'field_topic_bg_narrow',
  ],
  'description' => ['field_lede', 'field_topic_lede'],
];

// Determines which field names to use from the map.
$fields = Helper::getMappedFields($entity, $map);

...
}
```

In the example above, `Helper::getMappedFields` returns an array similar to `$map` where the key will correspond to the Mayflower pattern variable, but this time the value will be the `$entity` field machine field name which will be the data source for that variable.  In other words, we are mapping `$entity` field machine names to a Mayflower pattern variable so that we can build the pattern's data structure.

##### Data retrieval

Now that we have our entity fields, we need to get data from the `$entity` (and/or our passed `$options`, as seen in the [simple prepare method example above](#a-simple-prepare-method)).

Sometimes a prepare method will simply need to retrieve data using the `$entity` and field name.

```php
// .../modules/custom/mayflower/src/Prepare/Organisms.php

public static function preparePageBanner($entity, $options = NULL) {
...

$title_sub_text = '';
if (array_key_exists('title_sub_text', $fields) && Helper::isFieldPopulated($entity, $fields['title_sub_text'])) {
  $title_sub_text = $entity->$fields['title_sub_text']->value;
}
$pageBanner['titleSubText'] = $title_sub_text;

...
}
```

In the above example, we 1) use `array_key_exists()` to ensure that the passed `$entity` contains the field which would likely point to the data we want, 2) use a helper method to ensure that the field on that `$entity` is populated, and then 3) retrieve the value from the field using the [Drupal Entity API](https://www.drupal.org/docs/8/api/entity-api/introduction-to-entity-api-in-drupal-8): `$entity->$fields['title_sub_text']->value;`.

##### Data transformation

Sometimes a prepare method will need to use a helper method to perform the necessary data transformation on an entity's data.

```php
// .../modules/custom/mayflower/src/Prepare/Organisms.php

public static function preparePageBanner($entity, $options = NULL) {
...

// Use helper function to get the image url of a given image style.
$pageBanner['bgWide'] = Helper::getFieldImageUrl($entity, $image_style_wide, $fields['bg_wide']);

...
}
```

In the example above, the `bgWide` "property" is set by invoking the helper method `getFieldImageUrl` and passing in the `$entity`, an `$image_style_wide` variable (which was set by a value passed in the config `$options`), and the field name on the `$entity` that points to the data that we need.  *See the docblock comment of `Helper::getFieldImageUrl` for more information about that function.*

##### Return the data structure

Most prepare methods will end with a simple statement which returns the data structure variable that you just populated:

```php
// .../modules/custom/mayflower/src/Prepare/Organisms.php

public static function preparePageBanner($entity, $options = NULL) {
  $pageBanner = [];
  
  // retrieve and transform data to populate $pageBanner
  ...
    
  return $pageBanner;
}
```
