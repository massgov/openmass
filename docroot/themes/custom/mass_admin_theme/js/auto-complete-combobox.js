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

    optionLists.forEach((optionList) => {
      // Add missing accessibility components to pairng a field and its combobox(option list).
      optionList.setAttribute("role", "listbox");

      console.log("option list foreach");

      optionList.addEventListener("change", e => {
        var listId = optionList.getAttribute("id");
        if(e.target.style.display === "none") {

          console.log(e.target.style.display);

          document.querySelector("[aria-controls='${listId}']").setAttribute("aria-expanded", "false");
        } else {
          document.querySelector("[aria-controls='${listId}']").setAttribute("aria-expanded", "true");
        }
      });
    });

    autoCompleteFields.forEach((autoCompleteField, index) => {
      // Add missing accessibility components to pairng a field and its combobox(option list).
      autoCompleteField.setAttribute("role", "combobox");
      autoCompleteField.setAttribute("aria-autocomplete", "list");
      autoCompleteField.setAttribute("aria-expanded", "false");

      autoCompleteField.addEventListener("change", e => {
        if(optionLists[index].style.display === "none") {
          e.target.setAttribute("aria-expanded", "false");
        } else {
          e.target.setAttribute("aria-expanded", "true");
        }

        // Get ID of the UL.
        var listId = optionLists[index].getAttribute("id")
        // Add aria-controls with the UL ID value.
        // e.target.setAttribute("aria-controls", listId);
        e.target.setAttribute("aria-activedescendant", listId);

        setTimeout(function () {
          // Set role to LIs and their child As.
          optionLists[index].querySelectorAll(".ui-menu-item").forEach(item => {
            item.setAttribute("role", "none");
            item.querySelector(".ui-menu-item-wrapper").setAttribute("role", "option");
          });
        }, 100);
      });
    });
  }, 100);


  document.querySelectorAll("a").forEach(item => {
    item.addEventListener("click", (e) => {
      console.log(e.target);
    });
  });


  // document.querySelectorAll(".ui-menu-item-wrapper").forEach(option => {
  //   option.addEventListener("click", (e) => {

  //     console.log("option clicked");

  //     // Remove aria-selected from one currently has the attribute.
  //     optionLists.querySelectorAll(".ui-menu-item-wrapper").forEach(option => {
  //       if(option.hasAttribute("aria-selected")) {
  //         option.removeAttribute("aria-selected");
  //       }
  //     });
  //     // Set the item selected.
  //     e.target.setAttribute("aria-selected", true);
  //   });
  // });
})();
