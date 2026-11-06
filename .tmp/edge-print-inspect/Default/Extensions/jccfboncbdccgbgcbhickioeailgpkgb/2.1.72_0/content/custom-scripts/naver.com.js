"use strict";

(function () {
  let stored;
  Object.defineProperty(window, 'imageSearchTabData', {
    configurable: true,
    get() {
      return stored;
    },
    set(value) {
      if (value && typeof value === 'object') {
        if (value.shopAd && Array.isArray(value.shopAd.items)) {
          value.shopAd.items = [];
        }
        if (value.content && Array.isArray(value.content.items)) {
          value.content.items = value.content.items.filter(it => it && it.type !== 'shopAd');
        }
      }
      stored = value;
    }
  });
})();
jsonlEditXhrResponse('.shopAd.items.*', 'propsToMatch', '/p/c/image/');
jsonlEditXhrResponse('.content.items[?@.type=="shopAd"]', 'propsToMatch', '/p/c/image/');