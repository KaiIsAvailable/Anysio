"use strict";

function jsonEditXhrRequest(jsonq = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('json-edit-xhr-request', jsonq);
  const xhrInstances = new WeakMap();
  const jsonp = JSONPath.create(jsonq);
  if (!jsonp.valid || jsonp.value === undefined) {
    return safe.stndzLog(logPrefix, 'Bad JSONPath query');
  }
  const extraArgs = safe.getExtraArgs([jsonq, ...args], 2);
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
    send(body) {
      const xhrDetails = xhrInstances.get(this);
      if (xhrDetails) {
        body = this.#filterBody(body) || body;
      }
      super.send(body);
    }
    #filterBody(body) {
      if (typeof body !== 'string') {
        return;
      }
      let data;
      try {
        data = safe.JSON_parse(body);
      } catch {}
      if (data instanceof Object === false) {
        return;
      }
      const objAfter = jsonp.apply(data);
      if (objAfter === undefined) {
        return;
      }
      body = safe.JSON_stringify(objAfter);
      safe.stndzLog(logPrefix, 'Edited');
      if (safe.logLevel > 1) {
        safe.stndzLog(logPrefix, `After edit:\n${body}`);
      }
      return body;
    }
  };
}