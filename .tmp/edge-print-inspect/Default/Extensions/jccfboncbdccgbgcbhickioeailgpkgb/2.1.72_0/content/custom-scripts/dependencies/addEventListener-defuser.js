"use strict";

function addEventListenerDefuser(type = '', pattern = '', ...args) {
  const safe = safeSelf();
  const extraArgs = safe.getExtraArgs(args, 0);
  const logPrefix = safe.makeLogPrefix('prevent-addEventListener', type, pattern);
  const reType = safe.patternToRegex(type, undefined, true);
  const rePattern = safe.patternToRegex(pattern);
  const targetSelector = extraArgs.elements || undefined;
  const elementMatches = elem => {
    if (targetSelector === 'window') {
      return elem === window;
    }
    if (targetSelector === 'document') {
      return elem === document;
    }
    if (elem && elem instanceof Element && elem.matches(targetSelector)) {
      return true;
    }
    const elems = Array.from(document.querySelectorAll(targetSelector));
    return elems.includes(elem);
  };
  const elementDetails = elem => {
    if (elem instanceof Window) {
      return 'window';
    }
    if (elem instanceof Document) {
      return 'document';
    }
    if (elem instanceof Element === false) {
      return '?';
    }
    const parts = [];
    const id = String(elem.id);
    if (id !== '') {
      parts.push(`#${CSS.escape(id)}`);
    }
    for (let i = 0; i < elem.classList.length; i++) {
      const className = elem.classList.item(i);
      if (className) {
        parts.push(`.${CSS.escape(className)}`);
      }
    }
    for (let i = 0; i < elem.attributes.length; i++) {
      const attr = elem.attributes.item(i);
      if (!attr) {
        continue;
      }
      if (attr.name === 'id') {
        continue;
      }
      if (attr.name === 'class') {
        continue;
      }
      parts.push(`[${CSS.escape(attr.name)}="${attr.value}"]`);
    }
    return parts.join('');
  };
  const shouldPrevent = (thisArg, eventType, handler) => {
    const matchesType = safe.RegExp_test.call(reType, eventType);
    const matchesHandler = safe.RegExp_test.call(rePattern, handler);
    const matchesBoth = matchesType && matchesHandler;
    if (matchesBoth && targetSelector !== undefined) {
      if (elementMatches(thisArg) === false) {
        return false;
      }
    }
    return matchesBoth;
  };
  const proxyFn = function (context) {
    const {
      callArgs,
      thisArg
    } = context;
    if (!callArgs) {
      return context.reflect();
    }
    let t = '',
      h = '';
    try {
      t = String(callArgs[0]);
      if (typeof callArgs[1] === 'function') {
        h = String(safe.Function_toString(callArgs[1]));
      } else if (typeof callArgs[1] === 'object' && callArgs[1] !== null) {
        if (typeof callArgs[1].handleEvent === 'function') {
          h = String(safe.Function_toString(callArgs[1].handleEvent));
        }
      } else {
        h = String(callArgs[1]);
      }
    } catch {}
    if (type === '' && pattern === '') {
      safe.stndzLog(logPrefix, `Called: ${t}\n${h}\n${elementDetails(thisArg)}`);
    } else if (shouldPrevent(thisArg, t, h)) {
      return safe.stndzLog(logPrefix, `Prevented: ${t}\n${h}\n${elementDetails(thisArg)}`);
    }
    return context.reflect();
  };
  scheduleExecution(() => {
    proxyApply('EventTarget.prototype.addEventListener', proxyFn);
    proxyApply('document.addEventListener', proxyFn);
  }, extraArgs.runAt);
}