"use strict";

{
  document.querySelectorAll('style[id^="stndz-general"]').forEach(i => i.remove());
  const styleElement = currentDocument.createElement('style');
  styleElement.id = 'nth-rec';
  styleElement.textContent = `.DisplayAdController ${BLOCK_CSS_VALUE}`;
  document.head.prepend(styleElement);
}