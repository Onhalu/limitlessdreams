/* Limitless Dreams cookie consent.
   Library: vanilla-cookieconsent 3.1.0 (pinned), self-hosted as
   /assets/cookieconsent.umd.js and /assets/cookieconsent.css.
   https://cookieconsent.orestbida.com/ */
(function () {
  'use strict';

  var CC_VERSION = '3.1.0';
  var COPY = {
    cs: {
      youtube: {
        kicker: 'Video je vypnuté',
        note: 'YouTube (youtube-nocookie.com) se spustí až po souhlasu s médii.',
        button: 'Načíst video'
      },
      heyzine: {
        kicker: 'Ukázka je vypnutá',
        note: 'Heyzine načte náhled až po souhlasu s médii. Objednávka funguje i bez něj.',
        button: 'Načíst ukázku'
      }
    },
    en: {
      youtube: {
        kicker: 'Video is off',
        note: 'YouTube (youtube-nocookie.com) loads only after you allow media.',
        button: 'Load video'
      },
      heyzine: {
        kicker: 'Preview is off',
        note: 'Heyzine loads the preview only after you allow media. Ordering works without it.',
        button: 'Load preview'
      }
    }
  };

  function pageLang() {
    var lang = (document.documentElement.getAttribute('lang') || 'cs').toLowerCase();
    if (document.querySelector('.lang-btn[data-lang]')) {
      try {
        var saved = localStorage.getItem('ld-lang');
        if (saved === 'en' || saved === 'cs') lang = saved;
      } catch (err) {}
    }
    return lang.indexOf('en') === 0 ? 'en' : 'cs';
  }

  var currentLang = pageLang();

  /* Google Consent Mode v2 defaults. No Google tag is loaded.
     A future GA tag must be inserted after this script so these
     defaults exist before the tag runs. analytics_storage flips to
     granted only after the analytics category is accepted. */
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  window.gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    functionality_storage: 'denied',
    personalization_storage: 'denied',
    security_storage: 'granted',
    wait_for_update: 500
  });
  window.gtag('set', 'ads_data_redaction', true);
  window.gtag('set', 'url_passthrough', true);

  function syncConsentMode() {
    var analytics = !!(window.CookieConsent && CookieConsent.acceptedCategory('analytics'));
    window.gtag('consent', 'update', {
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: analytics ? 'granted' : 'denied',
      functionality_storage: 'denied',
      personalization_storage: 'denied',
      security_storage: 'granted'
    });
    /* Empty on purpose: GA is not installed. When a measurement ID
       exists, load gtag.js here only if analytics is granted. */
  }

  function applyMediaCopy(lang) {
    var pack = COPY[lang] || COPY.cs;
    document.querySelectorAll('.ld-media').forEach(function (el) {
      var kind = el.getAttribute('data-ld-media');
      var copy = pack[kind];
      if (!copy) return;
      var kicker = el.querySelector('.ld-media-kicker');
      var note = el.querySelector('.ld-media-note');
      var button = el.querySelector('.ld-media-load');
      if (kicker) kicker.textContent = copy.kicker;
      if (note) note.textContent = copy.note;
      if (button) button.textContent = copy.button;
    });
  }

  function mountMedia() {
    document.querySelectorAll('.ld-media').forEach(function (el) {
      var src = el.getAttribute('data-ld-src');
      if (!src || el.querySelector('iframe')) return;
      var iframe = document.createElement('iframe');
      iframe.src = src;
      iframe.title = el.getAttribute('data-ld-title') || '';
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      var placeholder = el.querySelector('.ld-media-placeholder');
      if (placeholder) placeholder.hidden = true;
      el.appendChild(iframe);
    });
  }

  function unmountMedia() {
    document.querySelectorAll('.ld-media iframe').forEach(function (iframe) {
      iframe.remove();
    });
    document.querySelectorAll('.ld-media-placeholder').forEach(function (placeholder) {
      placeholder.hidden = false;
    });
  }

  function applyConsent() {
    syncConsentMode();
    if (window.CookieConsent && CookieConsent.acceptedCategory('media')) mountMedia();
    else unmountMedia();
  }

  function enableMedia() {
    if (!window.CookieConsent) return;
    if (CookieConsent.acceptedCategory('media')) {
      mountMedia();
      return;
    }
    var prefs = CookieConsent.getUserPreferences();
    var accepted = (prefs.acceptedCategories || []).filter(function (name) {
      return name && name !== 'necessary';
    });
    if (accepted.indexOf('media') === -1) accepted.push('media');
    CookieConsent.acceptCategory(accepted);
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('.ld-media-load');
    if (!button) return;
    event.preventDefault();
    enableMedia();
  });

  window.ldSetConsentLang = function (lang) {
    var next = lang === 'en' ? 'en' : 'cs';
    if (next === currentLang) return;
    currentLang = next;
    applyMediaCopy(currentLang);
    if (window.CookieConsent && typeof CookieConsent.setLanguage === 'function') {
      CookieConsent.setLanguage(currentLang, true);
    }
  };

  function cookieTable(lang, rows) {
    var en = lang === 'en';
    return {
      caption: en ? 'Cookies in this category' : 'Cookies v této kategorii',
      headers: en
        ? { name: 'Name', domain: 'Domain', description: 'Purpose', expiration: 'Duration' }
        : { name: 'Název', domain: 'Doména', description: 'Účel', expiration: 'Platnost' },
      body: rows
    };
  }

  var necessaryRows = {
    cs: [
      {
        name: 'cc_cookie',
        domain: 'limitlessdreams.cz',
        description: 'Ukládá volbu souhlasu (vanilla-cookieconsent ' + CC_VERSION + ').',
        expiration: '182 dní'
      },
      {
        name: '_nss',
        domain: 'limitlessdreams.cz',
        description: 'Technická cookie hostingu WEDOS. Potřebná pro provoz webu, neslouží k reklamě.',
        expiration: 'dle hostingu WEDOS'
      },
      {
        name: 'FAPI',
        domain: 'form.fapi.cz',
        description: 'Technické cookies objednávkového formuláře. Objednávka funguje i bez souhlasu s médii a analytikou.',
        expiration: 'dle FAPI'
      }
    ],
    en: [
      {
        name: 'cc_cookie',
        domain: 'limitlessdreams.cz',
        description: 'Stores the consent choice (vanilla-cookieconsent ' + CC_VERSION + ').',
        expiration: '182 days'
      },
      {
        name: '_nss',
        domain: 'limitlessdreams.cz',
        description: 'Technical cookie set by WEDOS hosting. Required to run the site, not used for advertising.',
        expiration: 'set by WEDOS hosting'
      },
      {
        name: 'FAPI',
        domain: 'form.fapi.cz',
        description: 'Technical cookies for the order form. Checkout works without media or analytics consent.',
        expiration: 'set by FAPI'
      }
    ]
  };

  function translation(lang) {
    var cs = lang !== 'en';
    return {
      consentModal: cs ? {
        title: 'Souhlas s cookies',
        description: 'Aby web a objednávky fungovaly, používáme nezbytné cookies (formuláře FAPI a technickou cookie hostingu _nss). Videa a náhled e-booku z YouTube a Heyzine spustíme jen se souhlasem. Analytiku zatím nepoužíváme a je vypnutá. <a class="cc__link" href="/cookies/">Více o cookies</a>',
        acceptAllBtn: 'Přijmout vše',
        acceptNecessaryBtn: 'Odmítnout vše',
        showPreferencesBtn: 'Nastavení'
      } : {
        title: 'Cookie consent',
        description: 'We use necessary cookies so the site and orders work (FAPI order forms and the _nss hosting cookie). YouTube and Heyzine embeds load only with your consent. Analytics is unused and stays off. <a class="cc__link" href="/cookies/">Cookie details</a>',
        acceptAllBtn: 'Accept all',
        acceptNecessaryBtn: 'Reject all',
        showPreferencesBtn: 'Settings'
      },
      preferencesModal: cs ? {
        title: 'Nastavení cookies',
        acceptAllBtn: 'Přijmout vše',
        acceptNecessaryBtn: 'Odmítnout vše',
        savePreferencesBtn: 'Uložit nastavení',
        closeIconLabel: 'Zavřít',
        serviceCounterLabel: 'služba|služby',
        sections: [
          {
            title: 'Jak cookies používáme',
            description: 'Nezbytné cookies jsou vždy zapnuté. Média a analytiku zapneme jen vy. Volbu změníte kdykoli odkazem Nastavení cookies v patičce nebo na stránce <a class="cc__link" href="/cookies/">Cookies</a>.'
          },
          {
            title: 'Nezbytné',
            description: 'Potřebné pro zobrazení webu a odeslání objednávky. Nelze je vypnout. Objednávkové formuláře FAPI se načítají vždy, i když odmítnete ostatní kategorie.',
            linkedCategory: 'necessary',
            cookieTable: cookieTable('cs', necessaryRows.cs)
          },
          {
            title: 'Média',
            description: 'YouTube (přes youtube-nocookie.com) a náhled e-booku Heyzine. Bez souhlasu zůstane jen zástupný rámeček. Kliknutím na Načíst video nebo Načíst ukázku tuto kategorii povolíte a obsah se načte.',
            linkedCategory: 'media'
          },
          {
            title: 'Analytika',
            description: 'Prázdná kategorie, ve výchozím stavu vypnutá. Je připravená pro Google Analytics v Consent Mode v2 (analytics_storage). Měřicí kód na webu není a zapnutí teď nic neodesílá.',
            linkedCategory: 'analytics'
          }
        ]
      } : {
        title: 'Cookie settings',
        acceptAllBtn: 'Accept all',
        acceptNecessaryBtn: 'Reject all',
        savePreferencesBtn: 'Save settings',
        closeIconLabel: 'Close',
        serviceCounterLabel: 'service|services',
        sections: [
          {
            title: 'How we use cookies',
            description: 'Necessary cookies stay on. Media and analytics run only if you allow them. Change this any time with the Cookie settings link in the footer or on the <a class="cc__link" href="/cookies/">Cookies</a> page.'
          },
          {
            title: 'Necessary',
            description: 'Required to show the site and place an order. These cannot be switched off. FAPI order forms always load, even if you reject every other category.',
            linkedCategory: 'necessary',
            cookieTable: cookieTable('en', necessaryRows.en)
          },
          {
            title: 'Media',
            description: 'YouTube (via youtube-nocookie.com) and the Heyzine e-book preview. Without consent you only see a placeholder. Load video or Load preview accepts this category and then loads the embed.',
            linkedCategory: 'media'
          },
          {
            title: 'Analytics',
            description: 'Empty category, off by default. Reserved for Google Analytics with Consent Mode v2 (analytics_storage). No measurement tag is installed, so turning this on does not send anything yet.',
            linkedCategory: 'analytics'
          }
        ]
      }
    };
  }

  applyMediaCopy(currentLang);

  if (!window.CookieConsent || typeof CookieConsent.run !== 'function') return;

  CookieConsent.run({
    revision: 1,
    mode: 'opt-in',
    hideFromBots: false,
    disablePageInteraction: false,
    autoClearCookies: true,
    manageScriptTags: true,
    cookie: {
      name: 'cc_cookie',
      expiresAfterDays: 182,
      sameSite: 'Lax',
      path: '/'
    },
    guiOptions: {
      consentModal: {
        layout: 'box',
        position: 'bottom center',
        equalWeightButtons: true,
        flipButtons: false
      },
      preferencesModal: {
        layout: 'box',
        equalWeightButtons: true,
        flipButtons: false
      }
    },
    categories: {
      necessary: {
        enabled: true,
        readOnly: true
      },
      media: {
        services: {
          youtube: { label: 'YouTube' },
          heyzine: { label: 'Heyzine' }
        }
      },
      analytics: {
        autoClear: {
          cookies: [
            { name: /^_ga/ },
            { name: '_gid' },
            { name: '_gat' }
          ]
        },
        services: {
          ga: { label: 'Google Analytics' }
        }
      }
    },
    onFirstConsent: applyConsent,
    onConsent: applyConsent,
    onChange: applyConsent,
    language: {
      default: currentLang,
      translations: {
        cs: translation('cs'),
        en: translation('en')
      }
    }
  }).then(function () {
    applyConsent();
  });
})();
