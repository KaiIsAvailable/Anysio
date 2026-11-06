"use strict";

async function toggleSiteInList(hosts, list, sendMessageToPopup = false) {
  if (list === 'cookieBanners') {
    await Promise.all(hosts.map(async host => {
      await cookieBannersAllowedSites.toggle(host);
      const inList = await cookieBannersAllowedSites.isAllowed(host);
      serverLogger.logWhitelistChange(host, 'cookie_banners', !!inList);
    }));
  }
  if (list === 'deactivatedSites') {
    hosts.map(async host => {
      const inWhiteList = await deactivatedSites.isHostDeactivated(host);
      await Promise.all([popupShowNotificationList.removeValueByHost(host), popupAllowedSites.toggle(host, !inWhiteList), deactivatedSites.toggle(host)]);
      serverLogger.logWhitelistChange(host, 'ads', !!inWhiteList);
    });
  }
  if (sendMessageToPopup) {
    await sendMessage({
      type: MESSAGE_TYPES.toggleSiteInListResponse,
      payload: {
        forStandsPopup: true
      }
    });
  }
  const tabs = await queryTabs();
  const tabIds = tabs.filter(({
    url
  }) => hosts.includes(getUrlHost(url || ''))).map(({
    id
  }) => id).filter(id => typeof id === 'number');
  await pageDataComponent.refreshBulk(tabIds);
  await Promise.all([updateIcon(), contextMenus.update()]);
  tabIds.forEach(reloadTab);
}