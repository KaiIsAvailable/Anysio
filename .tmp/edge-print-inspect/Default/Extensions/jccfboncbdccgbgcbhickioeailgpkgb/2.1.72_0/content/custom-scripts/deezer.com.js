"use strict";

new MutationObserver(() => {
  const cookie_button = document.querySelector('#gdpr-btn-refuse-all');
  cookie_button?.click();
  document.querySelectorAll('style[id^="stndz-general"]').forEach(s => {
    s.remove();
  });
}).observe(document.documentElement, {
  childList: true,
  subtree: true
});
unblockContentScrollingScript('#fb-root');