/**
 * @file chatbot-cohort-test.js
 */

function loadChatbot(label = '', cohortsIncluded = [], totalCohorts = 10) {
  if (localStorage.getItem('mds-chatbot-cohort') === null) {
    localStorage.setItem('mds-chatbot-cohort', Math.floor(Math.random() * totalCohorts) + 1);
  }
  const assignedCohort = parseInt(localStorage.getItem('mds-chatbot-cohort'));
  const hasLabel = document.querySelector(`meta[name="mg_labels"][content*=${label}]`);
  if (hasLabel !== "undefined" && cohortsIncluded.indexOf(assignedCohort) != -1) {
    console.log(`CHATBOT LOADED\r\n\r\nAvailable cohorts are ${cohortsIncluded}. Your cohort is ${assignedCohort}.\r\n\r\nChange your mds-chatbot-cohort local storage value to one not available to see failure message.`);
  } else {
    console.log(`NO CHATBOT\r\n\r\nAvailable cohorts are ${cohortsIncluded}. Your cohort is ${assignedCohort}.\r\n\r\nChange your mds-chatbot-cohort local storage value to one available to see success message.`);
  }
}

window.addEventListener("load", (event) => {
  loadChatbot('massgovchatbot', [1,9,3,8]);
});
