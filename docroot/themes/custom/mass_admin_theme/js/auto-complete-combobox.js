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
    var instructionForAll = document.querySelector("#block-mass-admin-theme-mainpagecontent .view-header") ? document.querySelector("#block-mass-admin-theme-mainpagecontent .view-header") : null;
    var instructionForSrContent = document.createTextNode("Use tab key to navigate.");

    var instructionForCombobox = document.createElement("p");
    var instructionForComboboxContent = document.createTextNode("Use enter key to select an option from the list.");

    var autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
    var optionLists = document.querySelectorAll(".ui-autocomplete");

    // Add instructions for screen reader users.
    if (instructionForAll && instructionForAll.innerHTML.includes("Search for content using any of the filters below.")) {
      var instructionForSr = document.createElement("p");
      instructionForSr.classList.add("visually-hidden");
      instructionForSr.appendChild(instructionForSrContent);
      instructionForAll.appendChild(instructionForSr);
    }

    // Set up aria-describedby content for auto complete field option box.
    instructionForCombobox.setAttribute("id", "comboboxInfo");
    instructionForCombobox.setAttribute("style", "display: none;");
    instructionForCombobox.setAttribute("aria-hidden", "true");
    instructionForCombobox.appendChild(instructionForComboboxContent);
    document.querySelector("main.page-content").appendChild(instructionForCombobox);



    optionLists.forEach((optionList) => {
      optionList.setAttribute("role", "listbox");
    });

    autoCompleteFields.forEach((autoCompleteField, index) => {
      // Add missing accessibility components to pair a field and its combobox(option list).
      autoCompleteField.setAttribute("role", "combobox");
      autoCompleteField.setAttribute("aria-autocomplete", "list");
      // Currently the listbox status is not used.
      // autoCompleteField.setAttribute("aria-expanded", "false");
      autoCompleteField.setAttribute("aria-describedby", "comboboxInfo");

      // Get ID of the UL.
      var listId = optionLists[index].getAttribute("id");
      // Add aria-controls with the UL ID value.
      // aria-controls doesn't work with VoiceOver.
      autoCompleteField.setAttribute("aria-activedescendant", listId);

      autoCompleteField.addEventListener("change", e => {
        // Wait till the options are added to the list container .ui-autocomplete.
        setTimeout(function () {
          // Set role to LIs and their child As.
          optionLists[index].querySelectorAll(".ui-menu-item").forEach(item => {
            item.setAttribute("role", "none");
            item.querySelector(".ui-menu-item-wrapper").setAttribute("role", "option");
          });
        }, 200);
      });
    });
  }, 200);
})();
