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
    const instructionForAll = document.querySelector("#block-mass-admin-theme-mainpagecontent .view-header");
    const instructionForSrContent = document.createTextNode("Use tab key to navigate in the main content area.");

    const instructionForCombobox = document.createTag("p");
    const instructionForComboboxContent = document.createTextNode("Use enter key to select an option.");

    let autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
    let optionLists = document.querySelectorAll(".ui-autocomplete");

    // Add instructions for screen reader users.
    if (instructionForAll.innerHTML.includes("Search for content using any of the filters below.")) {
      const instructionForSr = document.createTag("p").className = "visually-hidden";
      instructionForSr.appendChild(instructionForSrContent);
      instructionForAll.appendChild(instructionForSr);
    }

    // Set up aria-describedby content for auto complete field option box.
    instructionForCombobox.setAttribute("id", "comboboxInfo");
    instructionForCombobox.setAttribute("style", "display: none;");
    instructionForCombobox.setAttribute("aria-hidden", "true");
    instructionForCombobox.appendChild(instructionForComboboxContent);
    document.querySelector("main.page-content").appendChild(instructionForCombobox);



    optionLists.forEach(optionList => {
      optionList.setAttribute("role", "listbox");
      optionList.setAttribute("aria-describedby", "comboboxInfo");
    });

    autoCompleteFields.forEach((autoCompleteField, index) => {
      // Add missing accessibility components to pair a field and its combobox(option list).
      autoCompleteField.setAttribute("role", "combobox");
      autoCompleteField.setAttribute("aria-autocomplete", "list");
      // autoCompleteField.setAttribute("aria-expanded", "false");

      // Get ID of the UL.
      let listId = optionLists[index].getAttribute("id");
      // Add aria-controls with the UL ID value.
      // aria-controls doesn't work with VoiceOver.
      autoCompleteField.setAttribute("aria-activedescendant", listId);

      autoCompleteField.addEventListener("change", e => {
        // console.log("CHANGE");
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
