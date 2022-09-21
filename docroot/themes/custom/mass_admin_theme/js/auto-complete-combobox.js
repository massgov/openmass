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
    // var listIndex;

    optionLists.forEach((optionList) => {
      // listIndex = index;
      // Add missing accessibility components to pairng a field and its combobox(option list).
      optionList.setAttribute("role", "listbox");
    });

    autoCompleteFields.forEach((autoCompleteField, index) => {
      // Add missing accessibility components to pairng a field and its combobox(option list).
      autoCompleteField.setAttribute("role", "combobox");
      autoCompleteField.setAttribute("aria-autocomplete", "none");
      autoCompleteField.setAttribute("aria-expanded", "false");

      autoCompleteField.addEventListener("change", e => {
        console.log("EVENT");
        console.log(index);
        // console.log(e.target.getAttribute("aria-expanded"));
        // Find the matching index UL.
        console.log(optionLists[index]);
        // Check the UL has display: none;
        if(optionLists[index].style.display) {
          e.target.setAttribute("aria-expanded", "false");
        } else {
          e.target.setAttribute("aria-expanded", "true");
        }

        // Get ID of the UL.
        var listId = optionLists[index].getAttribute("id")
        // Add aria-controls with the UL ID value.
        e.target.setAttribute("aria-controls", listId);

        // Set role to LIs and their child As.
        optionLists[index].querySelectorAll(".ui-menu-item").setAttribute("role", "none");
        optionLists[index].querySelectorAll(".ui-menu-item .ui-menu-item-wrapper").setAttribute("role", "option");
      });
    });
  }, 500);
})();
