<?php
/**
 * @file
 *
 */

use Drupal\DrupalExtension\Context\MarkupContext;
/**
 * Defines markup features specific to Mass.gov.
 */
class MassMarkupContext extends MarkupContext
{
  /**
   * @Then I should see the correct markup for the header
   */
  public function iShouldSeeTheCorrectMarkupForTheHeader()
  {
    $this->assertRegionElement('.ma__header__skip-nav', 'header');
    $this->assertRegionElement('.ma__header__backto', 'header');
    $this->assertRegionElement('.ma__header__container > .ma__header__logo > .ma__site-logo > a', 'header');
    $this->assertRegionElement('nav.ma__header__nav > .ma__header__button-container > .ma__header__back-button', 'header');
    $this->assertRegionElement('nav.ma__header__nav > .ma__header__button-container > .ma__header__menu-button', 'header');
    $this->assertRegionElement('nav.ma__header__nav > .ma__header__nav-container > .ma__header__main-nav > .ma__main-nav', 'header');
    //$this->assertRegionElement('nav.ma__header__nav > .ma__header__nav-container > .ma__header__main-nav > .ma__main-nav > ul.ma__main-nav__items', 'header');
    //$this->assertRegionElement('ul.ma__main-nav__items > li.ma__main-nav__item > a.ma__main-nav__top-link', 'header');
  }

  /**
   * @Then I should see the correct markup for the page banner
   */
  public function iShouldSeeTheCorrectMarkupForThePageBanner()
  {
    $this->assertRegionElement('nav.ma__breadcrumbs__container', 'breadcrumbs');
    $this->assertRegionElement('style', 'page_banner');
    $this->assertRegionElement('div.ma__page-banner__icon', 'page_banner');
    $this->assertRegionElement('svg', 'page_banner');
    $this->assertRegionElement('h1.ma__page-banner__title', 'page_banner');
  }

  /**
   * @Then I should see the correct markup for the section links
   */
  public function iShouldSeeTheCorrectMarkupForTheSectionLinks()
  {
    $this->assertRegionElement('.ma__section-links__content', 'section_links');
    $this->assertRegionElement('.ma__section-links__icon > .ma__category-icon > svg', 'section_links');
    $this->assertRegionElement('.ma__section-links__title', 'section_links');
    $this->assertRegionElement('.ma__section-links__toggle-content', 'section_links');
    $this->assertRegionElement('.ma__section-links__title > .ma__decorative-link > a', 'section_links');
    $this->assertRegionElement('.ma__section-links__title > .ma__decorative-link > a > svg', 'section_links');
    $this->assertRegionElement('.ma__section-links__toggle-content > .ma__section-links__description', 'section_links');
    $this->assertRegionElement('.ma__section-links__toggle-content .ma__section-links__mobile-title > .ma__decorative-link svg', 'section_links');
    $this->assertRegionElement('.ma__section-links__toggle-content .ma__section-links__items > .ma__section-links__item > .ma__decorative-link', 'section_links');
  }

  /**
   * @Then I should see the correct markup for the footer
   */
  public function iShouldSeeTheCorrectMarkupForTheFooter()
  {
    // Commenting out back2top because removing it is likely temporary.
    // $this->assertRegionElement('button.ma__footer__back2top', 'footer');
    $this->assertRegionElement('.ma__footer-new__container > .ma__footer-new__content > nav', 'footer');
    $this->assertRegionElement('.ma__footer-new__container > .ma__footer-new__logo', 'footer');
    $this->assertRegionElement('.ma__footer-new__container > .ma__footer-new__content > .ma__footer-new__copyright', 'footer');
  }

  /**
   * @Then I see the subtopic page markup
   */
  public function iSeeTheSubtopicPageMarkup()
  {
    $this->assertRegionElement('.ma__page-header__content > h1.ma__page-header__title', 'page_header');
    //$this->assertRegionElement('.ma__page-header__content > h4.ma__page-header__sub-title', 'page_header');
    $this->assertRegionElement('section.ma__action-finder .ma__action-finder__container', 'page_main');
    $this->assertRegionElement('section.ma__action-finder header.ma__action-finder__header > h2.ma__action-finder__title', 'page_main');
    $this->assertRegionElement('section.ma__action-finder .ma__action-finder__category', 'page_main');
    $this->assertRegionElement('section.ma__action-finder div.ma__action-finder__items a.ma__callout-link', 'page_main');
    $this->assertRegionElement('section.ma__link-list > h2.ma__comp-heading', 'page_main');
    $this->assertRegionElement('section.ma__link-list > .ma__link-list__container > ul.ma__link-list__items > li.ma__link-list__item > span.ma__decorative-link > a.js-clickable-link', 'page_main');
    $this->assertRegionElement('section.ma__image-credit > div.ma__image-credit__container > span.ma__image-credit__label', 'page_post');
  }

  /**
   * @Then I should see the correct markup for the top actions
   */
  public function iShouldSeeTheCorrectMarkupForTheTopActions()
  {
    $this->assertRegionElement('h2.ma__top-actions__title', 'top_actions');
    $this->assertRegionElement('div.ma__top-actions__items', 'top_actions');
    //$this->assertRegionElement('ul.ma__top-actions__items > li.ma__top-actions__item > div.ma__top-actions__link > div.ma__callout-link > span.ma__decorative-link > a.js-clickable-link', 'top_actions');
  }

  /**
   * @Then I should see the correct markup for the header search form
   */
  public function iShouldSeeTheCorrectMarkupForTheHeaderSearchForm()
  {
    $this->assertRegionElement('div.ma__header__search > section.ma__header-search > div#cse-header-search-form > div.gsc-control-searchbox-only > form.gsc-search-box > table.gsc-search-box > tbody > tr > td.gsc-input > input.gsc-input', 'header');

    $this->assertRegionElement('div.ma__header__search > section.ma__header-search > div#cse-header-search-form > div.gsc-control-searchbox-only > form.gsc-search-box > table.gsc-search-box > tbody > tr > td.gsc-search-button > input.gsc-search-button', 'header');
  }

  /**
   * @Then I should see the correct markup for the results search form
   */
  public function iShouldSeeTheCorrectMarkupForTheResultsSearchForm()
  {
    $this->assertRegionElement('div.ma__content__search > section.ma__content-search > div#cse-search-results-form > form.gsc-search-box > table.gsc-search-box > tbody > tr > td.gsc-input > input.gsc-input', 'page_pre');

    $this->assertRegionElement('div.ma__content__search > section.ma__content-search > div#cse-search-results-form > form.gsc-search-box > table.gsc-search-box > tbody > tr > td.gsc-search-button > input.gsc-search-button', 'page_pre');
  }

  /**
   * @Then I should see the correct markup for the search results
   */
  public function iShouldSeeTheCorrectMarkupForTheSearchResults()
  {
    $this->assertRegionElement('div#cse-search-results > div.gsc-control-cse > div.gsc-control-wrapper-cse  > div.gsc-results-wrapper-nooverlay > div.gsc-wrapper', 'search_results');
  }

  /**
   * @Then I should see the correct markup for the illustrated header
   */
  public function iShouldSeeTheCorrectMarkupForTheIllustratedHeader()
  {
    $this->assertRegionElement('div.ma__illustrated-header__container > .ma__illustrated-header__content > .ma__illustrated-header__category', 'page_illustrated_header');
    $this->assertRegionElement('div.ma__illustrated-header__container > .ma__illustrated-header__content > .ma__page-header > .ma__page-header__content > h1.ma__page-header__title', 'page_illustrated_header');
    $this->assertRegionElement('div.ma__illustrated-header__image.ma__illustrated-header__image--empty', 'page_illustrated_header');
  }

  /**
   * @Then I should see the correct markup for the related guides
   */
  public function iShouldSeeTheCorrectMarkupForTheRelatedGuides()
  {
    $this->assertRegionElement('div.ma__suggested-pages__container > h2.ma__suggested-pages__title', 'guide_related_guides');
    $this->assertRegionElement('div.ma__suggested-pages__container > .ma__suggested-pages__items .ma__suggested-pages__item.ma__suggested-pages__item--guide > .ma__illustrated-link > .ma__illustrated-link__content > div.ma__illustrated-link__title', 'guide_related_guides');
  }
}
