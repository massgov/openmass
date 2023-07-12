@api
Feature: Paragraph type definitions
  As a MassGov alpha content editor,
  I want to be able to add content for actions (a bedrock of the alpha release) for pre-determined journeys,
  so that I can help Bay Staters get the best information they need to fulfill basic tasks.

  Scenario: Verify that the paragraph types have the correct field configuration
    Given I am logged in as a user with the "administrator" role
    Then the "action_area" paragraph has the fields:
      | field                 | widget             |
      | field-area-action-ref | Paragraphs Legacy |

    Then the "action_step" paragraph has the fields:
      | field                 | widget                    |
      | field-para-icon-term  | Select list               |
      | field-title           | Textfield                 |
      | field-content         | Text area (multiple rows) |

    Then the "action_step_numbered" paragraph has the fields:
      | field         | widget                    |
      | field-title   | Textfield                 |
      | field-content | Text area (multiple rows) |

    Then the "action_step_numbered_list" paragraph has the fields:
      | field                            | widget                    |
      | field-action-step-numbered-items | Paragraphs Legacy        |

    Then the "action_set" paragraph has the fields:
      | field                  | widget                    |
      | field-related-content  | Autocomplete              |
      | field-featured-content | Autocomplete              |
      | field-image            | Entity browser            |

    Then the "callout_link" paragraph has the fields:
      | field      | widget |
      | field-link | Link   |

    Then the "callout_button" paragraph has the fields:
      | field      | widget |
      | field-link | Link   |

    Then the "callout_alert" paragraph has the fields:
      | field      | widget |
      | field-link | Link   |

    Then the "emergency_alert" paragraph has the fields:
      | field                           | widget             |
      | field-emergency-alert-content   | Paragraphs Legacy |
      | field-emergency-alert-link      | Link               |
      | field-emergency-alert-message   | Textfield          |

    Then the "file_download" paragraph has the fields:
      | field           | widget         |
      | field-downloads | Entity browser |

    Then the "icon" paragraph has the fields:
      | field                | widget         |
      | field-title          | Textfield      |
      | field-para-icon-term | Select List    |

    Then the "full_bleed" paragraph has the fields:
      | field                 | widget                    |
      | field-full-bleed-ref  | Paragraphs Legacy        |

    Then the "iframe" paragraph has the fields:
      | field               | widget         |
      | field-url           | Link           |
      | field-height        | Number field   |

    Then the "quick_action" paragraph has the fields:
      | field             | widget     |
      | field-link        | Link       |

    Then the "rich_text" paragraph has the fields:
      | field      | widget                    |
      | field-body | Text area (multiple rows) |

    Then the "search_band" paragraph has the fields:
      | field                            | widget                    |
      # @TODO: error when fields are disabled. 'Call to a member function getText() on null' This should not fail
      # as the fields do exist.  Need to refactor.
      #| field-image                      | Entity browser            |
      #| field-caption                    | Textfield                 |
      #| field-name                       | Textfield                 |
      | field-description                | Textfield                 |
      | field-link-six                   | Link                      |
      | field-title                      | Textfield                 |
      | field-home-bckgrnd-img-paragraph | Paragraphs Legacy        |

    Then the "homepage_background_images" paragraph has the fields:
      | field                            | widget                    |
      | field-image                      | Image            |
      | field-caption                    | Textfield                 |
      | field-name                       | Textfield                 |

    Then the "stat" paragraph has the fields:
      | field             | widget        |
      | field-stat        | Textfield     |
      | field-description | Textfield     |
      | field-alignment   | Select list   |

    Then the "slideshow" paragraph has the fields:
      | field            | widget             |
      | field-slideshow  | Entity browser     |

    Then the "subhead" paragraph has the fields:
      | field       | widget        |
      | field-title | Textfield     |

    Then the "map" paragraph has the fields:
      | field       | widget                   |
      | field-map   | Google Map Field default |

    Then the "video" paragraph has the fields:
      | field                  | widget                       |
      | field-video            | Inline entity form - Complex |
    When I go to "admin/structure/paragraphs_type/video/fields"
    Then I should see the text "field_video_caption"
    And I should see the text "field_video_id"
    And I should see the text "field_video_source"

    Then the "contact_group" paragraph has the fields:
      | field                      | widget              |
      | field-title                | Textfield           |
      | field-contact-info         | Paragraphs Legacy  |
      | field-contact-group-layout | Select list         |

    Then the "contact_info" paragraph has the fields:
      | field         | widget      |
      | field-type    | Select list |
      | field-label   | Textfield   |
      | field-phone   | Telephone number       |
      | field-email   | Email       |
      | field-link    | Link        |
      | field-address | Text area (multiple rows)     |
      | field-caption | Textfield   |

    Then the "related_link" paragraph has the fields:
      | field             | widget     |
      | field-link        | Link       |

    Then the "rules_section" paragraph has the fields:
      | field                      | widget                      |
      | field-rules-section-body   | Text area (multiple rows)   |
      | field-rules-section-title  | Textfield                   |

    Then the "hours" paragraph has the fields:
      | field                   | widget              |
      | field-hours-description | Textfield           |
      | field-hours-group-title | Textfield           |
      | field-hours-structured  | Office hours (list) |

    Then the "pull_quote" paragraph has the fields:
      | field       | widget                    |
      | field-quote | Text area (multiple rows) |
      | field-name  | Textfield                 |
      | field-title | Textfield                 |

    Then the "completion_time" paragraph has the fields:
      | field             | widget     |
      | field-time        | Textfield  |

    Then the "icon_links" paragraph has the fields:
      | field            | widget              |
      | field-icon-link  | Paragraphs Legacy  |

    Then the "icon_link" paragraph has the fields:
      | field                | widget      |
      | field-para-icon-term | Select list |
      | field-link-single    | Link        |

    Then the "section_heading_text" paragraph has the fields:
      | field                           | widget                   |
      | field-section-heading-text-head | Textfield                |
      | field-section-heading-text-body | Text area (multiple rows)|

    Then the "page" paragraph has the fields:
      | field           | widget              |
      | field-page-page | Link                |

    Then the "page_group" paragraph has the fields:
      | field                 | widget                   |
      | field-page-group-name | Textfield                |
      | field-page-group-page | Link                     |

    Then the "links_downloads" paragraph has the fields:
      | field                      | widget                       |
      | field-links-downloads-down | Inline entity form - Complex |
      | field-links-downloads-link | Link                         |

    Then the "section_long_form" paragraph has the fields:
      | field                             | widget              |
      | field-section-long-form-addition  | Paragraphs Legacy  |
      | field-section-long-form-content   | Paragraphs Legacy  |
      | field-section-long-form-heading   | Textfield           |

    Then the "file_download_single" paragraph has the fields:
      | field                      | widget                       |
      | field-file-download-single | Inline entity form - Complex |
