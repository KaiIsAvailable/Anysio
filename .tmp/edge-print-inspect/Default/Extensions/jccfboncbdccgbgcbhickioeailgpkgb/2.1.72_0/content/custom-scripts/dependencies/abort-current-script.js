"use strict";

function abortCurrentScriptCore(target = '', needle = '', context = '') {
  if (typeof target !== 'string') {
    return;
  }
  if (target === '') {
    return;
  }
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('abort-current-script', target, needle, context);
  const reNeedle = safe.patternToRegex(needle);
  const reContext = safe.patternToRegex(context);
  const thisScript = document.currentScript;
  const chain = safe.String_split.call(target, '.');
  let owner = window;
  let prop;
  for (;;) {
    prop = chain.shift();
    if (chain.length === 0) {
      break;
    }
    if (!prop || prop in owner === false) {
      break;
    }
    owner = owner[prop];
    if (owner instanceof Object === false) {
      return;
    }
  }
  if (!prop) {
    return;
  }
  let value;
  let desc = Object.getOwnPropertyDescriptor(owner, prop);
  if (desc instanceof Object === false || desc.get instanceof Function === false) {
    value = owner[prop];
    desc = undefined;
  }
  const exceptionToken = generateExceptionToken();
  const scriptTexts = new WeakMap();
  const getScriptText = elem => {
    let text = elem.textContent;
    if (text && text.trim() !== '') {
      return text;
    }
    if (scriptTexts.has(elem)) {
      return scriptTexts.get(elem) || '';
    }
    const [, mime, content] = /^data:([^,]*),(.+)$/.exec(elem.src.trim()) || ['', '', ''];
    try {
      switch (true) {
        case mime.endsWith(';base64'):
          text = self.atob(content);
          break;
        default:
          text = self.decodeURIComponent(content);
          break;
      }
    } catch {}
    scriptTexts.set(elem, text || '');
    return text || '';
  };
  const validate = () => {
    const e = document.currentScript;
    if (e instanceof HTMLScriptElement === false) {
      return;
    }
    if (e === thisScript) {
      return;
    }
    if (context !== '' && reContext.test(e.src) === false) {
      return;
    }
    if (safe.logLevel > 1 && context !== '') {
      safe.stndzLog(logPrefix, `Matched src\n${e.src}`);
    }
    const scriptText = getScriptText(e);
    if (reNeedle.test(scriptText) === false) {
      return;
    }
    if (safe.logLevel > 1) {
      safe.stndzLog(logPrefix, `Matched text\n${scriptText}`);
    }
    safe.stndzLog(logPrefix, 'Aborted');
    throw new ReferenceError(exceptionToken);
  };
  try {
    Object.defineProperty(owner, prop, {
      get: function () {
        validate();
        return desc instanceof Object && desc.get ? desc.get.call(owner) : value;
      },
      set: function (a) {
        validate();
        if (desc instanceof Object && desc.set) {
          desc.set.call(owner, a);
        } else {
          value = a;
        }
      }
    });
  } catch (ex) {
    safe.stndzErr(logPrefix, `Error: ${ex}`);
  }
}
function runAtHtmlElementFn(fn) {
  if (document.documentElement) {
    fn();
    return;
  }
  const observer = new MutationObserver(() => {
    observer.disconnect();
    fn();
  });
  observer.observe(document, {
    childList: true
  });
}
function abortCurrentScript(...args) {
  runAtHtmlElementFn(() => {
    abortCurrentScriptCore(...args);
  });
}