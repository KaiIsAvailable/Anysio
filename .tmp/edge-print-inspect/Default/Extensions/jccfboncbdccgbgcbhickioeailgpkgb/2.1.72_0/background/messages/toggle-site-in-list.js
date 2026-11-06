"use strict";

async function actionInCaseToggleSiteInList({
  payload
}) {
  await toggleSiteInList(payload.hosts, payload.list, true);
}