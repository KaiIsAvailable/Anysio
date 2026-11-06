"use strict";

function jsonlEdit(jsonp, text = '') {
  const safe = safeSelf();
  const lineSeparator = /\r?\n/.exec(text)?.[0] || '\n';
  const linesBefore = text.split('\n');
  const linesAfter = [];
  for (const lineBefore of linesBefore) {
    let obj;
    try {
      obj = safe.JSON_parse(lineBefore);
    } catch {}
    if (typeof obj !== 'object' || obj === null) {
      linesAfter.push(lineBefore);
      continue;
    }
    if (jsonp.apply(obj) === 0) {
      linesAfter.push(lineBefore);
      continue;
    }
    const lineAfter = safe.JSON_stringify(obj);
    linesAfter.push(lineAfter);
  }
  return linesAfter.join(lineSeparator);
}