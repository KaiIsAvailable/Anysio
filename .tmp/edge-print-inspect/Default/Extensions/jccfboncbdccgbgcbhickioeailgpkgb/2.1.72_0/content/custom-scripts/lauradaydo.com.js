"use strict";

abortCurrentScript('globalThis', 'break;case');
abortCurrentScript('WebAssembly', 'atob');
defineConstant('console.clear', 'undefined');