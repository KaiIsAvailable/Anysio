"use strict";

function editInboundObject(propChain = '', argPosRaw = '', jsonq = '') {
  if (propChain === '') {
    return;
  }
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix(propChain, jsonq);
  const jsonp = JSONPath.create(jsonq);
  if (jsonp.valid === false || jsonp.value === undefined) {
    return safe.stndzLog(logPrefix, 'Bad JSONPath query');
  }
  const argPos = parseInt(argPosRaw, 10);
  if (isNaN(argPos)) {
    return;
  }
  const getArgPos = args => {
    if (Array.isArray(args) === false) {
      return;
    }
    if (argPos >= 0) {
      if (args.length <= argPos) {
        return;
      }
      return argPos;
    }
    if (args.length < -argPos) {
      return;
    }
    return args.length + argPos;
  };
  const editObj = obj => {
    let clone;
    try {
      clone = safe.JSON_parse(safe.JSON_stringify(obj));
    } catch {}
    if (typeof clone !== 'object' || clone === null) {
      return;
    }
    const objAfter = jsonp.apply(clone);
    if (objAfter === undefined) {
      return;
    }
    safe.stndzLog(logPrefix, 'Edited');
    if (safe.logLevel > 1) {
      safe.stndzLog(logPrefix, `After edit:\n${safe.JSON_stringify(objAfter, null, 2)}`);
    }
    return objAfter;
  };
  proxyApply(propChain, function (context) {
    const callArgs = context.callArgs || [];
    const i = getArgPos(callArgs);
    if (i !== undefined) {
      const obj = editObj(callArgs[i]);
      if (obj) {
        callArgs[i] = obj;
      }
    }
    return context.reflect();
  });
}