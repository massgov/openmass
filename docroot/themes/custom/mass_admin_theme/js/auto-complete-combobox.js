/**
 * @file
 * Add missing combobox association between auto complete fields and
 * their option boxes with aria.
 *
 * This addresses the accessibility issue with JAWS:  DP-25848
 */
(function () {
  'use strict';

  var listIndex;

  // The timeout function is necessary to recognize the fields and the lists.
  setTimeout(function () {
    var autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
    var optionLists = document.querySelectorAll(".ui-autocomplete");

    autoCompleteFields.forEach((autoCompleteField, index) => {
      // Add missing accessibility components to pairng a field and its combobox(option list).
      autoCompleteField.setAttribute("role", "combobox");
      autoCompleteField.setAttribute("aria-autocomplete", "none");
      autoCompleteField.setAttribute("aria-expanded", "false");

      console.log("list index: " + listIndex);

      autoCompleteField.addEventListener("change", e => {
        console.log("EVENT");
        console.log(index);
        console.log(this.index);
        console.log(e);
      });
    });

    optionLists.forEach((optionList, index) => {
      listIndex = index;
      // Add missing accessibility components to pairng a field and its combobox(option list).
      optionList.setAttribute("role", "listbox");
    });
  }, 1000);



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
