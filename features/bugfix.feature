@api
Feature: Behat tests for bugfixes
  As a developer,
  I want to be able to provide better more stable code via better behat test
  so we can create the best content experience for the constituents of Massachusetts.

  Scenario: DP-1223 Verify that entity can embed alt and title
    Given I am logged in as a user with the "developer" role
    When I go to "admin/config/content/formats/manage/basic_html"
    Then I should see text matching "<a href hreflang data-entity-type data-entity-uuid data-entity-substitution> <sup> <sub> <em> <strong> <cite> <blockquote cite> <code> <ul start type> <ol start type> <li> <dl> <dt> <dd> <h2 id> <h3 id> <h4 id> <h5 id> <h6 id> <p> <br> <span> <img src alt height width data-entity-type data-entity-uuid data-entity-substitution data-align data-caption> <table> <caption> <tbody> <thead> <tfoot> <th> <td> <tr> <drupal-entity data-entity-type data-entity-uuid data-entity-substitution data-entity-embed-display data-entity-embed-display-settings data-align data-caption data-embed-button title alt>" in field "textarea#edit-filters-filter-html-settings-allowed-html"
