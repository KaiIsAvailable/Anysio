"use strict";
function proxyApply(target = '', handler) {
  let context = globalThis;
  let prop = target;
  for (;;) {
    const pos = prop.indexOf('.');
    if (pos === -1) {
      break;
    }
    context = context[prop.slice(0, pos)];
    if (context instanceof Object === false) {
      return;
    }
    prop = prop.slice(pos + 1);
  }
  const fn = context[prop];
  if (typeof fn !== 'function') {
    return;
  }
  if (proxyApply.CtorContext === undefined) {
    proxyApply.ctorContexts = [];
    proxyApply.CtorContext = class {
      constructor(...args) {
        this.init(...args);
      }
      init(callFn, callArgs) {
        this.callFn = callFn;
        this.callArgs = callArgs;
        return this;
      }
      reflect() {
        const r = Reflect.construct(this.callFn, this.callArgs);
        this.callFn = this.callArgs = this.private = undefined;
        proxyApply.ctorContexts.push(this);
        return r;
      }
      static factory(...args) {
        return proxyApply.ctorContexts.length !== 0 ? proxyApply.ctorContexts.pop().init(...args) : new proxyApply.CtorContext(...args);
      }
    };
    proxyApply.applyContexts = [];
    proxyApply.ApplyContext = class {
      constructor(...args) {
        this.init(...args);
      }
      init(callFn, thisArg, callArgs) {
        this.callFn = callFn;
        this.thisArg = thisArg;
        this.callArgs = callArgs;
        return this;
      }
      reflect() {
        const r = Reflect.apply(this.callFn, this.thisArg, this.callArgs);
        this.callFn = this.thisArg = this.callArgs = this.private = undefined;
        proxyApply.applyContexts.push(this);
        return r;
      }
      static factory(...args) {
        return proxyApply.applyContexts.length !== 0 ? proxyApply.applyContexts.pop().init(...args) : new proxyApply.ApplyContext(...args);
      }
    };
    proxyApply.isCtor = new Map();
    proxyApply.proxies = new WeakMap();
    proxyApply.nativeToString = Function.prototype.toString;
    const proxiedToString = new Proxy(Function.prototype.toString, {
      apply(target, thisArg) {
        let proxied = thisArg;
        for (;;) {
          const fn = proxyApply.proxies.get(proxied);
          if (fn === undefined) {
            break;
          }
          proxied = fn;
        }
        return proxyApply.nativeToString.call(proxied);
      }
    });
    proxyApply.proxies.set(proxiedToString, proxyApply.nativeToString);
    Function.prototype.toString = proxiedToString;
  }
  if (proxyApply.isCtor.has(target) === false) {
    proxyApply.isCtor.set(target, fn.prototype?.constructor === fn);
  }
  const proxyDetails = {
    apply(target, thisArg, args) {
      return handler(proxyApply.ApplyContext.factory(target, thisArg, args));
    }
  };
  if (proxyApply.isCtor.get(target)) {
    proxyDetails.construct = function (target, args) {
      return handler(proxyApply.CtorContext.factory(target, args));
    };
  }
  const proxiedTarget = new Proxy(fn, proxyDetails);
  proxyApply.proxies.set(proxiedTarget, fn);
  context[prop] = proxiedTarget;
}