"use strict";

function replaceFetchResponseContent(pattern = '', replacement = '', propsToMatch = '', ...args) {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('replace-fetch-response', pattern, replacement, propsToMatch);
  if (pattern === '*') {
    pattern = '.*';
  }
  const rePattern = safe.patternToRegex(pattern);
  const propNeedles = createPropertyMatchMap(propsToMatch, 'url');
  const extraArgs = safe.getExtraArgs([pattern, replacement, propsToMatch, ...args], 4);
  const reIncludes = extraArgs.includes ? safe.patternToRegex(extraArgs.includes) : null;
  self.fetch = new Proxy(self.fetch, {
    apply: function (target, thisArg, fetchArgs) {
      const fetchPromise = Reflect.apply(target, thisArg, fetchArgs);
      if (pattern === '') {
        return fetchPromise;
      }
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
        return response.text().then(textBefore => {
          if (reIncludes && reIncludes.test(textBefore) === false) {
            return responseBefore;
          }
          const textAfter = textBefore.replace(rePattern, replacement);
          if (textAfter === textBefore) {
            return responseBefore;
          }
          safe.stndzLog(logPrefix, 'Replaced');
          const responseAfter = new Response(textAfter, {
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
          safe.stndzErr(logPrefix, reason);
          return responseBefore;
        });
      }).catch(reason => {
        safe.stndzErr(logPrefix, reason);
        return fetchPromise;
      });
    }
  });
}