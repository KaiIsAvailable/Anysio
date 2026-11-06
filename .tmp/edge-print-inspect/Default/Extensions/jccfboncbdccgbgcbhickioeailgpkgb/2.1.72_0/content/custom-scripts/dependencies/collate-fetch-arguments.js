"use strict";

const collateFetchArguments = function (resource, options) {
  const safe = safeSelf();
  const props = ['body', 'cache', 'credentials', 'duplex', 'headers', 'integrity', 'keepalive', 'method', 'mode', 'priority', 'redirect', 'referrer', 'referrerPolicy', 'url'];
  const out = {};
  if (collateFetchArguments.collateKnownProps === undefined) {
    collateFetchArguments.collateKnownProps = (src, outParam) => {
      for (const prop of props) {
        if (src[prop] === undefined) {
          continue;
        }
        outParam[prop] = src[prop];
      }
    };
  }
  if (typeof resource !== 'object' || safe.Object_toString.call(resource) !== '[object Request]') {
    out.url = `${resource}`;
  } else {
    let clone;
    try {
      clone = safe.Request_clone.call(resource);
    } catch {}
    collateFetchArguments.collateKnownProps(clone || resource, out);
  }
  if (typeof options === 'object' && options !== null) {
    collateFetchArguments.collateKnownProps(options, out);
  }
  return out;
};