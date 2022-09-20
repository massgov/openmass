/**
 * @file
 * Add missing combobox association between auto complete fields and
 * their option boxes with aria.
 *
 * This addresses the accessibility issue with JAWS:  DP-25848
 */
(function () {
  'use strict';

  // The timeout function is necessary to recognize the fields and the lists.
  setTimeout(function () {
    var autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
    var optionLists = document.querySelectorAll(".ui-autocomplete");
    var listIndex;

    optionLists.forEach((optionList, index) => {
      listIndex = index;
      // Add missing accessibility components to pairng a field and its combobox(option list).
      optionList.setAttribute("role", "listbox");
    });

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
        console.log(e.target);
        // Find the matching index UL.

        // Check the UL has display: none;
        // if() {
        //   this.setAttribute("aria-expanded", "false");
        // } else {
        //   this.setAttribute("aria-expanded", "true");
        // }
        // If no, change aria-expanded value to true.

        // Get ID of the UL.

        // Add aria-controls with the UL ID value.
        // this.setAttribute("aria-controls", "ID");

        // Set role to LIs and their child As.
        // optionLists[XX].querySelectorAll("li").setAttribute("role", "none");
        // optionLists[XX].querySelectorAll("li a").setAttribute("role", "option");
      });
    });
  }, 1000);
})();
