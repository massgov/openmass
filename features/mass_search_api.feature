@search_api
@api
Feature: Mass Search API
  As a site visitor,
  I want to be able to search the site,
  so that I can find content related to my query.

  # Org API tests
  Scenario: Verify Org endpoint displays org data
    Given I am viewing a published org_page with the title "IsAnOrg"
    And I request "/api/v1/orgs"
    Then the response is success
    And the response body contains JSON:
      """
      [
        {"name": "IsAnOrg"}
      ]
      """

  Scenario: Verify Org detail endpoint displays org data
    Given I am viewing a published org_page with the title "HasNewsOrg"
    And the news node "Extra Extra" references org "HasNewsOrg"
    And I request "/api/v1/orgs/detail"
    Then the response is success
    And the response body contains JSON:
      """
      [
        {"name": "HasNewsOrg"}
      ]
      """
    And the response body contains JSON:
      """
      [
        {"contentInfo": ["news"]}
      ]
      """

  # QA nodes are identified by titles prefixed with _QA.  They should not
  # be included in API responses.
  Scenario: Verify Org endpoint does not display QA orgs:
    Given I am viewing a published org_page with the title "_QA: MyTestOrg"
    And I request "/api/v1/orgs"
    Then the response is success
    Then the response body does not contain JSON:
      """
      [
        {"name": "_QA: MyTestOrg"}
      ]
      """

  Scenario: Verify Org endpoints display all org item data
    Given I am viewing an org_page content:
      | title                | An Org Page           |
      | field_title_sub_text | (AOP)                 |
      | field_sub_title      | AOP description text. |
      | moderation_state     | published             |
    And the advisory node "Advisory from Org" references org "An Org Page"
    And the binder node "Binder from Org" references org "An Org Page"
    And the decision node "Decision from Org" references org "An Org Page"
    And the executive_order "Executive Order from Org" with org ref to "An Org Page"
    And the news node "News from Org" references org "An Org Page"
    And the regulation node "Regulation from Org" references org "An Org Page"
    And the rules node "Rules of Court from Org" references org "An Org Page"
    And the service_page node "Service Page from Org" references org "An Org Page"
    And I request "/api/v1/orgs"
    Then the response is success
    And the response body contains JSON:
      """
      [
        {
          "nid": "@variableType(integer)",
          "name": "An Org Page",
          "acronym": "(AOP)",
          "url": "http://mass.local/orgs/an-org-page",
          "logoUrl": "",
          "description": "AOP description text."
        }
      ]
      """
    And I request "/api/v1/orgs/detail"
    Then the response is success
    And the response body contains JSON:
      """
      [
        {
          "nid": "@variableType(integer)",
          "name": "An Org Page",
          "acronym": "(AOP)",
          "url": "http://mass.local/orgs/an-org-page",
          "logoUrl": "",
          "description": "AOP description text.",
          "contentInfo": [
            "advisory",
            "binder",
            "decision",
            "executive_order",
            "news",
            "regulation",
            "rules",
            "service_page"
          ]
        }
      ]
      """

  # News API tests
  Scenario: Verify News endpoint displays news data
    Given I am viewing a published org_page with the title "HasNewsOrg"
    And a news item "HasNewsOrg TestNews" referencing "HasNewsOrg"
    And I request "/api/v1/news"
    Then the response is success
    And the response body contains JSON:
      """
      [
        {"title": "HasNewsOrg TestNews"}
      ]
      """

  Scenario: Verify News endpoint does not display QA news:
    Given I am viewing a published org_page with the title "HasNewsOrg"
    And a news item "_QA: HasNewsOrg TestNews" referencing "HasNewsOrg"
    And I request "/api/v1/news"
    Then the response is success
    And the response body does not contain JSON:
      """
      [
        {"title": "_QA: HasNewsOrg TestNews"}
      ]
      """

  Scenario: Verify News endpoint displays all news item data
    Given I am viewing a published org_page with the title "HasNewsOrg"
    And a news item "HasNewsOrg TestNews" referencing "HasNewsOrg"
    And I request "/api/v1/news"
    Then the response is success
    And the response body contains JSON:
      """
      [
        {
          "nid": "@variableType(integer)",
          "title": "HasNewsOrg TestNews",
          "url": "http://mass.local/news/hasnewsorg-testnews",
          "type": "",
          "datePublished": "@variableType(string)",
          "signees": ["HasNewsOrg"]
        }
      ]
      """
