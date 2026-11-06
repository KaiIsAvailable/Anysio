"use strict";

function changeStyleForRedirectElement(redirectElement) {
  const elems = document.querySelectorAll(redirectElement);
  const listOfClasses = [];
  const listOfIds = [];
  elems.forEach(elem => {
    listOfClasses.push(elem.className ?? '');
    listOfIds.push(elem.id ?? '');
  });
  const stringOfClasses = listOfClasses.length > 0 ? listOfClasses.filter(el => el !== '').map(el => `.${el}`).join(',') : '';
  const stringOfIds = listOfIds.length > 0 ? listOfIds.filter(el => el !== '').map(el => `.${el}`).join(',') : '';
  const fullList = stringOfClasses + (stringOfIds.length > 0 ? `,${stringOfIds}` : '');
  if (fullList.length > 0) {
    const styleElement = currentDocument.createElement('style');
    styleElement.id = 'st_fo_re_el';
    styleElement.textContent = `${fullList} {display:none !important; visibility:hidden !important; opacity:0 !important; 'position:absolute !important; width:0px !important; height:0px !important;}`;
    if (document.head) {
      document.head.prepend(styleElement);
    } else {
      window.setTimeout(() => {
        addElementToHead(styleElement);
      }, 10);
    }
  }
}
function changeStyleForRedirectElementScript(selector) {
  changeStyleForRedirectElement(selector);
  if (document.readyState !== 'complete' && window.top === window) {
    setTimeout(() => {
      changeStyleForRedirectElementScript(selector);
    }, 5000);
  }
}