"use strict";
function jsonlEditXhrResponse(jsonq = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('jsonl-edit-xhr-response', jsonq);
  const xhrInstances = new WeakMap();
  const jsonp = JSONPath.create(jsonq);
  if (!jsonp.valid || jsonp.value !== undefined) {
    return safe.stndzLog(logPrefix, 'Bad JSONPath query');
  }
  const extraArgs = safe.getExtraArgs(args, 1);
  const propNeedles = createPropertyMatchMap(extraArgs.propsToMatch, 'url');
  self.XMLHttpRequest = class extends self.XMLHttpRequest {
    open(method, url, ...openArgs) {
      const xhrDetails = {
        method,
        url
      };
      const matched = propNeedles.size === 0 || matchObjectProperties(propNeedles, xhrDetails);
      if (matched) {
        if (safe.logLevel > 1 && Array.isArray(matched)) {
          safe.stndzLog(logPrefix, `Matched "propsToMatch":\n\t${matched.join('\n\t')}`);
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
      const outerResponse = jsonlEdit(jsonp, innerResponse);
      if (outerResponse !== innerResponse) {
        safe.stndzLog(logPrefix, 'Pruned');
      }
      return xhrDetails.response = outerResponse;
    }
    get responseText() {
      const response = this.response;
      return typeof response !== 'string' ? super.responseText : response;
    }
  };
}