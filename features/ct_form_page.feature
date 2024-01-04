@api @api2
Feature: Form Page Content type
  As a MassGov alpha content editor,
  I want to be able to add form pages,
  so that I can add interactive forms to the site.

  Scenario: Verify that the form page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "form_page" content has the correct fields


  Scenario: Formstack URLs are output correctly when using the formstack type
    Given I am viewing a "form_page" content:
      | title | Form Test Page |
      | field_form_embed:type  | formstack |
      | field_form_embed:value | "<script type="text/javascript" src="http://example.formstack.com/script?nojquery=1"></script><noscript><a href="http://example.formstack.com/noscript" title="Online Form">Test</a></noscript><div><a href="http://example.com/link" title="Web Form Creator">Web Form Creator</a></div>" |
      | moderation_state       | published |
    Then I should see a script element with the source "http://example.formstack.com/script?nojquery=1&jsonp=1"

  Scenario: Formstack URLs are output correctly when using the formstack_reload type
    Given I am viewing a "form_page" content:
      | title | Form Test Page |
      | field_form_embed:type  | formstack_reload |
      | field_form_embed:value | "<script type="text/javascript" src="http://example.formstack.com/script?nojquery=1"></script><noscript><a href="http://example.formstack.com/noscript" title="Online Form">Test</a></noscript><div><a href="http://example.com/link" title="Web Form Creator">Web Form Creator</a></div>" |
      | moderation_state       | published |
    Then I should see a script element with the source "http://example.formstack.com/script?nojquery=1"

  Scenario: Verify that pathauto patterns are applied to Form Page nodes.
    Given I am viewing an "form_page" content with the title "Test Form Page"
    Then I am on "forms/test-form-page"

  @caching @dynamic_cache
  Scenario: Verify that the form page content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing an "form_page" content with the title "test form page"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
