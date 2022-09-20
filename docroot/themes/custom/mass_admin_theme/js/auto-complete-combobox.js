/**
 * @file
 * Add missing combobox association between auto complete fields and
 * their option boxes with aria.
 *
 * This addresses the accessibility issue with JAWS:  DP-25848
 */
let autoCompleteFields = document.querySelectorAll(".ui-autocomplete-input");
let optionLists = document.querySelectorAll(".ui-autocomplete");

// Add missing accessibility components to pairng a field and its combobox(option list).
autoCompleteFields.setAttribute("role", "combobox");
autoCompleteFields.setAttribute("aria-autocomplete", "none");
autoCompleteFields.setAttribute("aria-expanded", "false");

optionLists.attr("role", "listbox");


// autoCompleteFields.addEventListener("keyPress", () => {

// });

autoCompleteFields.forEach(autoCompleteField => {
  autoCompleteField.addEventListener("keyPress", (e) => {
    console.log(e.target);
  });
});
// autoCompleteFields.findIndex();


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

