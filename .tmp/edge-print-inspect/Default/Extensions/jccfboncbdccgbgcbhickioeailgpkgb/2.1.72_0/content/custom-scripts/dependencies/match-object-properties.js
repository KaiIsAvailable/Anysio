"use strict";

function matchObjectProperties(propNeedles, ...objs) {
  const safe = safeSelf();
  const matched = [];
  for (const obj of objs) {
    if (obj instanceof Object === false) {
      continue;
    }
    for (const [prop, details] of propNeedles) {
      let value = obj[prop];
      if (value === undefined) {
        continue;
      }
      if (typeof value !== 'string') {
        try {
          value = safe.JSON_stringify(value);
        } catch {}
        if (typeof value !== 'string') {
          continue;
        }
      }
      if (safe.testPattern(details, value) === false) {
        return;
      }
      matched.push(`${prop}: ${value}`);
    }
  }
  return matched;
}