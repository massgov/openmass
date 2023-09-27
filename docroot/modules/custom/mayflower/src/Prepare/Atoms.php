<?php

namespace Drupal\mayflower\Prepare;

use Drupal\Core\Url;
use Drupal\mayflower\Helper;

/**
 * Provides variable structure for mayflower atoms using prepare functions.
 */
class Atoms {

  /**
   * Returns the variables structure required to render a the image atom.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param object $options
   *   The object that contains static data and other options.
   * @param string $field
   *   The field name to use otherwise the field_map will be relied on.
   *
   * @see @atoms/09-media/image.twig
   *
   * @return array
   *   Returns an array of items that contain:
   *    "image": {
   *      "alt": "eohhs logo",
   *      "src": "/assets/images/placeholder/230x130.png",
   *      "height": "130",
   *      "width": "230"
   *    }
   */
  public static function prepareImage($entity, $options, $field = NULL) {
    $image = '';

    if ($field == NULL) {
      $map = [
        'image' => [
          'field_sub_brand',
          'field_promo_image',
        ],
      ];

      // Determines which field names to use from the map.
      $fields = Helper::getMappedFields($entity, $map);
    }
    else {
      $fields['image'] = $field;
    }

    if ($src = Helper::getFieldImageUrl($entity, $options['style'], $fields['image'])) {
      // Get image alt text from entity (set to '' on falsey return).
      $image_alt = $entity->{$fields['image']}->alt ?: '';

      $image = [
        'alt' => $image_alt,
        'src' => $src,
        'height' => $options['height'],
        'width' => $options['width'],
      ];
    }

    return $image;
  }

  /**
   * Returns the variables structure required to render rich text paragraphs.
   *
   * @param object $entity
   *   The object that contains the fields.
   *
   * @see @organisms/by-author/rich-text.twig
   * @see @atoms/11-text/paragraph.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    rteElements: [
   *      [
   *        path: @atoms/11-text/paragraph.twig,
   *        data: [
   *          paragraph: [
   *            text: 'My paragraph text.'
   *          ]
   *        ]
   *      ], ...
   *    ]
   */
  public static function preparePageContentParagraph($entity) {
    $paragraphs = [];

    foreach ($entity as $paragraph) {
      if (!empty($paragraph->value)) {
        $text = $paragraph->value;
      }
      else {
        $text = $paragraph->view();
      }
      $paragraphs[] = [
        'path' => '@atoms/11-text/raw-html.twig',
        'data' => [
          'rawHtml' => [
            'content' => $text,
          ],
        ],
      ];
    }
    return [
      'rteElements' => $paragraphs,
    ];
  }

  /**
   * Returns the variables structure required to render rich text paragraphs.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   An array of options.
   *
   * @see @organisms/by-author/rich-text.twig
   * @see @atoms/11-text/paragraph.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    rteElements: [
   *      [
   *        path: @atoms/11-text/paragraph.twig,
   *        data: [
   *          paragraph: [
   *            text: 'My paragraph text.'
   *          ]
   *        ]
   *      ], ...
   *    ]
   */
  public static function prepareParagraph($entity, array $options) {

    $field = $options['field'];

    $rteElements = [
      'path' => '@atoms/11-text/paragraph.twig',
      'data' => [
        'paragraph' => [
          'text' => Helper::fieldFullView($entity, $field),
        ],
      ],
    ];

    return $rteElements;
  }

  /**
   * Returns the variables structure required to securely render text fields.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param string $field_name
   *   The name of the field.
   *
   * @see @organisms/by-author/rich-text.twig
   * @see @atoms/11-text/raw-html.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    rteElements: [
   *      [
   *        path: @atoms/11-text/paragraph.twig,
   *        data: [
   *          rawHtml: [
   *            content: 'My paragraph text.'
   *          ]
   *        ]
   *      ], ...
   *    ]
   */
  public static function prepareTextField($entity, $field_name) {
    $content = Helper::fieldFullView($entity, $field_name);
    return [
      'path' => '@atoms/11-text/raw-html.twig',
      'data' => [
        'rawHtml' => [
          'content' => $content,
        ],
      ],
    ];
  }

  /**
   * Returns the variables structure required to render sidebar heading.
   *
   * @param string $text
   *   A string containing text.
   * @param string $titleContext
   *   Add supplemental information to the heading.
   *
   * @see @atoms/04-headings/sidebar-heading.twig
   *
   * @return array
   *   Returns correct array for sidebarHeading:
   *    [
   *      "sidebarHeading": [
   *        "title": "Social"
   *      ]
   *    ]
   */
  public static function prepareSidebarHeading($text, $titleContext = NULL) {
    return [
      'sidebarHeading' => [
        'title' => $text,
        'titleContext' => $titleContext,
      ],
    ];
  }

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
   *         "titleContext": "for those looking for employment"
   *         "sub": "",
   *         "color": "",
   *         "id": "employment",
   *         "centered": ""
   *      ],
   *    ].
   */
  public static function prepareCompHeading(array $options) {
    return [
      'compHeading' => [
        'title' => isset($options['title']) ? $options['title'] : 'Title',
        'titleContext' => isset($options['titleContext']) ? $options['titleContext'] : NULL,
        'sub' => isset($options['sub']) ? $options['sub'] : FALSE,
        'color' => isset($options['color']) ? $options['color'] : '',
        'id' => isset($options['title']) ? Helper::createIdTitle($options['title']) : 'title',
        'centered' => isset($options['centered']) ? $options['centered'] : '',
        'level' => isset($options['level']) ? $options['level'] : '',
      ],
    ];
  }

  /**
   * Returns the variables structure required to render column heading.
   *
   * @param string $text
   *   A string containing text.
   *
   * @see @atoms/04-headings/column-heading.twig
   *
   * @return array
   *   Returns correct array for sidebarHeading:
   *    [
   *      "columnHeading": [
   *        "title": "Social"
   *      ]
   *    ]
   */
  public static function prepareColumnHeading($text) {
    return [
      'path' => '@atoms/04-headings/column-heading.twig',
      'data' => [
        'columnHeading' => [
          'text' => $text,
        ],
      ],
    ];
  }

  /**
   * Returns the variables structure required to render a the video atom.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param array $options
   *   The object that contains static data and other options.
   *
   * @see @atoms/09-media/video.twig
   *
   * @return array|null
   *   Returns an array of items that contain:
   *    "video": {
   *      "src": "https://www.youtube.com/embed/dEkUq-Rs-Tc",
   *      "label": "Using a gas grill safely",
   *      "height": "853",
   *      "width": "480",
   *      "position": "right",
   *      "link": {
   *        "href": "/path/to/transcript",
   *        "text": "View transcript",
   *        "info": "View transcript of Using a gas grill safely",
   *        "property": ""
   *      }
   *    }
   */
  public static function prepareVideo($entity, array $options) {
    if ($entity->getEntityTypeId() != 'media' || $entity->bundle() != 'media_video') {
      return NULL;
    }

    $video = NULL;
    $video_render_array = Helper::fieldFullView($entity, 'field_media_video_embed_field');

    if (isset($video_render_array['children'])) {
      if (array_key_exists('#url', $video_render_array['children'])) {
        $name = $entity->get('name')->value;
        $src = $video_render_array['children']['#url'];

        $video = [
          'label' => $name,
          'src' => $src,
          'height' => (isset($options['height']) && !empty($options['height'])) ? $options['height'] : $video_render_array['children']['#attributes']['height'],
          'width' => (isset($options['width']) && !empty($options['width'])) ? $options['width'] : $video_render_array['children']['#attributes']['width'],
          'position' => (isset($options['position']) && !empty($options['position'])) ? $options['position'] : NULL,
        ];

        // Only add the link if field_video_description has content in it.
        if (!empty($entity->get('field_video_description')->value)) {
          $video['link'] = [
            'href' => Url::fromRoute('entity.media.canonical', ['media' => $entity->id()])->toString(),
            'property' => '',
            'info' => empty($entity->get('name')->value) ? '' : ' for ' . $name,
            'labelContext' => empty($entity->get('name')->value) ? '' : ' of ' . $name,
            'text' => 'View transcript',
          ];
        }

        if (Helper::isFieldPopulated($entity, 'field_video_transcript')) {
          $info = ' for ' . $name;
          $video['link'] = [
            'href' => Url::fromUri($entity->field_video_transcript->uri),
            'text' => 'View transcript',
            'info' => $info,
            'labelContext' => empty($entity->get('name')->value) ? '' : ' of ' . $name,
          ];
        }
      }
    }

    return $video;
  }

}
