"use strict";

const heartbeat = async reason => {
  const user = await userData.getData();
  if (!user || !user.settings) {
    return;
  }
  const currentSettings = await userData.getSettings();
  const currentTime = Date.now();
  if (!currentSettings.lastHeartbeat) {
    await userData.updateData({
      settings: {
        ...currentSettings,
        lastHeartbeat: 0
      }
    });
  }
  if (currentTime - currentSettings.lastHeartbeat < 5 * 59 * 60 * 1000) {
    return;
  }
  const {
    error
  } = await serverApi.callUrl({
    url: API_URLS.heartbeat,
    method: 'PUT',
    data: {
      appVersion: getAppVersion(),
      anonymousUserId: await loadAnonyId(),
      extensionId: getExtensionId(),
      privateUserId: user.privateUserId,
      reason
    }
  });
  if (error) {
    debug.error(`Error sending heartbeat: ${error}`);
    return;
  }
  await userData.updateData({
    settings: {
      ...currentSettings,
      lastHeartbeat: currentTime
    }
  });
};
const scheduledHeartbeat = async () => {
  await heartbeat('onSchedule');
};