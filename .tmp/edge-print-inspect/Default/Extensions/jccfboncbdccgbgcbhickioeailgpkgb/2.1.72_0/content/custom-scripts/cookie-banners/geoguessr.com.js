"use strict";

{
  const fn = debounce(() => {
    document.querySelector('body > div:has([id=accept-choices])')?.remove();
    document.querySelector('div:has(> main) > aside')?.remove();
  }, 100);
  fn();
  new MutationObserver(fn).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
}