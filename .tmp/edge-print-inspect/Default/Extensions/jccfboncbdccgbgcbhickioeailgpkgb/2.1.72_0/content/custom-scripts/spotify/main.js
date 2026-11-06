"use strict";

{
  const AD_ASSET_PATH = /^\/mp3(-ad)?\//;
  const AD_HOSTS = /(^|\.)(2mdn\.net|amillionads\.com)$/i;
  const SAMPLE_RATE = 8000;
  let silentUrl = '';
  const silentSource = () => {
    if (silentUrl) {
      return silentUrl;
    }
    const dataLength = SAMPLE_RATE * 1 * 2;
    const view = new DataView(new ArrayBuffer(44 + dataLength));
    const ascii = (offset, text) => {
      for (let i = 0; i < text.length; i += 1) {
        view.setUint8(offset + i, text.charCodeAt(i));
      }
    };
    ascii(0, 'RIFF');
    view.setUint32(4, 36 + dataLength, true);
    ascii(8, 'WAVE');
    ascii(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, SAMPLE_RATE, true);
    view.setUint32(28, SAMPLE_RATE * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    ascii(36, 'data');
    view.setUint32(40, dataLength, true);
    silentUrl = URL.createObjectURL(new Blob([view.buffer], {
      type: 'audio/wav'
    }));
    return silentUrl;
  };
  const isAdUrl = value => {
    if (!value.startsWith('https://')) {
      return false;
    }
    try {
      const url = new URL(value);
      return url.hostname === 'adstudio-assets.scdn.co' ? AD_ASSET_PATH.test(url.pathname) : AD_HOSTS.test(url.hostname);
    } catch {
      return false;
    }
  };
  const srcDescriptor = Object.getOwnPropertyDescriptor(HTMLMediaElement.prototype, 'src');
  const currentSrcDescriptor = Object.getOwnPropertyDescriptor(HTMLMediaElement.prototype, 'currentSrc');
  if (srcDescriptor?.get && srcDescriptor.set && currentSrcDescriptor?.get) {
    const nativeSrcGet = srcDescriptor.get;
    const nativeSrcSet = srcDescriptor.set;
    const nativeCurrentSrcGet = currentSrcDescriptor.get;
    Object.defineProperty(HTMLMediaElement.prototype, 'src', {
      configurable: true,
      enumerable: srcDescriptor.enumerable,
      get() {
        return this.__stndzAdSrc ?? nativeSrcGet.call(this);
      },
      set(value) {
        const next = String(value);
        if (isAdUrl(next)) {
          this.__stndzAdSrc = next;
          nativeSrcSet.call(this, silentSource());
          return;
        }
        delete this.__stndzAdSrc;
        nativeSrcSet.call(this, value);
      }
    });
    Object.defineProperty(HTMLMediaElement.prototype, 'currentSrc', {
      configurable: true,
      enumerable: currentSrcDescriptor.enumerable,
      get() {
        return this.__stndzAdSrc ?? nativeCurrentSrcGet.call(this);
      }
    });
  }
  const nativeSetAttribute = Element.prototype.setAttribute;
  Element.prototype.setAttribute = function (name, value) {
    if (this instanceof HTMLMediaElement && name.toLowerCase() === 'src') {
      const element = this;
      const next = String(value);
      if (isAdUrl(next)) {
        element.__stndzAdSrc = next;
        nativeSetAttribute.call(element, name, silentSource());
        return;
      }
      delete element.__stndzAdSrc;
    }
    nativeSetAttribute.call(this, name, value);
  };
}