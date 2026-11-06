"use strict";

class JobRunner {
  jobs = {};
  onAlarm({
    name
  }) {
    this.jobs[name]?.();
  }
  addJob(name, func, periodInMinutes) {
    browser.alarms.get(name).then(alarm => {
      if (!alarm || alarm.periodInMinutes !== periodInMinutes) {
        browser.alarms.create(name, {
          periodInMinutes
        });
      }
    });
    this.jobs[name] = func;
  }
}
const jobRunner = new JobRunner();
function createAllJobs() {
  jobRunner.addJob('get-matched-rules', countMatchedRules, 1);
  jobRunner.addJob('reset-icon-badge', updateIcon, 10);
  jobRunner.addJob('cleanup-tabs', tab.cleanupTabs.bind(tab), 30);
  jobRunner.addJob('send-logs', serverLogger.prepareAndSend.bind(serverLogger), 60);
  jobRunner.addJob('report-bulk', analysisReporter.reportBulk.bind(analysisReporter), 60);
  jobRunner.addJob('heartbeat', scheduledHeartbeat, 6 * 60);
}