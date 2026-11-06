"use strict";

function jsonPruneXhrResponse(rawPrunePaths = '', rawNeedlePaths = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('json-prune-xhr-response', rawPrunePaths, rawNeedlePaths);
  const xhrInstances = new WeakMap();
  const extraArgs = safe.getExtraArgs([rawPrunePaths, rawNeedlePaths, ...args], 2);
  const propNeedles = createPropertyMatchMap(extraArgs.propsToMatch, 'url');
  const stackNeedle = safe.initPattern(extraArgs.stackToMatch || '', {
    canNegate: true
  });
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
          safe.stndzLog(logPrefix, `Matched optional "propsToMatch", "${extraArgs.propsToMatch}"`);
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
      let objBefore;
      if (typeof innerResponse === 'object') {
        objBefore = innerResponse;
      } else if (typeof innerResponse === 'string') {
        try {
          objBefore = safe.JSON_parse(innerResponse);
        } catch {}
      }
      if (typeof objBefore !== 'object') {
        return xhrDetails.response = innerResponse;
      }
      const objAfter = pruneObject(objBefore, rawPrunePaths, rawNeedlePaths, stackNeedle, extraArgs);
      let outerResponse;
      if (typeof objAfter === 'object') {
        outerResponse = typeof innerResponse === 'string' ? safe.JSON_stringify(objAfter) : objAfter;
        safe.stndzLog(logPrefix, 'Pruned');
      } else {
        outerResponse = innerResponse;
      }
      return xhrDetails.response = outerResponse;
    }
    get responseText() {
      const response = this.response;
      return typeof response !== 'string' ? super.responseText : response;
    }
  };
}