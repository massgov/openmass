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

          autoCompleteFields[index].setAttribute("aria-expanded", "true");
        }, 200);


        // Mark selected item.
        let activeValue = e.target.value;
        optionLists[index].querySelectorAll(".ui-menu-item .ui-menu-item-wrapper").forEach(item => {
          console.log(activeValue);
          console.log(item.innerHTML);
          if (item.value === activeValue) {
              item.setAttribute("aria-selected", true);
          } else {
            item.removeAttribute("aria-selected");
          }
        });


      });
    });
  }, 200);

  // List box display status for aria.
  // let activeField = document.activeElement;
  // let matchedListId = activeField.getAttribute("aria-activedescendant");
  // let matchedList =  document.getElementById(matchedListId);

  // let observer = new MutationObserver(function(mutations) {
  //   mutations.forEach(function(mutationRecord) {
  //     console.log('style changed!');
  //   });
  // });

  // let target = matchedList;
  // // var target = document.getElementById('myId');

  // observer.observe(target, {
  //   attributes: true,
  //   attributeFilter: ['style']
  // });


  ////////////////



  // document.querySelectorAll(".ui-menu-item-wrapper").forEach(option => {

    // option.addEventListener("change", (e) => {




      // let pId = e.target.closest(".ui-autocomplete").getAttribute("id");
      // // document.querySelector("[aria-controls='${pId}']").style.backgroundColor = "pink";
      // document.querySelector("[aria-activedescendant='${pId}']").style.backgroundColor = "pink";
      // console.log("e.target");

  //     // Remove aria-selected from one currently has the attribute.
  //     optionLists.querySelectorAll(".ui-menu-item-wrapper").forEach(option => {
  //       if(option.hasAttribute("aria-selected")) {
  //         option.removeAttribute("aria-selected");
  //       }
  //     });
  //     // Set the item selected.
  //     e.target.setAttribute("aria-selected", true);
    // });
  // });
})();
