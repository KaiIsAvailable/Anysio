"use strict";
const GOOGLE_COOKIE_BANNER_HOSTS = ['google.com', 'google.ad', 'google.ae', 'google.al', 'google.am', 'google.as', 'google.at', 'google.az', 'google.ba', 'google.be', 'google.bf', 'google.bg', 'google.bi', 'google.bj', 'google.bs', 'google.bt', 'google.by', 'google.ca', 'google.cat', 'google.cd', 'google.cf', 'google.cg', 'google.ch', 'google.ci', 'google.cl', 'google.cm', 'google.cn', 'google.co.ao', 'google.co.bw', 'google.co.ck', 'google.co.cr', 'google.co.id', 'google.co.il', 'google.co.in', 'google.co.jp', 'google.co.ke', 'google.co.kr', 'google.co.ls', 'google.co.ma', 'google.co.mz', 'google.co.nz', 'google.co.th', 'google.co.tz', 'google.co.ug', 'google.co.uk', 'google.co.uz', 'google.co.ve', 'google.co.vi', 'google.co.za', 'google.co.zm', 'google.co.zw', 'google.com.af', 'google.com.ag', 'google.com.ar', 'google.com.au', 'google.com.bd', 'google.com.bh', 'google.com.bn', 'google.com.bo', 'google.com.br', 'google.com.bz', 'google.com.co', 'google.com.cu', 'google.com.cy', 'google.com.do', 'google.com.ec', 'google.com.eg', 'google.com.et', 'google.com.fj', 'google.com.gh', 'google.com.gi', 'google.com.gt', 'google.com.hk', 'google.com.jm', 'google.com.kh', 'google.com.kw', 'google.com.lb', 'google.com.ly', 'google.com.mm', 'google.com.mt', 'google.com.mx', 'google.com.my', 'google.com.na', 'google.com.ng', 'google.com.ni', 'google.com.np', 'google.com.om', 'google.com.pa', 'google.com.pe', 'google.com.pg', 'google.com.ph', 'google.com.pk', 'google.com.pr', 'google.com.py', 'google.com.qa', 'google.com.sa', 'google.com.sb', 'google.com.sg', 'google.com.sl', 'google.com.sv', 'google.com.tj', 'google.com.tr', 'google.com.tw', 'google.com.ua', 'google.com.uy', 'google.com.vc', 'google.com.vn', 'google.cv', 'google.cz', 'google.de', 'google.dj', 'google.dk', 'google.dm', 'google.dz', 'google.ee', 'google.es', 'google.fi', 'google.fm', 'google.fr', 'google.ga', 'google.ge', 'google.gg', 'google.gl', 'google.gm', 'google.gr', 'google.gy', 'google.hn', 'google.hr', 'google.ht', 'google.hu', 'google.ie', 'google.im', 'google.iq', 'google.is', 'google.it', 'google.je', 'google.jo', 'google.kg', 'google.ki', 'google.kz', 'google.la', 'google.li', 'google.lk', 'google.lt', 'google.lu', 'google.lv', 'google.md', 'google.me', 'google.mg', 'google.mk', 'google.ml', 'google.mn', 'google.mu', 'google.mv', 'google.mw', 'google.ne', 'google.nl', 'google.no', 'google.nr', 'google.nu', 'google.pl', 'google.pn', 'google.ps', 'google.pt', 'google.ro', 'google.rs', 'google.ru', 'google.rw', 'google.sc', 'google.se', 'google.sh', 'google.si', 'google.sk', 'google.sm', 'google.sn', 'google.so', 'google.sr', 'google.st', 'google.td', 'google.tg', 'google.tl', 'google.tm', 'google.tn', 'google.to', 'google.tt', 'google.vu', 'google.ws'];
class CookieBannersAllowedSites {
  container = new DataContainer('cookieBannersAllowedSites', {});
  async init() {
    const sitesData = await this.container.get();
    await Promise.all([this.updateDynamicRules(sitesData), this.updateContentScripts(sitesData)]);
  }
  async updateDynamicRules(sitesData) {
    const hosts = Object.keys(sitesData);
    const addRules = hosts.length ? [{
      id: ALLOWED_RULES_IDS.cookieBanner,
      priority: 100,
      action: {
        type: 'allow'
      },
      condition: {
        initiatorDomains: hosts
      }
    }] : [];
    await updateDynamicRules({
      removeRuleIds: [2],
      addRules
    });
  }
  async updateContentScripts(sitesData) {
    const data = [{
      hosts: ['geoguessr.com'],
      scripts: [{
        allFrames: true,
        id: 'geoguessr.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/geoguessr.com.js'],
        matches: ['*://*.geoguessr.com/*'],
        runAt: 'document_start'
      }]
    }, {
      hosts: ['outlook.live.com'],
      scripts: [{
        allFrames: true,
        id: 'outlook.live.com-cookie-banner-custom-script',
        js: ['content/custom-scripts/cookie-banners/outlook.live.com.js'],
        matches: ['*://*.outlook.live.com/*'],
        runAt: 'document_start'
      }]
    }, {
      hosts: ['globo.com', 'techtudo.com.br', 'haber7.com'],
      scripts: [{
        allFrames: true,
        id: 'globo.com-cookie-banner-custom-script',
        js: ['content/custom-scripts/cookie-banners/globo.com.js'],
        matches: ['*://*.globo.com/*', '*://*.techtudo.com.br/*', '*://*.haber7.com/*'],
        runAt: 'document_start'
      }]
    }, {
      hosts: [...GOOGLE_COOKIE_BANNER_HOSTS],
      scripts: [{
        allFrames: true,
        id: 'google.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/google.com.js'],
        matches: GOOGLE_COOKIE_BANNER_HOSTS.map(host => `*://*.${host}/*`),
        runAt: 'document_start'
      }]
    }, {
      hosts: ['facebook.com'],
      scripts: [{
        allFrames: true,
        id: 'facebook.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/facebook.com.js'],
        matches: ['*://*.facebook.com/*'],
        runAt: 'document_start'
      }]
    }, {
      hosts: ['consent.yahoo.com'],
      scripts: [{
        allFrames: true,
        id: 'consent.yahoo.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/consent.yahoo.com.js'],
        matches: ['*://*.consent.yahoo.com/*'],
        runAt: 'document_end'
      }]
    }, {
      hosts: ['dailymail.co.uk'],
      scripts: [{
        allFrames: true,
        id: 'dailymail.co.uk-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/dailymail.co.uk.js'],
        matches: ['*://*.dailymail.co.uk/*'],
        runAt: 'document_end'
      }]
    }, {
      hosts: ['letras.mus.br'],
      scripts: [{
        allFrames: true,
        id: 'letras.mus.br-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/letras.mus.br.js'],
        matches: ['*://*.letras.mus.br/*'],
        runAt: 'document_end'
      }]
    }, {
      hosts: ['quizlet.com'],
      scripts: [{
        allFrames: true,
        id: 'quizlet.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/quizlet.com.js'],
        matches: ['*://*.quizlet.com/*'],
        runAt: 'document_end'
      }]
    }, {
      hosts: ['nouvelobs.com'],
      scripts: [{
        allFrames: true,
        id: 'nouvelobs.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/nouvelobs.com.js'],
        matches: ['*://*.nouvelobs.com/*'],
        runAt: 'document_end'
      }]
    }, {
      hosts: ['instagram.com'],
      scripts: [{
        allFrames: true,
        id: 'instagram.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/instagram.com.js'],
        matches: ['*://*.instagram.com/*'],
        runAt: 'document_start'
      }]
    }, {
      hosts: ['reddit.com'],
      scripts: [{
        allFrames: true,
        id: 'reddit.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/reddit.com.js'],
        matches: ['*://*.reddit.com/*'],
        runAt: 'document_start'
      }]
    }, {
      hosts: ['bing.com'],
      scripts: [{
        allFrames: true,
        id: 'bing.com-cookie-banner-custom-script',
        js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/bing.com.js'],
        matches: ['*://*.bing.com/*'],
        runAt: 'document_start'
      }]
    }];
    ['bbc.com', 'bild.de', 'politico.eu', 'spiegel.de', 'telegraph.co.uk', 'theguardian.com', 'thesun.co.uk', 'weather.com'].forEach(host => {
      data.push({
        hosts: [host],
        scripts: [{
          allFrames: true,
          id: `${host}-cookie-banner-sp-custom-script`,
          js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/sp-message-iframe.js'],
          matches: [`*://*.${host}/*`],
          runAt: 'document_start'
        }]
      });
    });
    ['abc.es', 'elmundo.es', 'elpais.com', 'euronews.com', 'marca.com', 'sport.es'].forEach(host => {
      data.push({
        hosts: [host],
        scripts: [{
          allFrames: true,
          id: `${host}-cookie-banner-didomi-custom-script`,
          js: ['common/helpers.js', 'content/custom-scripts/cookie-banners/didomi.js'],
          matches: [`*://*.${host}/*`],
          runAt: 'document_start'
        }]
      });
    });
    const registeredScripts = await getRegisteredContentScripts({
      ids: data.flatMap(({
        scripts
      }) => scripts.map(({
        id
      }) => id))
    });
    const idsToUnregister = data.filter(({
      hosts
    }) => hosts.some(h => sitesData[h])).flatMap(({
      scripts
    }) => scripts).filter(({
      id
    }) => registeredScripts.some(s => s.id === id)).map(({
      id
    }) => id);
    if (idsToUnregister.length) {
      await unregisterContentScripts({
        ids: idsToUnregister
      });
    }
    const scriptsToRegister = data.flatMap(({
      scripts
    }) => scripts.filter(script => !idsToUnregister.includes(script.id) && !registeredScripts.some(s => s.id === script.id)));
    if (scriptsToRegister.length) {
      await registerContentScripts(scriptsToRegister);
    }
  }
  async toggle(host) {
    const data = await this.container.get();
    if (data[host]) {
      delete data[host];
    } else {
      data[host] = true;
    }
    await Promise.all([this.container.set(data), this.updateDynamicRules(data), this.updateContentScripts(data)]);
  }
  async getList() {
    const data = await this.container.get();
    return Object.keys(data);
  }
  async isAllowed(host) {
    const data = await this.container.get();
    return data[host];
  }
}
const cookieBannersAllowedSites = new CookieBannersAllowedSites();