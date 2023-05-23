/**
 * @file chatbot-cohort-test.js
 */

function loadChatbot(labelsIncluded = [], cohortsIncluded = [], totalCohorts = 10) {
  if (localStorage.getItem('massgovChatbotCohort') === null) {
    localStorage.setItem('massgovChatbotCohort', Math.floor(Math.random() * totalCohorts) + 1);
  }
  const assignedCohort = parseInt(localStorage.getItem('massgovChatbotCohort'));
  const mgLabels = document.querySelector(`meta[name="mg_labels"]`);
  const labelsFound = mgLabels !== null ? mgLabels.getAttribute('content'): '';
  let labelMatches = [];
  labelsIncluded.forEach((label) => {
    if (labelsFound.includes(label)) {
      labelMatches.push(true);
    } else {
      labelMatches.push(false);
    }
  });
  const hasLabels = labelsIncluded.length > 0 && labelMatches.indexOf(true) != -1;
  const inCohort = cohortsIncluded.indexOf(assignedCohort) != -1;
  const successMessage = `CHATBOT LOADED\r\n\r\nAvailable cohorts are ${cohortsIncluded}. Your cohort is ${assignedCohort}.\r\n\r\nLabels included are ${labelsIncluded}. This page's labels are ${labelsFound}.\r\n\r\nChange your mds-chatbot-cohort local storage value to one not available and load a page that does not match all included labels to see failure message.`;
  const failureMessage = `NO CHATBOT\r\n\r\nAvailable cohorts are ${cohortsIncluded}. Your cohort is ${assignedCohort}.\r\n\r\nLabels included are ${labelsIncluded}. This page's labels are ${labelsFound}.\r\n\r\nChange your mds-chatbot-cohort local storage value to one available and load a page that matches all included labels to see success message.`;
  if (hasLabels && inCohort) {
    console.log(successMessage);
    return true;
  }
  console.log(failureMessage);
  return false;
}

async function getCohortTargets() {
  // @todo Compile JSON into the same file on build.
  const response = await fetch(window.location.origin + "/themes/custom/mass_theme/overrides/js/cohorts.json");
  const json = await response.json();

  return json;
}

window.addEventListener('load', (event) => {
  const alreadyLoadedMessage = 'CHATBOT ALREADY LOADED';
  let chatbotLoaded = false;
  getCohortTargets().then((targets) => {
    targets.every((target) => {
      if (chatbotLoaded == true) {
        console.log(alreadyLoadedMessage);
        return false;
      }
      chatbotLoaded = loadChatbot(target.labels, target.cohorts);
      return true;
    });
  });
});
