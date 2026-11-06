"use strict";

function jsonEditFetchRequest(jsonq = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('json-edit-fetch-request', jsonq);
  const jsonp = JSONPath.create(jsonq);
  if (!jsonp.valid || jsonp.value === undefined) {
    return safe.stndzLog(logPrefix, 'Bad JSONPath query');
  }
  const extraArgs = safe.getExtraArgs(Array.from(args), 0);
  const propNeedles = createPropertyMatchMap(extraArgs.propsToMatch, 'url');
  const filterBody = body => {
    if (typeof body !== 'string') {
      return;
    }
    let data;
    try {
      data = safe.JSON_parse(body);
    } catch {}
    if (!(data instanceof Object)) {
      return;
    }
    const objAfter = jsonp.apply(data);
    if (objAfter === undefined) {
      return;
    }
    return safe.JSON_stringify(objAfter);
  };
  const proxyHandler = context => {
    const callArgs = context.callArgs || [];
    const [resource, options] = callArgs;
    const bodyBefore = options?.body;
    if (!bodyBefore) {
      return context.reflect();
    }
    const bodyAfter = filterBody(bodyBefore);
    if (bodyAfter === undefined || bodyAfter === bodyBefore) {
      return context.reflect();
    }
    if (propNeedles.size !== 0) {
      const props = collateFetchArguments(resource, options);
      const matched = matchObjectProperties(propNeedles, props);
      if (matched === undefined) {
        return context.reflect();
      }
      if (safe.logLevel > 1) {
        safe.stndzLog(logPrefix, `Matched "propsToMatch":\n\t${matched.join('\n\t')}`);
      }
    }
    safe.stndzLog(logPrefix, 'Edited');
    if (safe.logLevel > 1) {
      safe.stndzLog(logPrefix, `After edit:\n${bodyAfter}`);
    }
    options.body = bodyAfter;
    return context.reflect();
  };
  proxyApply('fetch', proxyHandler);
  proxyApply('Request', proxyHandler);
}