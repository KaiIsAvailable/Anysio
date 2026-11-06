"use strict";

function jsonPruneFetchResponse(rawPrunePaths = '', rawNeedlePaths = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('json-prune-fetch-response', rawPrunePaths, rawNeedlePaths);
  const extraArgs = safe.getExtraArgs([rawPrunePaths, rawNeedlePaths, ...args], 2);
  const propNeedles = createPropertyMatchMap(extraArgs.propsToMatch, 'url');
  const stackNeedle = safe.initPattern(extraArgs.stackToMatch || '', {
    canNegate: true
  });
  self.fetch = new Proxy(self.fetch, {
    apply: function (target, thisArg, fetchArgs) {
      const fetchPromise = Reflect.apply(target, thisArg, fetchArgs);
      if (propNeedles.size !== 0) {
        const props = collateFetchArguments(fetchArgs[0], fetchArgs[1]);
        const matched = matchObjectProperties(propNeedles, props);
        if (matched === undefined) {
          return fetchPromise;
        }
        if (safe.logLevel > 1) {
          safe.stndzLog(logPrefix, `Matched "propsToMatch":\n\t${matched.join('\n\t')}`);
        }
      }
      return fetchPromise.then(responseBefore => {
        const response = responseBefore.clone();
        return response.json().then(objBefore => {
          if (typeof objBefore !== 'object') {
            return responseBefore;
          }
          if (rawPrunePaths === '') {
            safe.stndzLog(logPrefix, safe.JSON_stringify(objBefore, null, 2));
            return responseBefore;
          }
          const objAfter = pruneObject(objBefore, rawPrunePaths, rawNeedlePaths, stackNeedle, extraArgs);
          if (typeof objAfter !== 'object') {
            return responseBefore;
          }
          safe.stndzLog(logPrefix, 'Pruned');
          const responseAfter = Response.json(objAfter, {
            status: responseBefore.status,
            statusText: responseBefore.statusText,
            headers: responseBefore.headers
          });
          Object.defineProperties(responseAfter, {
            ok: {
              value: responseBefore.ok
            },
            redirected: {
              value: responseBefore.redirected
            },
            type: {
              value: responseBefore.type
            },
            url: {
              value: responseBefore.url
            }
          });
          return responseAfter;
        }).catch(reason => {
          safe.stndzErr(logPrefix, 'Error:', reason);
          return responseBefore;
        });
      }).catch(reason => {
        safe.stndzErr(logPrefix, 'Error:', reason);
        return fetchPromise;
      });
    }
  });
}