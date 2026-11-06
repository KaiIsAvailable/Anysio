"use strict";

{
  const fn = debounce(() => {
    const rejectBtn = document.querySelector('#W0wltc, form[action] button[jsname="tWT92d"]');
    if (rejectBtn) {
      rejectBtn.click();
    } else {
      document.querySelector('#xe7COe.HTjtHe, div.HTjtHe[role="dialog"]')?.remove();
    }
    document.body?.classList.remove('EM1Mrb');
  }, 100);
  fn();
  new MutationObserver(fn).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
}