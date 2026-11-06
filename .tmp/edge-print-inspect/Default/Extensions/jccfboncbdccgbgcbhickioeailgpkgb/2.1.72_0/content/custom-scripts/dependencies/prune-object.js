"use strict";

const pruneObject = function (obj, rawPrunePaths, rawNeedlePaths, stackNeedleDetails = {
  matchAll: true
}, extraArgs = {}) {
  if (typeof rawPrunePaths !== 'string') {
    return;
  }
  const safe = safeSelf();
  const prunePaths = rawPrunePaths !== '' ? safe.String_split.call(rawPrunePaths, / +/) : [];
  const needlePaths = prunePaths.length !== 0 && rawNeedlePaths !== '' ? safe.String_split.call(rawNeedlePaths, / +/) : [];
  if (stackNeedleDetails.matchAll !== true) {
    if (matchesStackTrace(stackNeedleDetails, extraArgs.logstack) === false) {
      return;
    }
  }
  if (pruneObject.mustProcess === undefined) {
    pruneObject.mustProcess = (root, needlePathsParam) => {
      for (const needlePath of needlePathsParam) {
        if (findObjectOwner(root, needlePath) === false) {
          return false;
        }
      }
      return true;
    };
  }
  if (prunePaths.length === 0) {
    return;
  }
  let outcome = 'nomatch';
  if (pruneObject.mustProcess(obj, needlePaths)) {
    for (const path of prunePaths) {
      if (findObjectOwner(obj, path, true)) {
        outcome = 'match';
      }
    }
  }
  if (outcome === 'match') {
    return obj;
  }
};