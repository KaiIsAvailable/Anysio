"use strict";

function pruneJson(rawPrunePaths = '', rawNeedlePaths = '', stackNeedle = '') {
  const safe = safeSelf();
  const logPrefix = safe.makeLogPrefix('json-prune', rawPrunePaths, rawNeedlePaths, stackNeedle);
  const stackNeedleDetails = safe.initPattern(stackNeedle, {
    canNegate: true
  });
  const extraArgs = safe.getExtraArgs([rawPrunePaths, rawNeedlePaths, stackNeedle], 3);
  proxyApply('JSON.parse', function (context) {
    const objBefore = context.reflect();
    if (rawPrunePaths === '') {
      safe.stndzLog(logPrefix, safe.JSON_stringify(objBefore, null, 2));
    }
    const objAfter = pruneObject(objBefore, rawPrunePaths, rawNeedlePaths, stackNeedleDetails, extraArgs);
    if (objAfter === undefined) {
      return objBefore;
    }
    safe.stndzLog(logPrefix, 'Pruned');
    if (safe.logLevel > 1) {
      safe.stndzLog(logPrefix, `After pruning:\n${safe.JSON_stringify(objAfter, null, 2)}`);
    }
    return objAfter;
  });
}