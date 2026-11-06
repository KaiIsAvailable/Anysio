"use strict";

function preventDomBypass(methodPath = '', targetProp = '') {
  if (methodPath === '') {
    return;
  }
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('prevent-dom-bypass', methodPath, targetProp);
  proxyApply(methodPath, function (context) {
    const elems = new Set(context.callArgs.filter(e => e instanceof HTMLElement));
    const r = context.reflect();
    if (elems.size === 0) {
      return r;
    }
    for (const elem of elems) {
      try {
        if (`${elem.contentWindow}` !== '[object Window]') {
          continue;
        }
        if (elem.contentWindow.location.href !== 'about:blank') {
          if (elem.contentWindow.location.href !== self.location.href) {
            continue;
          }
        }
        if (targetProp !== '') {
          let me = self;
          let it = elem.contentWindow;
          let chain = targetProp;
          for (;;) {
            const pos = chain.indexOf('.');
            if (pos === -1) {
              break;
            }
            const prop = chain.slice(0, pos);
            me = me[prop];
            it = it[prop];
            chain = chain.slice(pos + 1);
          }
          it[chain] = me[chain];
        } else {
          Object.defineProperty(elem, 'contentWindow', {
            value: self
          });
        }
        safe.stndzLog(logPrefix, 'Bypass prevented');
      } catch {}
    }
    return r;
  });
}