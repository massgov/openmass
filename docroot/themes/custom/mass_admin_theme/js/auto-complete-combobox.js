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
    let autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
    let optionLists = document.querySelectorAll(".ui-autocomplete");

    optionLists.forEach(optionList => {
      optionList.setAttribute("role", "listbox");
    });

    autoCompleteFields.forEach((autoCompleteField, index) => {
      // Add missing accessibility components to pairng a field and its combobox(option list).
      autoCompleteField.setAttribute("role", "combobox");
      autoCompleteField.setAttribute("aria-autocomplete", "list");
      autoCompleteField.setAttribute("aria-expanded", "false");

      // Get ID of the UL.
      let listId = optionLists[index].getAttribute("id");
      // Add aria-controls with the UL ID value.
      // e.target.setAttribute("aria-controls", listId);
      autoCompleteField.setAttribute("aria-activedescendant", listId);

      autoCompleteField.addEventListener("keyUp", e => {

        console.log("keyup");
        console.log(optionLists[index]);

        if(!optionLists[index].innerHTML.trim()) {
          e.target.setAttribute("aria-expanded", "false");
        } else {
          e.target.setAttribute("aria-expanded", "true");
          console.log("hippo");
        }


        // if(optionLists[index].style.display === "none") {
        //   e.target.setAttribute("aria-expanded", "false");
        // } else {
        //   e.target.setAttribute("aria-expanded", "true");
        // }

        // Wait till the options are added to the list container .ui-autocomplete.
        setTimeout(function () {
          // Set role to LIs and their child As.
          optionLists[index].querySelectorAll(".ui-menu-item").forEach(item => {
            item.setAttribute("role", "none");
            item.querySelector(".ui-menu-item-wrapper").setAttribute("role", "option");
          });
          autoCompleteFields[index].setAttribute("aria-expanded", "true");
          console.log("panda");
        }, 100);
      });
    });
  }, 100);

  // After the initial rendering
  // if (document.querySelectorAll(".ui-autocomplete-input")) {
  //   var autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
  //   var optionLists = document.querySelectorAll(".ui-autocomplete");


  // }





  // document.querySelectorAll("a").forEach(item => {
  //   item.addEventListener("click", (e) => {
  //     console.log(e.target);
  //   });
  // });


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
