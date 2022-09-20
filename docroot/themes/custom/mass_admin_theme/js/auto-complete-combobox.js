/**
 * @file
 * Add missing combobox association between auto complete fields and
 * their option boxes with aria.
 *
 * This addresses the accessibility issue with JAWS:  DP-25848
 */
(function () {
  'use strict';

    // #block-mass-admin-theme-mainpagecontent
  var autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
  var optionLists = document.querySelectorAll(".ui-autocomplete");

  autoCompleteFields.forEach(autoCompleteField => {
    // Add missing accessibility components to pairng a field and its combobox(option list).
    autoCompleteField.setAttribute("role", "combobox");
    autoCompleteField.setAttribute("aria-autocomplete", "none");
    autoCompleteField.setAttribute("aria-expanded", "false");

    console.log(autoCompleteField);

    autoCompleteField.addEventListener("keyPress", (e) => {
      console.log(e.target);
    });
  });
  // autoCompleteFields.findIndex();

  optionLists.forEach(optionList => {
    optionList.attr("role", "listbox");
  });

  // Add missing accessibility components to pairng a field and its combobox(option list) as their lists get generated.
  // Get index for the field with keypress event fired.

  // Find the UL that got its content.
  // Get ID and assign it to its corresponding field's aria-controls.
  // autoCompleteFields[XX].setAttribute("aria-controls", "ID");
  // 1. Check the UL doesn't have display: none;
  // 2. Change the aria-expanded value to true.
  // autoCompleteFields[XX].setAttribute("aria-expanded", "true");
  // Add roles to LIs and As.
  // optionLists[XX].setAttribute("role", "combobox");
  // optionLists[XX].querySelectorAll("li").setAttribute("role", "none");
  // optionLists[XX].querySelectorAll("li a").setAttribute("role", "option");


  // // When UL has display: none;, set its corresnponding field's aria-expanded to false.
  // autoCompleteFields[XX].setAttribute("aria-expanded", "false");
})();
