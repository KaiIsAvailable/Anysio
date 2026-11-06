"use strict";

function pruneInboundObject(entryPoint = '', argPos = '', rawPrunePaths = '', rawNeedlePaths = '', ...args) {
  if (entryPoint === '') {
    return;
  }
  let context = globalThis;
  let prop = entryPoint;
  for (;;) {
    const pos = prop.indexOf('.');
    if (pos === -1) {
      break;
    }
    const nextContext = context[prop.slice(0, pos)];
    if (nextContext instanceof Object === false) {
      return;
    }
    context = nextContext;
    prop = prop.slice(pos + 1);
  }
  const fn = context[prop];
  if (typeof fn !== 'function') {
    return;
  }
  const argIndex = parseInt(argPos);
  if (isNaN(argIndex)) {
    return;
  }
  if (argIndex < 1) {
    return;
  }
  const safe = safeSelf();
  const extraArgs = safe.getExtraArgs(args, 0);
  const needlePaths = [];
  if (rawPrunePaths !== '') {
    needlePaths.push(...safe.String_split.call(rawPrunePaths, / +/));
  }
  if (rawNeedlePaths !== '') {
    needlePaths.push(...safe.String_split.call(rawNeedlePaths, / +/));
  }
  const stackNeedle = safe.initPattern(extraArgs.stackToMatch || '', {
    canNegate: true
  });
  const mustProcess = root => {
    for (const needlePath of needlePaths) {
      if (findObjectOwner(root, needlePath) === false) {
        return false;
      }
    }
    return true;
  };
  context[prop] = new Proxy(fn, {
    apply: function (target, thisArg, argsList) {
      const targetArg = argIndex <= argsList.length ? argsList[argIndex - 1] : undefined;
      if (targetArg instanceof Object && mustProcess(targetArg)) {
        let objBefore = targetArg;
        if (extraArgs.dontOverwrite) {
          try {
            objBefore = safe.JSON_parse(safe.JSON_stringify(targetArg));
          } catch {
            objBefore = undefined;
          }
        }
        if (objBefore !== undefined) {
          const objAfter = pruneObject(objBefore, rawPrunePaths, rawNeedlePaths, stackNeedle);
          argsList[argIndex - 1] = objAfter || objBefore;
        }
      }
      return Reflect.apply(target, thisArg, argsList);
    }
  });
}