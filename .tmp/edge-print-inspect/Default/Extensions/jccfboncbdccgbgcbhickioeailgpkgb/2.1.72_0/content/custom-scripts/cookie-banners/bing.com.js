"use strict";

{
  const fn = debounce(() => {
    document.querySelector('#bnp_cookie_banner')?.remove();
    document.querySelector('#cookie_preference')?.remove();
    if (document.documentElement.style.getPropertyValue('overflow') === 'hidden') {
      document.documentElement.style.setProperty('overflow', 'auto', 'important');
    }
  }, 100);
  fn();
  new MutationObserver(fn).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
}