"use strict";
if (window.top === window) {
  document.addEventListener('readystatechange', () => {
    isolated();
  });
}
function isolated() {
  const scriptletGlobals = {};
  function replaceNodeTextFn(nodeName = '', pattern = '', replacement = '', ...args) {
    const safe = safeSelf();
    const logPrefix = safe.makeLogPrefix('replace-node-text.fn', nodeName, pattern, replacement, ...args);
    const reNodeName = safe.patternToRegex(nodeName, 'i', true);
    const rePattern = safe.patternToRegex(pattern, 'gms');
    const extraArgs = safe.getExtraArgs([nodeName, pattern, replacement, ...args], 3);
    const reIncludes = extraArgs.includes || extraArgs.condition ? safe.patternToRegex(extraArgs.includes || extraArgs.condition, 'ms') : null;
    const reExcludes = extraArgs.excludes ? safe.patternToRegex(extraArgs.excludes, 'ms') : null;
    const stop = (takeRecord = true) => {
      if (takeRecord) {
        handleMutations(observer.takeRecords());
      }
      observer.disconnect();
      if (safe.logLevel > 1) {
        safe.stndzLog(logPrefix, 'Quitting');
      }
    };
    const textContentFactory = (() => {
      const out = {
        createScript: s => s
      };
      const {
        trustedTypes: tt
      } = self;
      if (tt instanceof Object) {
        if (typeof tt.getPropertyType === 'function') {
          if (tt.getPropertyType('script', 'textContent') === 'TrustedScript') {
            return tt.createPolicy(getRandomTokenFn(), out);
          }
        }
      }
      return out;
    })();
    let sedCount = extraArgs.sedCount || 0;
    const handleNode = node => {
      const before = node.textContent;
      if (reIncludes) {
        reIncludes.lastIndex = 0;
        if (safe.RegExp_test.call(reIncludes, before) === false) {
          return true;
        }
      }
      if (reExcludes) {
        reExcludes.lastIndex = 0;
        if (safe.RegExp_test.call(reExcludes, before)) {
          return true;
        }
      }
      rePattern.lastIndex = 0;
      if (safe.RegExp_test.call(rePattern, before) === false) {
        return true;
      }
      rePattern.lastIndex = 0;
      const after = pattern !== '' ? before.replace(rePattern, replacement) : replacement;
      node.textContent = node.nodeName === 'SCRIPT' ? textContentFactory.createScript(after) : after;
      if (safe.logLevel > 1) {
        safe.stndzLog(logPrefix, `Text before:\n${before.trim()}`);
      }
      safe.stndzLog(logPrefix, `Text after:\n${after.trim()}`);
      return sedCount === 0 || (sedCount -= 1) !== 0;
    };
    const handleMutations = mutations => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (reNodeName.test(node.nodeName) === false) {
            continue;
          }
          if (handleNode(node)) {
            continue;
          }
          stop(false);
          return;
        }
      }
    };
    const observer = new MutationObserver(handleMutations);
    observer.observe(document, {
      childList: true,
      subtree: true
    });
    if (document.documentElement) {
      const treeWalker = document.createTreeWalker(document.documentElement, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
      let count = 0;
      for (;;) {
        const node = treeWalker.nextNode();
        count += 1;
        if (node === null) {
          break;
        }
        if (reNodeName.test(node.nodeName) === false) {
          continue;
        }
        if (node === document.currentScript) {
          continue;
        }
        if (handleNode(node)) {
          continue;
        }
        stop();
        break;
      }
      safe.stndzLog(logPrefix, `${count} nodes present before installing mutation observer`);
    }
    if (extraArgs.stay) {
      return;
    }
    runAt(() => {
      const quitAfter = extraArgs.quitAfter || 0;
      if (quitAfter !== 0) {
        setTimeout(() => {
          stop();
        }, quitAfter);
      } else {
        stop();
      }
    }, 'interactive');
  }
  function removeNodeText(nodeName, includes, ...extraArgs) {
    replaceNodeTextFn(nodeName, '', '', 'includes', includes || '', ...extraArgs);
  }
  function removeAttr(rawToken = '', rawSelector = '', behavior = '') {
    if (typeof rawToken !== 'string') {
      return;
    }
    if (rawToken === '') {
      return;
    }
    const safe = safeSelf();
    const logPrefix = safe.makeLogPrefix('remove-attr', rawToken, rawSelector, behavior);
    const tokens = safe.String_split.call(rawToken, /\s*\|\s*/);
    const selector = tokens.map(a => `${rawSelector}[${CSS.escape(a)}]`).join(',');
    if (safe.logLevel > 1) {
      safe.uboLog(logPrefix, `Target selector:\n\t${selector}`);
    }
    const asap = /\basap\b/.test(behavior);
    let timerId;
    const rmattrAsync = () => {
      if (timerId !== undefined) {
        return;
      }
      timerId = safe.onIdle(() => {
        timerId = undefined;
        rmattr();
      }, {
        timeout: 17
      });
    };
    const rmattr = () => {
      if (timerId !== undefined) {
        safe.offIdle(timerId);
        timerId = undefined;
      }
      try {
        const nodes = document.querySelectorAll(selector);
        for (const node of nodes) {
          for (const attr of tokens) {
            if (node.hasAttribute(attr) === false) {
              continue;
            }
            node.removeAttribute(attr);
            safe.uboLog(logPrefix, `Removed attribute '${attr}'`);
          }
        }
      } catch {}
    };
    const mutationHandler = mutations => {
      if (timerId !== undefined) {
        return;
      }
      let skip = true;
      for (let i = 0; i < mutations.length && skip; i++) {
        const {
          type,
          addedNodes,
          removedNodes
        } = mutations[i];
        if (type === 'attributes') {
          skip = false;
        }
        for (let j = 0; j < addedNodes.length && skip; j++) {
          if (addedNodes[j].nodeType === 1) {
            skip = false;
            break;
          }
        }
        for (let j = 0; j < removedNodes.length && skip; j++) {
          if (removedNodes[j].nodeType === 1) {
            skip = false;
            break;
          }
        }
      }
      if (skip) {
        return;
      }
      asap ? rmattr() : rmattrAsync();
    };
    const start = () => {
      rmattr();
      if (/\bstay\b/.test(behavior) === false) {
        return;
      }
      const observer = new MutationObserver(mutationHandler);
      observer.observe(document, {
        attributes: true,
        attributeFilter: tokens,
        childList: true,
        subtree: true
      });
    };
    runAt(() => {
      start();
    }, safe.String_split.call(behavior, /\s+/));
  }
  function runAt(fn, when) {
    const intFromReadyState = state => {
      const targets = {
        loading: 1,
        interactive: 2,
        end: 2,
        '2': 2,
        complete: 3,
        idle: 3,
        '3': 3
      };
      const tokens = Array.isArray(state) ? state : [state];
      for (const token of tokens) {
        const prop = `${token}`;
        if (!targets.hasOwnProperty(prop)) {
          continue;
        }
        return targets[prop];
      }
      return 0;
    };
    const fromReadyState = intFromReadyState(when);
    if (intFromReadyState(document.readyState) >= fromReadyState) {
      fn();
      return;
    }
    const safe = safeSelf();
    const args = ['readystatechange', () => {
      if (intFromReadyState(document.readyState) < fromReadyState) {
        return;
      }
      fn();
      safe.removeEventListener.apply(document, args);
    }, {
      capture: true
    }];
    safe.addEventListener.apply(document, args);
  }
  function safeSelf() {
    if (scriptletGlobals.safeSelf) {
      return scriptletGlobals.safeSelf;
    }
    const self = globalThis;
    const safe = {
      Array_from: Array.from,
      Error: self.Error,
      Function_toStringFn: self.Function.prototype.toString,
      Function_toString: thisArg => safe.Function_toStringFn.call(thisArg),
      Math_floor: Math.floor,
      Math_max: Math.max,
      Math_min: Math.min,
      Math_random: Math.random,
      Object,
      Object_defineProperty: Object.defineProperty.bind(Object),
      Object_defineProperties: Object.defineProperties.bind(Object),
      Object_fromEntries: Object.fromEntries.bind(Object),
      Object_getOwnPropertyDescriptor: Object.getOwnPropertyDescriptor.bind(Object),
      RegExp: self.RegExp,
      RegExp_test: self.RegExp.prototype.test,
      RegExp_exec: self.RegExp.prototype.exec,
      Request_clone: self.Request.prototype.clone,
      String_fromCharCode: String.fromCharCode,
      XMLHttpRequest: self.XMLHttpRequest,
      addEventListener: self.EventTarget.prototype.addEventListener,
      removeEventListener: self.EventTarget.prototype.removeEventListener,
      fetch: self.fetch,
      JSON: self.JSON,
      JSON_parseFn: self.JSON.parse,
      JSON_stringifyFn: self.JSON.stringify,
      JSON_parse: (...args) => safe.JSON_parseFn.call(safe.JSON, ...args),
      JSON_stringify: (...args) => safe.JSON_stringifyFn.call(safe.JSON, ...args),
      logLevel: 1,
      makeLogPrefix(...args) {
        return this.sendToLogger && `[${args.join(' \u205D ')}]` || '';
      },
      sendToLogger(type, ...args) {},
      stndzLog(...args) {
        if (this.sendToLogger === undefined) {
          return;
        }
        if (args === undefined || args[0] === '') {
          return;
        }
        return this.sendToLogger('info', ...args);
      },
      stndzErr(...args) {
        if (this.sendToLogger === undefined) {
          return;
        }
        if (args === undefined || args[0] === '') {
          return;
        }
        return this.sendToLogger('error', ...args);
      },
      escapeRegexChars(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      },
      initPattern(pattern, options = {}) {
        if (pattern === '') {
          return {
            matchAll: true
          };
        }
        const expect = options.canNegate !== true || !pattern.startsWith('!');
        if (!expect) {
          pattern = pattern.slice(1);
        }
        const match = /^\/(.+)\/([gimsu]*)$/.exec(pattern);
        if (match !== null) {
          return {
            re: new this.RegExp(match[1], match[2] || options.flags),
            expect
          };
        }
        if (options.flags !== undefined) {
          return {
            re: new this.RegExp(this.escapeRegexChars(pattern), options.flags),
            expect
          };
        }
        return {
          pattern,
          expect
        };
      },
      testPattern(details, haystack) {
        if (details.matchAll) {
          return true;
        }
        if (details.re) {
          return this.RegExp_test.call(details.re, haystack) === details.expect;
        }
        return haystack.includes(details.pattern) === details.expect;
      },
      patternToRegex(pattern, flags = undefined, verbatim = false) {
        if (pattern === '') {
          return /^/;
        }
        const match = /^\/(.+)\/([gimsu]*)$/.exec(pattern);
        if (match === null) {
          const reStr = this.escapeRegexChars(pattern);
          return new RegExp(verbatim ? `^${reStr}$` : reStr, flags);
        }
        try {
          return new RegExp(match[1], match[2] || undefined);
        } catch (ex) {}
        return /^/;
      },
      getExtraArgs(args, offset = 0) {
        const entries = args.slice(offset).reduce((out, v, i, a) => {
          if ((i & 1) === 0) {
            const rawValue = a[i + 1];
            const value = /^\d+$/.test(rawValue) ? parseInt(rawValue, 10) : rawValue;
            out.push([a[i], value]);
          }
          return out;
        }, []);
        return this.Object_fromEntries(entries);
      },
      onIdle(fn, options) {
        if (self.requestIdleCallback) {
          return self.requestIdleCallback(fn, options);
        }
        return self.requestAnimationFrame(fn);
      }
    };
    scriptletGlobals.safeSelf = safe;
    return safe;
  }
  function getRandomTokenFn() {
    const safe = safeSelf();
    return safe.String_fromCharCode(Date.now() % 26 + 97) + safe.Math_floor(safe.Math_random() * 982451653 + 982451653).toString(36);
  }
  try {
    const patternStr = '(function serverContract()';
    function replacementFunction() {
      if ('YOUTUBE_PREMIUM_LOGO' === ytInitialData?.topbar?.desktopTopbarRenderer?.logo?.topbarLogoRenderer?.iconImage?.iconType || location.href.startsWith('https://www.youtube.com/tv#/') || location.href.startsWith('https://www.youtube.com/embed/')) return;
      const e = ytcfg.data_.INNERTUBE_CONTEXT.client.userAgent,
        t = t => {
          ytcfg.data_.INNERTUBE_CONTEXT.client.userAgent = t ? e.replace?.(/(Mozilla\/5\.0 \([^)]+)/, `$1; ${t}`) : e;
        },
        o = ['channel'];
      let a = !1,
        r = o;
      document.addEventListener('DOMContentLoaded', function () {
        const e = () => {
          const e = document.getElementById('movie_player');
          if (!e || !window.location.href.includes('/watch?')) return void (r = o);
          const n = e.getPlayerResponse?.(),
            s = e.getProgressState?.(),
            i = e.getStatsForNerds?.();
          if (s && s.duration > 0 && (s.loaded < s.duration || s.duration - s.current > 1) || n?.videoDetails?.isLive) {
            if (!i?.debug_info?.startsWith?.('SSAP, AD')) {
              const o = n.videoDetails?.videoId,
                s = n.playerConfig?.playbackStartConfig?.startSeconds ?? 0,
                d = e.getPlayerStateObject?.()?.isBuffering;
              return void ('UNPLAYABLE' !== n?.playabilityStatus?.status || n?.playabilityStatus?.errorScreen?.playerErrorMessageRenderer?.playerCaptchaViewModel || 'WEB_PAGE_TYPE_UNKNOWN' !== n?.playabilityStatus?.errorScreen?.playerErrorMessageRenderer?.subreason?.runs?.[0]?.navigationEndpoint?.commandMetadata?.webCommandMetadata?.webPageType || 'https://support.google.com/youtube/answer/3037019' !== n?.playabilityStatus?.errorScreen?.playerErrorMessageRenderer?.subreason?.runs?.[0]?.navigationEndpoint?.commandMetadata?.webCommandMetadata?.url ? 0 === r.length ? (a = !1, t('')) : d && '0.00 s' === i?.buffer_health_seconds && '0x0' === i?.resolution && a && (t(r[0]), a = !1, e.loadVideoById(o, s)) : (r = r.slice(1), r.length > 0 ? t(r[0]) : t(''), a = !1, e.loadVideoById(o, s)));
            }
            s.duration > 0 && e.seekTo?.(s.duration);
          }
        };
        e(), new MutationObserver(() => {
          e();
        }).observe(document, {
          childList: !0,
          subtree: !0
        });
      }), window.Map.prototype.has = new Proxy(window.Map.prototype.has, {
        apply: (e, t, o) => {
          if ('onSnackbarMessage' === o?.[0] && !a) {
            const e = document.getElementById('movie_player');
            if (!e) return;
            const t = e.getStatsForNerds?.(),
              o = e.getPlayerStateObject?.()?.isBuffering,
              n = e.getPlayerResponse?.()?.playbackTracking?.videostatsPlaybackUrl?.baseUrl;
            o && '0.00 s' === t?.buffer_health_seconds && '0x0' === t?.resolution && r.length > 0 && (n.includes('reloadxhr') && (r = r.slice(1)), a = !0);
          }
          return Reflect.apply(e, t, o);
        }
      });
      const n = {
        apply: (e, t, o) => {
          const a = o[0];
          return 'function' == typeof a && a.toString().includes('onAbnormalityDetected') && (o[0] = function () {}), Reflect.apply(e, t, o);
        }
      };
      window.Promise.prototype.then = new Proxy(window.Promise.prototype.then, n);
    }
    replaceNodeTextFn('script', patternStr, `(${replacementFunction.toString()})();${patternStr}`, 'sedCount', '1');
    removeNodeText('script', 'window,"fetch"');
    removeAttr('player-unavailable', '#page-manager:has(#player-error-message-container #subreason a.yt-simple-endpoint[href="https://support.google.com/youtube/answer/3037019"]) ytd-watch-flexy[player-unavailable]', 'asap stay');
  } catch (e) {}
}
{
  const fn = debounce(() => {
    document.querySelector('#page-manager:has(#player-error-message-container #subreason a.yt-simple-endpoint[href="https://support.google.com/youtube/answer/3037019"]) ytd-watch-flexy[player-unavailable]')?.removeAttribute('player-unavailable');
    setStyle(document.querySelector('#page-manager:has(#player-error-message-container #subreason a.yt-simple-endpoint[href="https://support.google.com/youtube/answer/3037019"]) ytd-watch-flexy[player-unavailable] :is(#player-container-outer, #cinematics-container, #ytd-player > #container)'), {
      visibility: 'visible'
    });
  }, 100);
  fn();
  new MutationObserver(fn).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
}