"use strict";

function hideBeforeContent(selector) {
  const styleElement = currentDocument.createElement('style');
  styleElement.id = 'hi_be_co';
  styleElement.textContent = `${selector}::before {display:none !important;}`;
  if (document.head) {
    document.head.prepend(styleElement);
  } else {
    window.setTimeout(() => {
      addElementToHead(styleElement);
    }, 10);
  }
}