var metrics = require('lastcall-nightcrawler/dist/metrics');

function responseTime(responses, label, max) {
  // Base our averages on only responses that have a backendTime property.
  var responsesWithTime = responses.filter(function(response) {
    return response.hasOwnProperty('time');
  });
  var totalTime = responsesWithTime.reduce(function(sum, response) {
    return sum + response.time;
  }, 0);
  var avgTime = responsesWithTime.length > 0
    ? totalTime / responsesWithTime.length
    : 0;
  var level = avgTime <= max ? 0 : 2;
  return new metrics.Milliseconds(label, level, avgTime);
}

function serverErrors(responses, label, max) {
  var failed = responses.filter(function(response) {
    return response.statusCode > 499;
  });
  var level = failed >= max ? 0 : 2;

  return new metrics.Number(label, level, failed.length);
}


module.exports = {
  responseTime: responseTime,
  serverErrors: serverErrors
}
