"use strict";

function replaceXhrResponseContent(pattern = '', replacement = '', propsToMatch = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('trusted-replace-xhr-response', pattern, replacement, propsToMatch);
  const xhrInstances = new WeakMap();
  if (pattern === '*') {
    pattern = '.*';
  }
  const rePattern = safe.patternToRegex(pattern);
  const propNeedles = createPropertyMatchMap(propsToMatch, 'url');
  const extraArgs = safe.getExtraArgs([pattern, replacement, propsToMatch, ...args], 3);
  const reIncludes = extraArgs.includes ? safe.patternToRegex(extraArgs.includes) : null;
  self.XMLHttpRequest = class extends self.XMLHttpRequest {
    open(method, url, ...openArgs) {
      const xhrDetails = {
        method,
        url
      };
      let outcome = 'match';
      if (propNeedles.size !== 0) {
        if (matchObjectProperties(propNeedles, xhrDetails) === undefined) {
          outcome = 'nomatch';
        }
      }
      if (outcome === 'match') {
        if (safe.logLevel > 1) {
          safe.stndzLog(logPrefix, 'Matched "propsToMatch"');
        }
        xhrInstances.set(this, xhrDetails);
      }
      return super.open(method, url, ...openArgs);
    }
    get response() {
      const innerResponse = super.response;
      const xhrDetails = xhrInstances.get(this);
      if (xhrDetails === undefined) {
        return innerResponse;
      }
      const responseLength = typeof innerResponse === 'string' ? innerResponse.length : undefined;
      if (xhrDetails.lastResponseLength !== responseLength) {
        xhrDetails.response = undefined;
        xhrDetails.lastResponseLength = responseLength;
      }
      if (xhrDetails.response !== undefined) {
        return xhrDetails.response;
      }
      if (typeof innerResponse !== 'string') {
        return xhrDetails.response = innerResponse;
      }
      if (reIncludes && reIncludes.test(innerResponse) === false) {
        return xhrDetails.response = innerResponse;
      }
      const textBefore = innerResponse;
      const textAfter = textBefore.replace(rePattern, replacement);
      if (textAfter !== textBefore) {
        safe.stndzLog(logPrefix, 'Match');
      }
      return xhrDetails.response = textAfter;
    }
    get responseText() {
      const response = this.response;
      if (typeof response !== 'string') {
        return super.responseText;
      }
      return response;
    }
  };
}