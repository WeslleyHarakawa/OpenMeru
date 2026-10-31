(function () {
  // Texts come from the server (lang/<locale>.json), for the language in the openmeru_lang cookie.
  const I18N = window.I18N || {};

  const FOLDERS = [
    { id: "inbox", icon: "bi-inbox" },
    { id: "starred", icon: "bi-star" },
    { id: "sent", icon: "bi-send" },
    { id: "drafts", icon: "bi-file-earmark" },
    { id: "archive", icon: "bi-archive" },
    { id: "spam", icon: "bi-exclamation-circle" },
    { id: "trash", icon: "bi-trash" }
  ];

  const store = {
    get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch (e) {} }
  };

  const lang = window.LANG || "en";
  let theme = store.get("openmeru-theme") || "light";
  const page = document.body.dataset.page;

  function t(key) {
    return I18N[key] || key;
  }

  function applyChrome() {
    document.documentElement.lang = window.LANG_TAG || "en";
    document.documentElement.setAttribute("data-theme", theme);
    document.documentElement.setAttribute("data-bs-theme", theme);
    document.querySelectorAll("[data-i18n]").forEach((el) => { el.textContent = t(el.dataset.i18n); });
    document.querySelectorAll("[data-i18n-ph]").forEach((el) => { el.setAttribute("placeholder", t(el.dataset.i18nPh)); });
    document.querySelectorAll("[data-i18n-aria]").forEach((el) => { el.setAttribute("aria-label", t(el.dataset.i18nAria)); });
    document.querySelectorAll("[data-lang]").forEach((btn) => { btn.classList.toggle("active", btn.dataset.lang === lang); });
    document.querySelectorAll(".lang-code").forEach((el) => { el.textContent = lang.replace("_", "-").toUpperCase(); });
    if (page === "settings") document.title = t("accountSettings") + " · " + t("appName");
    document.querySelectorAll("[data-action='theme'] i").forEach((icon) => { icon.className = theme === "dark" ? "bi bi-sun" : "bi bi-moon"; });
    document.querySelectorAll("[data-action='theme']").forEach((btn) => {
      btn.setAttribute("aria-label", theme === "dark" ? t("themeToLight") : t("themeToDark"));
    });
    if (page === "login") document.title = t("appName");
    else if (page === "forgot") document.title = t("forgotTitle") + " · " + t("appName");
    else if (page === "reset") document.title = t("newPwTitle") + " · " + t("appName");
  }

  // The server renders every page in the chosen language, so a change reloads the page.
  function setLang(next) {
    if (next === lang) return;
    document.cookie = "openmeru_lang=" + encodeURIComponent(next) + "; path=/; max-age=31536000; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
    location.reload();
  }

  function setTheme(next) {
    theme = next;
    store.set("openmeru-theme", next);
    applyChrome();
    if (page === "inbox") renderReader();
  }

  document.addEventListener("click", (event) => {
    const langBtn = event.target.closest("[data-lang]");
    if (langBtn) return setLang(langBtn.dataset.lang);
    const themeBtn = event.target.closest("[data-action='theme']");
    if (themeBtn) setTheme(theme === "dark" ? "light" : "dark");
  });

  applyChrome();

  const csrf = (document.querySelector("meta[name='csrf-token']") || {}).content;
  // Where the webmail lives: "/" on a normal install, "/mail" on openmeru.com.
  const WEBMAIL = (document.querySelector("meta[name='webmail']") || {}).content || "/mail";

  async function api(method, url, body) {
    const headers = { Accept: "application/json", "X-CSRF-TOKEN": csrf, "X-Requested-With": "XMLHttpRequest" };
    let payload = body;
    if (body && !(body instanceof FormData)) {
      headers["Content-Type"] = "application/json";
      payload = JSON.stringify(body);
    }
    const res = await fetch(url, { method, headers, body: payload, credentials: "same-origin" });
    if ((res.status === 401 || res.status === 419) && page === "inbox") {
      location.href = WEBMAIL;
      throw new Error("auth");
    }
    let data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    if (res.status === 403 && data && data.message === "demo") {
      if (window.demoNotice) window.demoNotice(data.detail);
      else toast(data.detail || "Demo");
      const err = new Error("demo");
      err.status = 403;
      err.demo = true;
      throw err;
    }
    if (!res.ok) {
      const err = new Error((data && (data.detail || data.message)) || res.statusText);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  const validEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

  // Show/hide buttons next to any password field (account settings, admin forms, rows loaded later).
  document.addEventListener("click", (event) => {
    const eye = event.target.closest("[data-toggle-eye]");
    if (!eye) return;
    const input = eye.parentElement.querySelector("input");
    if (!input) return;
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    eye.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    eye.setAttribute("aria-label", show ? t("hidePw") : t("showPw"));
  });

  if (page === "login" || page === "forgot" || page === "reset") {
    const eye = document.querySelector("[data-action='toggle-password']");
    if (eye) {
      eye.addEventListener("click", () => {
        const input = document.querySelector("input[name='password']");
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        eye.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        eye.setAttribute("aria-label", show ? t("hidePw") : t("showPw"));
      });
    }
  }

  if (page === "login") {
    const form = document.getElementById("loginForm");
    const error = document.getElementById("formError");
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const email = form.email.value.trim();
      const password = form.password.value;
      if (!email) return (error.textContent = t("emailRequired"));
      if (!validEmail(email)) return (error.textContent = t("emailInvalid"));
      if (!password) return (error.textContent = t("passwordRequired"));
      error.textContent = "";
      const btn = form.querySelector("button[type='submit']");
      btn.disabled = true;
      try {
        const next = form.querySelector("input[name='next']");
        const data = await api("POST", "/login", { email, password, remember: true, next: next ? next.value : null });
        location.href = data.redirect || WEBMAIL;
      } catch (e) {
        error.textContent = e.status === 429 ? t("tooMany") : t("invalidLogin");
        btn.disabled = false;
      }
    });
  }

  if (page === "forgot") {
    const form = document.getElementById("forgotForm");
    const error = document.getElementById("formError");
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const email = form.email.value.trim();
      if (!email) return (error.textContent = t("emailRequired"));
      if (!validEmail(email)) return (error.textContent = t("emailInvalid"));
      error.textContent = "";
      try {
        await api("POST", "/forgot", { email });
        document.getElementById("resetForm").hidden = true;
        document.getElementById("resetDone").hidden = false;
      } catch (e) {
        error.textContent = e.status === 429 ? t("tooMany") : t("error");
      }
    });
  }

  if (page === "reset") {
    const form = document.getElementById("resetPwForm");
    const error = document.getElementById("formError");
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const body = {
        token: form.token.value,
        email: form.email.value.trim(),
        password: form.password.value,
        password_confirmation: form.password_confirmation.value
      };
      if (!validEmail(body.email)) return (error.textContent = t("emailInvalid"));
      if (body.password.length < 8) return (error.textContent = t("pwShort"));
      if (body.password !== body.password_confirmation) return (error.textContent = t("pwMismatch"));
      try {
        const data = await api("POST", "/reset", body);
        location.href = data.redirect || WEBMAIL;
      } catch (e) {
        error.textContent = t("resetFailed");
      }
    });
  }

  if (page !== "inbox" && page !== "settings") return;

  // ---- Shared by the inbox and the settings page ----
  const me = { email: document.body.dataset.email, name: document.body.dataset.name };
  const shell = document.querySelector(".shell");
  const pushSupported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
  let pushKey = null;
  const bellBadge = document.getElementById("bellBadge");
  const bellList = document.getElementById("bellList");
  const bellPush = document.getElementById("bellPush");

  bellPush.addEventListener("click", (event) => {
    if (event.target.closest("[data-push-on]")) enablePush().then(loadBell);
  });
  if (pushSupported) registration().catch(() => {});

  if (page === "settings") {
    settingsPage();
    return;
  }

  const state = {
    mode: "mail",
    folder: "inbox",
    messages: [],
    total: 0,
    counts: {},
    selectedId: null,
    detail: null,
    query: "",
    loading: false,
    draftId: null,
    replyToId: null,
    files: []
  };

  const navEl = document.getElementById("nav");
  const rowsEl = document.getElementById("rows");
  const readerEl = document.getElementById("reader");
  const rangeEl = document.getElementById("range");
  const searchEl = document.getElementById("search");

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1048576) return Math.round(bytes / 1024) + " KB";
    return (bytes / 1048576).toFixed(1) + " MB";
  }

  const PALETTE = ["#7c6cf0", "#7c3aed", "#2563eb", "#0891b2", "#059669", "#d97706", "#dc2626", "#db2777", "#4f46e5", "#0d9488"];

  function avatar(name, email) {
    const source = (name || email || "?").trim();
    const parts = source.replace(/<.*>/, "").split(/[\s.@_-]+/).filter(Boolean);
    const text = ((parts[0] || "?")[0] + (parts[1] ? parts[1][0] : "")).toUpperCase();
    let hash = 0;
    for (const ch of email || source) hash = (hash * 31 + ch.charCodeAt(0)) >>> 0;
    return { text, bg: PALETTE[hash % PALETTE.length] };
  }

  function displayList(list) {
    return (list || []).map((item) => item.replace(/\s*<[^>]+>/, "") || item).join(", ");
  }

  function bareAddress(item) {
    const match = /<([^>]+)>/.exec(item);
    return (match ? match[1] : item).trim().toLowerCase();
  }

  function folderName(id) {
    return t(id === "inbox" ? "inbox" : id);
  }

  function renderNav() {
    navEl.innerHTML = FOLDERS.map((folder) => {
      const c = state.counts[folder.id] || { total: 0, unread: 0 };
      const count = folder.id === "inbox" ? c.unread : folder.id === "drafts" ? c.total : folder.id === "spam" ? c.unread : "";
      const active = state.mode === "mail" && state.folder === folder.id ? " active" : "";
      return '<button class="nav-item' + active + '" data-action="folder" data-folder="' + folder.id + '"><i class="bi ' + folder.icon + '"></i><span>' + esc(folderName(folder.id)) + '</span><span class="count">' + (count || "") + "</span></button>";
    }).join("");
  }

  function renderList() {
    document.getElementById("listTitle").textContent = folderName(state.folder);
    const items = state.messages;
    rangeEl.textContent = items.length ? t("admin.showing").replace(":from", 1).replace(":to", items.length).replace(":total", state.total) : "";
    if (state.loading && !items.length) {
      rowsEl.innerHTML = '<div class="empty">' + esc(t("loading")) + "</div>";
      return;
    }
    if (!items.length) {
      rowsEl.innerHTML = '<div class="empty">' + esc(t("empty")) + "</div>";
      return;
    }
    const outgoing = state.folder === "sent" || state.folder === "drafts";
    rowsEl.innerHTML = items.map((m) => {
      const who = outgoing ? (t("to") + ": " + (displayList(m.to) || "—")) : (m.from_name || m.from_email || "?");
      const av = outgoing ? avatar(displayList(m.to), bareAddress((m.to || [""])[0] || "")) : avatar(m.from_name, m.from_email);
      const unread = !m.is_read && !outgoing;
      return (
        '<article class="mail-row' + (unread ? " unread" : "") + (m.id === state.selectedId ? " active" : "") + '" data-action="open" data-id="' + m.id + '">' +
        '<span class="unread-dot" aria-hidden="true"></span>' +
        '<span class="mono" style="background:' + av.bg + '">' + esc(av.text) + "</span>" +
        '<span class="row-main"><span class="row-top"><strong>' + esc(who) + "</strong><time>" + esc(formatWhen(m.date)) + "</time></span>" +
        '<span class="row-sub"><b>' + esc(m.subject || t("noSubject")) + "</b></span>" +
        '<span class="snippet">' + esc(m.snippet || "") + "</span></span>" +
        (m.has_attachments ? '<i class="bi bi-paperclip clip"></i>' : "<span></span>") +
        '<button class="star ' + (m.is_starred ? "on" : "") + '" data-action="star" data-id="' + m.id + '" aria-label="star"><i class="bi ' + (m.is_starred ? "bi-star-fill" : "bi-star") + '"></i></button>' +
        "</article>"
      );
    }).join("");
  }


  // ---- Translation: the browser's own translator first (Chrome), the server (Cloudflare) otherwise.
  const UI_LANG = String(window.LANG || "en").split(/[_-]/)[0];
  const TR_TARGETS = [...new Set([UI_LANG, "en", "pt", "es"])];
  const STOPWORDS = {
    en: "the and you your for with this that are have from will not our can more what about all just they their",
    pt: "que não uma para com você seu sua mais como dos das pelo isso está são também por nos foi ser tem",
    es: "que los las una para con por está usted más como del pero sus este esta también muy hay ser",
    fr: "les des une pour avec vous est pas que dans sur votre plus nous mais cette sont aux ont été",
    de: "der die und das ist nicht mit sie ich für auf ein eine den von dem sich auch wir sind",
    it: "che non una per con della sono questo anche più gli del alla nel come suo questa ogni",
    nl: "het een van en dat je niet voor met zijn op ook wij jouw meer deze naar bij wordt"
  };
  const STOPSETS = Object.fromEntries(Object.entries(STOPWORDS).map(([k, v]) => [k, new Set(v.split(" "))]));
  let langDetector = null;

  function withTimeout(promise, ms) {
    return Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error("timeout")), ms))]);
  }

  function guessLang(text) {
    const sample = text.slice(0, 4000);
    const letters = (sample.match(/\p{L}/gu) || []).length;
    if (!letters) return null;
    if ((sample.match(/[Ѐ-ӿ]/g) || []).length > letters * 0.3) return "ru";
    if ((sample.match(/[一-鿿]/g) || []).length > letters * 0.2) return "zh";
    const words = sample.toLowerCase().match(/\p{L}+/gu) || [];
    if (words.length < 6) return null;
    let best = null, top = 0, second = 0;
    for (const code in STOPSETS) {
      let score = 0;
      for (const w of words) if (STOPSETS[code].has(w)) score++;
      if (score > top) { second = top; top = score; best = code; } else if (score > second) second = score;
    }
    return top >= 3 && top > second * 1.3 ? best : null;
  }

  async function detectLang(text) {
    try {
      if ("LanguageDetector" in self) {
        if (!langDetector && (await withTimeout(LanguageDetector.availability(), 2500)) === "available") langDetector = await withTimeout(LanguageDetector.create(), 5000);
        if (langDetector) {
          const [first] = await withTimeout(langDetector.detect(text.slice(0, 3000)), 4000);
          if (first && first.confidence > 0.6 && first.detectedLanguage !== "und") return first.detectedLanguage.split("-")[0];
        }
      }
    } catch (e) {}
    return guessLang(text);
  }

  function langName(code) {
    try {
      const name = new Intl.DisplayNames([locale()], { type: "language" }).of(code);
      return name.charAt(0).toUpperCase() + name.slice(1);
    } catch (e) { return code; }
  }

  function messageText(m) {
    return (m.subject || "") + "\n" + (m.text_body || (m.html_body ? new DOMParser().parseFromString(m.html_body, "text/html").body.innerText : ""));
  }

  async function translateWith(segments, from, to) {
    if ("Translator" in self) {
      try {
        if ((await withTimeout(Translator.availability({ sourceLanguage: from, targetLanguage: to }), 3000)) !== "unavailable") {
          // The first use may download the language model, so creating can take a while.
          const tr = await withTimeout(Translator.create({ sourceLanguage: from, targetLanguage: to }), 90000);
          const out = [];
          for (const s of segments) out.push(/\p{L}/u.test(s) ? await withTimeout(tr.translate(s), 20000) : s);
          return out;
        }
      } catch (e) {}
    }
    const data = await api("POST", "/api/translate", { source: from, target: to, segments });
    return data.translations;
  }

  function trBar(mode, info) {
    const bar = document.getElementById("translateBar");
    if (!bar) return;
    const from = info && info.from ? langName(info.from) : "";
    const to = info && info.to ? langName(info.to) : "";
    let html = '<i class="bi bi-translate"></i> ';
    if (mode === "offer") {
      html += "<span>" + esc(t("trDetected").replace(":lang", from)) + '</span><button type="button" data-tr="' + esc(info.to) + '">' + esc(t("trTo").replace(":lang", to)) + "</button>";
    } else if (mode === "working") {
      html += "<span>" + esc(t("trWorking")) + "</span>";
    } else if (mode === "done") {
      html += "<span>" + esc(t("trDone").replace(":from", from).replace(":to", to)) + '</span><button type="button" data-tr-original>' + esc(t("trOriginal")) + "</button>";
    } else if (mode === "same") {
      html += "<span>" + esc(t("trSame").replace(":lang", to)) + "</span>";
    } else {
      html += "<span>" + esc(info && info.unavailable ? t("trUnavailable") : t("trFailed")) + "</span>";
    }
    bar.innerHTML = html;
    bar.hidden = false;
  }

  async function offerTranslation(m) {
    const from = await detectLang(messageText(m));
    if (state.detail !== m) return;
    m._lang = from;
    if (from && from !== UI_LANG) trBar("offer", { from, to: UI_LANG });
  }

  function keepSpace(original, translated) {
    const lead = (original.match(/^\s*/) || [""])[0];
    const trail = (original.match(/\s*$/) || [""])[0];
    return lead + String(translated).trim() + trail;
  }

  async function runTranslation(to) {
    const m = state.detail;
    if (!m) return;
    const from = m._lang || (await detectLang(messageText(m))) || "en";
    if (from === to) return trBar("same", { to });
    trBar("working", { from, to });
    try {
      const subjectEl = readerEl.querySelector(".subject-row h2");
      const body = document.getElementById("letterBody");
      const frame = body && body.querySelector("iframe");
      const doc = frame && frame.contentDocument;
      let nodes = [];
      let segments;
      if (doc && doc.body) {
        const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, {
          acceptNode: (n) => /\p{L}/u.test(n.nodeValue) && !/^(SCRIPT|STYLE|NOSCRIPT|TITLE)$/.test(n.parentNode.nodeName) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT
        });
        while (walker.nextNode() && nodes.length < 299) nodes.push(walker.currentNode);
        segments = nodes.map((n) => n.nodeValue);
      } else {
        segments = String(m.text_body || "").split("\n").slice(0, 299);
      }
      const out = await translateWith([m.subject || ""].concat(segments), from, to);
      if (state.detail !== m) return;
      if (subjectEl && m.subject) subjectEl.textContent = out[0];
      if (nodes.length) {
        nodes.forEach((n, i) => { n.nodeValue = keepSpace(n.nodeValue, out[i + 1]); });
        try { frame.style.height = doc.documentElement.scrollHeight + 8 + "px"; } catch (e) {}
      } else if (body) {
        const rest = String(m.text_body || "").split("\n").slice(299);
        body.innerHTML = '<div style="white-space:pre-wrap;line-height:1.6;font-size:15px">' + textToHtml(out.slice(1).concat(rest).join("\n")) + "</div>";
      }
      trBar("done", { from, to });
    } catch (e) {
      if (state.detail === m) trBar("error", { unavailable: e.data && e.data.message === "translate_unconfigured" });
    }
  }

  readerEl.addEventListener("click", (event) => {
    const pick = event.target.closest("[data-tr]");
    if (pick) {
      event.preventDefault();
      readerEl.querySelectorAll(".tr-pop").forEach((p) => (p.hidden = true));
      return runTranslation(pick.dataset.tr);
    }
    if (event.target.closest("[data-tr-original]")) {
      const m = state.detail;
      renderReader();
      if (m && m._lang && m._lang !== UI_LANG) trBar("offer", { from: m._lang, to: UI_LANG });
      return;
    }
    const menu = event.target.closest("[data-tr-menu]");
    if (menu) {
      const pop = menu.parentElement.querySelector(".tr-pop");
      pop.hidden = !pop.hidden;
      return;
    }
    readerEl.querySelectorAll(".tr-pop").forEach((p) => (p.hidden = true));
  });


  function letterFrame(html) {
    const dark = theme === "dark";
    const doc =
      '<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'; object-src \'none\'; frame-src \'none\'">' +
      '<base target="_blank"><style>html,body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1b2230;background:#fff;word-wrap:break-word}img{max-width:100%;height:auto}' +
      (dark ? "body{border-radius:10px;padding:14px}" : "") + "</style></head><body>" + html + "</body></html>";
    const frame = document.createElement("iframe");
    frame.className = "letter-frame";
    frame.setAttribute("sandbox", "allow-same-origin allow-popups allow-popups-to-escape-sandbox");
    frame.setAttribute("referrerpolicy", "no-referrer");
    frame.style.cssText = "width:100%;border:0;min-height:120px;display:block;background:transparent";
    frame.srcdoc = doc;
    frame.addEventListener("load", () => {
      const fit = () => {
        try { frame.style.height = frame.contentDocument.documentElement.scrollHeight + 8 + "px"; } catch (e) { frame.style.height = "70vh"; }
      };
      fit();
      setTimeout(fit, 400);
      setTimeout(fit, 1500);
    });
    return frame;
  }

  function textToHtml(text) {
    return esc(text || "").replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  function renderReader() {
    const m = state.detail;
    if (!m || m.id !== state.selectedId) {
      readerEl.innerHTML = '<div class="empty">' + esc(state.selectedId ? t("loading") : t("pick")) + "</div>";
      return;
    }
    const outgoing = m.folder === "sent" || m.folder === "drafts";
    const av = avatar(m.from_name, m.from_email);
    const bannerKey = { drafts: "draftBanner", spam: "spamBanner", trash: "trashBanner", archive: "archiveBanner" }[m.folder];
    const banner = bannerKey ? '<div class="banner">' + esc(t(bannerKey)) + "</div>" : "";
    const toLine = outgoing || (m.to || []).length > 1 || !(m.to || []).some((a) => bareAddress(a) === me.email)
      ? esc(t("to")) + " " + esc(displayList(m.to)) + ((m.cc || []).length ? " · " + esc(t("cc")) + " " + esc(displayList(m.cc)) : "")
      : esc(t("toMe")) + ((m.cc || []).length ? " · " + esc(t("cc")) + " " + esc(displayList(m.cc)) : "");
    const files = (m.attachments || []).map((a) =>
      '<a class="file" href="' + esc(a.url) + '" target="_blank" rel="noopener" style="text-decoration:none;color:inherit;display:flex;gap:10px;align-items:center;margin-bottom:8px">' +
      '<i class="bi bi-file-earmark' + (/pdf/.test(a.mime || "") ? "-pdf" : /image/.test(a.mime || "") ? "-image" : "") + '"></i>' +
      "<div><b>" + esc(a.filename) + "</b><span>" + esc(formatSize(a.size)) + "</span></div></a>"
    ).join("");
    const inTrash = m.folder === "trash";
    const tools =
      '<button class="tool-btn back-read" data-action="back" aria-label="' + esc(t("close")) + '" style="display:none"><i class="bi bi-arrow-left"></i></button>' +
      (m.folder === "inbox" ? '<button class="tool-btn" data-action="move" data-folder="archive" title="' + esc(t("archive")) + '"><i class="bi bi-archive"></i></button>' : "") +
      (["archive", "spam", "trash"].includes(m.folder) ? '<button class="tool-btn" data-action="move" data-folder="inbox" title="' + esc(t("moveInbox")) + '"><i class="bi bi-inbox"></i></button>' : "") +
      (m.folder === "inbox" ? '<button class="tool-btn" data-action="move" data-folder="spam" title="' + esc(t("markSpam")) + '"><i class="bi bi-exclamation-octagon"></i></button>' : "") +
      '<button class="tool-btn" data-action="delete-one" title="' + esc(inTrash || m.folder === "drafts" ? t("deleteForever") : t("del")) + '"><i class="bi bi-trash"></i></button>' +
      (!outgoing ? '<button class="tool-btn" data-action="unread-one" title="' + esc(t("markUnread")) + '"><i class="bi bi-envelope"></i></button>' : "") +
      '<span class="tr-menu"><button class="tool-btn" type="button" data-tr-menu title="' + esc(t("translate")) + '"><i class="bi bi-translate"></i></button><span class="tr-pop" hidden>' +
      TR_TARGETS.map((c) => '<button type="button" data-tr="' + c + '">' + esc(langName(c)) + "</button>").join("") + "</span></span>" +
      '<span class="range"></span>' +
      '<button class="tool-btn" data-action="prev" aria-label="prev"><i class="bi bi-chevron-left"></i></button>' +
      '<button class="tool-btn" data-action="next" aria-label="next"><i class="bi bi-chevron-right"></i></button>';

    readerEl.innerHTML =
      '<div class="read-tools">' + tools + "</div>" +
      '<div class="read-body">' +
      '<div class="subject-row"><h2>' + esc(m.subject || t("noSubject")) + '</h2><button class="star ' + (m.is_starred ? "on" : "") + '" data-action="star" data-id="' + m.id + '"><i class="bi ' + (m.is_starred ? "bi-star-fill" : "bi-star") + '"></i></button></div>' +
      '<div class="sender"><span class="mono" style="background:' + av.bg + '">' + esc(av.text) + "</span>" +
      '<div><div class="who-name">' + esc(m.from_name || m.from_email) + (m.from_name ? " &lt;" + esc(m.from_email) + "&gt;" : "") + '</div><div class="who-mail">' + toLine + "</div></div>" +
      "<time>" + esc(formatWhen(m.date, true)) + "</time></div>" +
      '<div class="letter"><div class="translate-bar" id="translateBar" hidden></div>' + banner + '<div id="letterBody"></div>' + (files ? '<div style="margin-top:16px">' + files + "</div>" : "") + "</div></div>" +
      '<div class="replies">' +
      (m.folder === "drafts" ? '<button type="button" data-action="edit-draft"><i class="bi bi-pencil"></i> ' + esc(t("continueDraft")) + "</button>" :
        '<button type="button" data-action="reply"><i class="bi bi-reply"></i> ' + esc(t("reply")) + "</button>" +
        '<button type="button" data-action="reply-all"><i class="bi bi-reply-all"></i> ' + esc(t("replyAll")) + "</button>" +
        '<button type="button" data-action="forward"><i class="bi bi-forward"></i> ' + esc(t("forward")) + "</button>") +
      "</div>";

    const body = document.getElementById("letterBody");
    if (m.html_body) {
      body.appendChild(letterFrame(m.html_body));
    } else {
      body.innerHTML = '<div style="white-space:pre-wrap;line-height:1.6;font-size:15px">' + textToHtml(m.text_body) + "</div>";
    }
    offerTranslation(m);
    if (window.matchMedia("(max-width: 1100px)").matches) {
      const back = readerEl.querySelector(".back-read");
      if (back) back.style.display = "grid";
    }
  }

  function renderGreeting() {
    const el = document.getElementById("greeting");
    if (!el) return;
    const hour = new Date().getHours();
    const key = hour >= 5 && hour < 12 ? "goodMorning" : hour >= 12 && hour < 19 ? "goodAfternoon" : "goodEvening";
    const first = (me.name || "").trim().split(/\s+/)[0] || me.email;
    const today = new Intl.DateTimeFormat(locale(), { weekday: "long", day: "numeric", month: "long" }).format(new Date());
    el.innerHTML = esc(t(key)) + ", " + esc(first) + " 👋<span>" + esc(today) + "</span>";
  }

  function render() {
    applyChrome();
    renderGreeting();
    shell.classList.toggle("composing", state.mode === "compose");
    renderNav();
    const unread = (state.counts.inbox || {}).unread || 0;
    if (state.mode === "compose") {
      document.getElementById("composeHeading").textContent = state.draftId ? t("editDraft") : t("newMessage");
      document.title = (state.draftId ? t("editDraft") : t("newMessage")) + " · " + t("appName");
      return;
    }
    document.title = (unread ? "(" + unread + ") " : "") + folderName(state.folder) + " · " + t("appName");
    renderList();
    renderReader();
  }

  let listToken = 0;
  async function loadList(keepSelection) {
    const token = ++listToken;
    state.loading = true;
    if (!keepSelection) {
      state.messages = [];
      renderList();
    }
    try {
      const params = new URLSearchParams({ folder: state.folder });
      if (state.query.trim()) params.set("q", state.query.trim());
      const data = await api("GET", "/api/messages?" + params.toString());
      if (accountChanged(data)) return;
      if (token !== listToken) return;
      const before = (state.counts.inbox || {}).unread || 0;
      state.messages = data.data;
      state.total = data.total;
      state.counts = data.counts;
      const after = (state.counts.inbox || {}).unread || 0;
      if (keepSelection && after > before) toast(t("newMail"));
      if (!keepSelection) {
        state.selectedId = null;
        state.detail = null;
      }
    } catch (e) {
      if (e.message !== "auth") toast(t("error"));
    } finally {
      state.loading = false;
      if (token === listToken && state.mode === "mail") {
        const unread = (state.counts.inbox || {}).unread || 0;
        document.title = (unread ? "(" + unread + ") " : "") + folderName(state.folder) + " · " + t("appName");
        renderNav();
        renderList();
        if (!keepSelection) renderReader();
      }
    }
  }

  async function openMessage(id) {
    state.selectedId = id;
    state.detail = null;
    shell.classList.add("reading");
    const row = state.messages.find((m) => m.id === id);
    if (row) row.is_read = true;
    render();
    try {
      const detail = await api("GET", "/api/messages/" + id);
      if (state.selectedId !== id) return;
      state.detail = detail;
      renderReader();
      const data = await api("GET", "/api/me");
      state.counts = data.counts;
      renderNav();
      loadBell();
      document.title = folderName(state.folder) + " · " + t("appName");
    } catch (e) {
      if (e.message !== "auth") toast(t("error"));
    }
  }

  async function patch(id, fields, note) {
    const data = await api("PATCH", "/api/messages/" + id, fields);
    state.counts = data.counts;
    if (note) toast(note);
  }

  function removeFromList(id) {
    const index = state.messages.findIndex((m) => m.id === id);
    state.messages = state.messages.filter((m) => m.id !== id);
    state.total = Math.max(0, state.total - 1);
    const next = state.messages[index] || state.messages[index - 1];
    if (next) {
      openMessage(next.id);
    } else {
      state.selectedId = null;
      state.detail = null;
      shell.classList.remove("reading");
      render();
    }
  }

  function renderFiles() {
    document.getElementById("composeFiles").innerHTML = state.files.map((file, i) =>
      '<span class="tag" style="margin:0 6px 6px 0;display:inline-flex;gap:6px;align-items:center"><i class="bi bi-paperclip"></i>' + esc(file.name) + " (" + esc(formatSize(file.size)) + ')<button type="button" data-action="drop-file" data-index="' + i + '" aria-label="' + esc(t("close")) + '">×</button></span>'
    ).join("");
  }

  // ---- Rich-text body (Quill). Falls back to the plain textarea if Quill did not load. ----
  let editor = null;
  function setupEditor() {
    if (editor || !window.Quill) return editor;
    const holder = document.getElementById("composeEditor");
    holder.hidden = false;
    document.getElementById("composeBody").hidden = true;
    editor = new window.Quill(holder, {
      theme: "snow",
      placeholder: t("message"),
      modules: {
        toolbar: [
          [{ header: [1, 2, false] }],
          ["bold", "italic", "underline", "strike"],
          [{ color: [] }, { background: [] }],
          [{ list: "ordered" }, { list: "bullet" }],
          ["blockquote", "link"],
          [{ align: [] }],
          ["clean"]
        ]
      }
    });
    return editor;
  }

  function setBody(text, html) {
    if (!setupEditor()) {
      document.getElementById("composeBody").value = text || "";
      return;
    }
    editor.setContents([]);
    if (html) editor.clipboard.dangerouslyPasteHTML(0, html);
    else if (text) editor.setText(text);
    editor.setSelection(0, 0);
  }

  function getBody() {
    if (!editor) return { text: document.getElementById("composeBody").value, html: "" };
    const text = editor.getText().replace(/\n$/, "");
    return { text, html: text.trim() ? editor.getSemanticHTML() : "" };
  }

  function quoteHtml(m) {
    const who = m.from_name ? m.from_name + " <" + m.from_email + ">" : m.from_email;
    const text = m.text_body || (m.html_body ? new DOMParser().parseFromString(m.html_body, "text/html").body.innerText : "");
    return "<p><br></p><p>" + esc(formatWhen(m.date, true) + ", " + who + " " + t("wrote")) + "</p><blockquote>" +
      esc(text).replace(/\n/g, "<br>") + "</blockquote>";
  }

  function openCompose(prefill) {
    const data = prefill || {};
    state.mode = "compose";
    state.draftId = data.draftId || null;
    state.replyToId = data.replyToId || null;
    state.files = [];
    document.getElementById("composeTo").value = data.to || "";
    document.getElementById("composeCc").value = data.cc || "";
    document.getElementById("composeBcc").value = data.bcc || "";
    showCopyLine("cc", !!data.cc);
    showCopyLine("bcc", !!data.bcc);
    document.getElementById("composeSubject").value = data.subject || "";
    setBody(data.body || "", data.html || "");
    document.getElementById("composeError").textContent = "";
    renderFiles();
    shell.classList.remove("reading", "nav-open");
    render();
    if (data.to && editor) {
      editor.focus();
      editor.setSelection(0, 0);
    } else if (data.to) {
      const area = document.getElementById("composeBody");
      area.focus();
      area.setSelectionRange(0, 0);
    } else {
      document.getElementById("composeTo").focus();
    }
  }

  // Cc and Bcc stay folded behind small toggles on the To line until needed.
  function showCopyLine(kind, show) {
    document.getElementById(kind + "Line").hidden = !show;
    const toggle = document.querySelector("[data-action='show-" + kind + "']");
    if (toggle) toggle.hidden = show;
  }

  function closeCompose() {
    state.mode = "mail";
    state.draftId = null;
    state.replyToId = null;
    state.files = [];
    render();
  }

  function composeForm() {
    const form = new FormData();
    form.append("to", document.getElementById("composeTo").value);
    form.append("cc", document.getElementById("composeCc").value);
    form.append("bcc", document.getElementById("composeBcc").value);
    form.append("subject", document.getElementById("composeSubject").value);
    const body = getBody();
    form.append("body", body.text);
    form.append("body_html", body.html);
    if (state.draftId) form.append("draft_id", state.draftId);
    if (state.replyToId) form.append("reply_to_id", state.replyToId);
    return form;
  }

  function composeErrorText(e) {
    const errors = e.data && e.data.errors;
    if (errors) {
      const first = Object.values(errors)[0][0] || "";
      if (first === "needTo") return t("needTo");
      if (first.startsWith("invalid:")) return t("invalidAddress") + first.slice(8);
      return first;
    }
    return t("sendFailed") + e.message;
  }

  async function sendCompose() {
    const errorEl = document.getElementById("composeError");
    const to = document.getElementById("composeTo").value.trim();
    const cc = document.getElementById("composeCc").value.trim();
    const bcc = document.getElementById("composeBcc").value.trim();
    if (!to && !cc && !bcc) return (errorEl.textContent = t("needTo"));
    errorEl.textContent = "";
    const btn = document.getElementById("sendBtn");
    btn.disabled = true;
    btn.querySelector("span").textContent = t("sending");
    const form = composeForm();
    state.files.forEach((file) => form.append("attachments[]", file));
    try {
      const data = await api("POST", "/api/send", form);
      state.counts = data.counts;
      toast(t("sentOk"));
      closeCompose();
      if (state.folder === "sent" || state.folder === "drafts") loadList();
    } catch (e) {
      if (e.demo) errorEl.textContent = "";
      else if (e.message !== "auth") errorEl.textContent = composeErrorText(e);
    } finally {
      btn.disabled = false;
      btn.querySelector("span").textContent = t("send");
    }
  }

  async function saveDraft() {
    try {
      const data = await api("POST", "/api/drafts", composeForm());
      state.counts = data.counts;
      toast(t("draftSaved"));
      closeCompose();
      if (state.folder === "drafts") loadList();
    } catch (e) {
      if (e.message !== "auth") document.getElementById("composeError").textContent = composeErrorText(e);
    }
  }

  function quote(m) {
    const who = m.from_name ? m.from_name + " <" + m.from_email + ">" : m.from_email;
    const text = m.text_body || (m.html_body ? new DOMParser().parseFromString(m.html_body, "text/html").body.innerText : "");
    return "\n\n" + formatWhen(m.date, true) + ", " + who + " " + t("wrote") + "\n" + text.split("\n").map((line) => "> " + line).join("\n");
  }

  function prefixed(prefix, subject) {
    const s = subject || "";
    return new RegExp("^" + prefix.trim() + "\\s", "i").test(s) ? s : prefix + s;
  }

  function step(direction) {
    const index = state.messages.findIndex((m) => m.id === state.selectedId);
    const next = state.messages[index + direction];
    if (next) openMessage(next.id);
  }

  shell.addEventListener("click", async (event) => {
    const el = event.target.closest("[data-action]");
    if (!el) return;
    const action = el.dataset.action;
    const id = Number(el.dataset.id);
    const m = state.detail;
    try {
      if (action === "folder") {
        state.mode = "mail";
        state.folder = el.dataset.folder;
        shell.classList.remove("nav-open", "reading");
        render();
        loadList();
      } else if (action === "open") {
        openMessage(id);
      } else if (action === "star") {
        event.stopPropagation();
        const row = state.messages.find((item) => item.id === id);
        const next = !(row ? row.is_starred : m && m.is_starred);
        if (row) row.is_starred = next;
        if (m && m.id === id) m.is_starred = next;
        render();
        await patch(id, { is_starred: next });
        renderNav();
      } else if (action === "back") {
        shell.classList.remove("reading");
      } else if (action === "menu") {
        shell.classList.add("nav-open");
      } else if (action === "prev") {
        step(-1);
      } else if (action === "next") {
        step(1);
      } else if (action === "move" && m) {
        const folder = el.dataset.folder;
        await patch(m.id, { folder }, folder === "archive" ? t("archived") : t("moved"));
        removeFromList(m.id);
      } else if (action === "delete-one" && m) {
        const forever = m.folder === "trash" || m.folder === "drafts";
        const data = await api("DELETE", "/api/messages/" + m.id);
        state.counts = data.counts;
        toast(forever ? t("deleted") : t("movedTrash"));
        removeFromList(m.id);
      } else if (action === "unread-one" && m) {
        await patch(m.id, { is_read: false });
        const row = state.messages.find((item) => item.id === m.id);
        if (row) row.is_read = false;
        state.selectedId = null;
        state.detail = null;
        shell.classList.remove("reading");
        render();
      } else if ((action === "reply" || action === "reply-all") && m) {
        const sentByMe = m.folder === "sent";
        const primary = sentByMe ? (m.to || []).map(bareAddress) : [bareAddress(m.reply_to || m.from_email)];
        let cc = [];
        if (action === "reply-all") {
          const everyone = (sentByMe ? m.cc || [] : (m.to || []).concat(m.cc || [])).map(bareAddress);
          cc = everyone.filter((addr) => addr !== me.email && !primary.includes(addr));
        }
        openCompose({
          to: primary.join(", "),
          cc: cc.join(", "),
          subject: prefixed("Re: ", m.subject),
          body: quote(m),
          html: window.Quill ? quoteHtml(m) : "",
          replyToId: m.id
        });
      } else if (action === "forward" && m) {
        const header = "\n\n" + t("forwarded") + "\n" + t("from") + ": " + (m.from_name ? m.from_name + " <" + m.from_email + ">" : m.from_email) +
          "\n" + t("subject") + ": " + (m.subject || "") + "\n" + t("to") + ": " + displayList(m.to) + "\n\n";
        const text = m.text_body || (m.html_body ? new DOMParser().parseFromString(m.html_body, "text/html").body.innerText : "");
        const fwdHtml = window.Quill
          ? "<p><br></p><p>" + esc(header.trim()).replace(/\n/g, "<br>") + "</p>" + (m.html_body ? m.html_body : "<p>" + esc(text).replace(/\n/g, "<br>") + "</p>")
          : "";
        openCompose({ subject: prefixed("Fwd: ", m.subject), body: header + text, html: fwdHtml });
      } else if (action === "edit-draft" && m) {
        openCompose({
          draftId: m.id,
          to: (m.to || []).join(", "),
          cc: (m.cc || []).join(", "),
          bcc: (m.bcc || []).join(", "),
          subject: m.subject || "",
          body: m.text_body || "",
          html: m.html_body || ""
        });
      } else if (action === "show-cc" || action === "show-bcc") {
        event.preventDefault();
        const kind = action === "show-cc" ? "cc" : "bcc";
        showCopyLine(kind, true);
        document.getElementById(kind === "cc" ? "composeCc" : "composeBcc").focus();
      } else if (action === "save-draft") {
        saveDraft();
      } else if (action === "discard") {
        closeCompose();
      } else if (action === "drop-file") {
        state.files.splice(Number(el.dataset.index), 1);
        renderFiles();
      } else if (action === "refresh") {
        loadList();
      }
    } catch (e) {
      if (e.message !== "auth") toast(t("error"));
    }
  });

  document.querySelector("[data-action='compose']").addEventListener("click", () => openCompose());
  document.getElementById("backdrop").addEventListener("click", () => shell.classList.remove("nav-open"));
  document.getElementById("composeAttach").addEventListener("change", (event) => {
    state.files = state.files.concat(Array.from(event.target.files || []));
    event.target.value = "";
    renderFiles();
  });

  let searchTimer = null;
  searchEl.addEventListener("input", () => {
    state.query = searchEl.value;
    if (state.mode === "compose") return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadList(), 300);
  });

  document.getElementById("composeForm").addEventListener("submit", (event) => {
    event.preventDefault();
    sendCompose();
  });

  bellList.addEventListener("click", (event) => {
    const item = event.target.closest("[data-open]");
    if (!item) return;
    const id = Number(item.dataset.open);
    state.mode = "mail";
    if (state.folder !== "inbox") {
      state.folder = "inbox";
      loadList().then(() => openMessage(id));
    } else {
      openMessage(id);
    }
    bootstrap.Dropdown.getOrCreateInstance(document.getElementById("bellBtn")).hide();
  });

  if (pushSupported) {
    navigator.serviceWorker.addEventListener("message", (event) => {
      if (event.data && event.data.type === "open") {
        const id = Number(new URL(event.data.url).searchParams.get("open"));
        if (id) {
          state.folder = "inbox";
          loadList().then(() => openMessage(id));
        }
      }
    });
  }

  // New mail shows up without a reload: a push from the service worker
  // refreshes at once, and a short poll covers devices without push.
  function refreshMail() {
    loadBell();
    if (state.mode !== "mail" || state.query) return;
    loadList(true);
  }
  setInterval(() => {
    if (!document.hidden) refreshMail();
  }, 15000);
  setInterval(renderGreeting, 60000);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) refreshMail();
  });
  if (pushSupported) {
    navigator.serviceWorker.addEventListener("message", (event) => {
      if (event.data && event.data.type === "new-mail") refreshMail();
    });
  }

  render();
  const opening = Number(new URLSearchParams(location.search).get("open"));
  loadList().then(() => {
    if (opening) {
      history.replaceState(null, "", WEBMAIL);
      openMessage(opening);
    }
  });
  loadBell().then(autoPush);


  // Another tab (or the dashboard link) signed this browser into a different mailbox:
  // reload so the header, the strip and the read-only mode match the account in use.
  function accountChanged(data) {
    if (data && data.account && me.email && data.account.toLowerCase() !== me.email.toLowerCase()) {
      location.reload();
      return true;
    }
    return false;
  }

  // ---- Bell: unread inbox messages (shared) ----
  async function loadBell() {
    try {
      const data = await api("GET", "/api/notifications");
      if (accountChanged(data)) return;
      pushKey = data.push_key;
      bellBadge.hidden = !data.unread;
      bellBadge.textContent = data.unread > 99 ? "99+" : data.unread;
      bellList.innerHTML = data.items.length
        ? data.items.map((m) => '<button type="button" class="bell-item" data-open="' + m.id + '"><b>' + esc(m.from_name || m.from_email) + "</b><span>" + esc(m.subject || t("noSubject")) + " · " + esc(formatWhen(m.date)) + "</span></button>").join("")
        : '<div class="bell-empty">' + esc(t("noNotifications")) + "</div>";
      const status = await pushStatus();
      bellPush.innerHTML = status === "off" && pushKey ? '<button type="button" data-push-on>' + esc(t("turnOnPush")) + "</button>" : "";
    } catch (e) {}
  }

  // ---- Web Push (shared) ----
  function urlBase64ToUint8Array(base64) {
    const padding = "=".repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, "+").replace(/_/g, "/"));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
  }

  async function registration() {
    return navigator.serviceWorker.register("/sw.js");
  }

  async function pushStatus() {
    if (!pushSupported) return "unsupported";
    if (Notification.permission === "denied") return "blocked";
    try {
      const reg = await navigator.serviceWorker.getRegistration("/");
      const sub = reg && (await reg.pushManager.getSubscription());
      return sub ? "on" : "off";
    } catch (e) {
      return "off";
    }
  }

  async function subscribe() {
    const reg = await registration();
    await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
      sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(pushKey) });
    }
    await api("POST", "/api/push/subscribe", sub.toJSON());
  }

  async function enablePush() {
    if (!pushSupported) return toast(t("pushUnsupported"));
    if (!pushKey) return toast(t("pushNotReady"));
    const permission = await Notification.requestPermission();
    if (permission !== "granted") return toast(t("pushBlocked"));
    await subscribe();
    toast(t("pushOn"));
  }

  async function disablePush() {
    const reg = await navigator.serviceWorker.getRegistration("/");
    const sub = reg && (await reg.pushManager.getSubscription());
    if (sub) {
      await api("POST", "/api/push/unsubscribe", { endpoint: sub.endpoint });
      await sub.unsubscribe();
    }
    store.set("openmeru-push-off", "1");
    toast(t("pushOff"));
  }

  // Notifications switch on by themselves: the browser asks once, on the first
  // visit after sign-in; with permission already given the device subscribes silently.
  async function autoPush() {
    if (!pushSupported || !pushKey || store.get("openmeru-push-off")) return;
    try {
      if (Notification.permission === "granted") {
        if ((await pushStatus()) === "off") await subscribe();
      } else if (Notification.permission === "default" && !store.get("openmeru-push-asked")) {
        store.set("openmeru-push-asked", "1");
        if ((await Notification.requestPermission()) === "granted") {
          await subscribe();
          toast(t("pushOn"));
        }
      }
      loadBell();
    } catch (e) {}
  }

  function toast(text) {
    const node = document.createElement("div");
    node.className = "toast-note";
    node.textContent = text;
    document.body.appendChild(node);
    setTimeout(() => node.remove(), 2800);
  }

  function locale() {
    return window.LANG_TAG || "en-GB";
  }

  function formatWhen(iso, full) {
    if (!iso) return "";
    const date = new Date(iso);
    if (full) return new Intl.DateTimeFormat(locale(), { dateStyle: "medium", timeStyle: "short" }).format(date);
    const now = new Date();
    if (date.toDateString() === now.toDateString()) {
      return new Intl.DateTimeFormat(locale(), { hour: "2-digit", minute: "2-digit" }).format(date);
    }
    if (date.getFullYear() === now.getFullYear()) {
      return new Intl.DateTimeFormat(locale(), { month: "short", day: "numeric" }).format(date);
    }
    return new Intl.DateTimeFormat(locale(), { dateStyle: "short" }).format(date);
  }

  function esc(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));
  }

  // ---- Settings page (/settings) ----
  function settingsPage() {
    const errorEl = document.getElementById("settingsError");
    const tabs = ["profile", "password", "forwarding", "notify", "delete"];

    function showTab(tab) {
      if (!tabs.includes(tab)) tab = "profile";
      document.querySelectorAll("[data-tab]").forEach((b) => b.classList.toggle("active", b.dataset.tab === tab));
      document.querySelectorAll("[data-pane]").forEach((p) => { p.hidden = p.dataset.pane !== tab; });
      errorEl.textContent = "";
      if (tab === "notify") renderNotifyState();
    }

    async function renderNotifyState() {
      const status = await pushStatus();
      const key = { on: "pushOn", off: "pushOff", blocked: "pushBlocked", unsupported: "pushUnsupported" }[status];
      document.getElementById("notifyState").textContent = !pushKey && status !== "unsupported" ? t("pushNotReady") : t(key);
      document.getElementById("pushOn").hidden = status === "on" || status === "unsupported";
      document.getElementById("pushOff").hidden = status !== "on";
      document.getElementById("pushTest").hidden = status !== "on";
    }

    function errorText(e) {
      const errors = e.data && e.data.errors;
      if (!errors) return e.status === 429 ? t("tooMany") : t("error");
      const first = Object.values(errors)[0][0] || "";
      if (first === "wrongPassword") return t("wrongPassword");
      if (first === "adminCannotDelete") return t("adminCannotDelete");
      if (errors.password) return /confirm/i.test(first) ? t("pwMismatch") : t("pwShort");
      if (errors.forward_to) return t("forwardOwnDomain");
      return first;
    }

    window.addEventListener("hashchange", () => showTab(location.hash.slice(1)));
    showTab(location.hash.slice(1));

    document.querySelector("[data-action='menu']").addEventListener("click", () => shell.classList.add("nav-open"));
    document.getElementById("backdrop").addEventListener("click", () => shell.classList.remove("nav-open"));
    bellList.addEventListener("click", (event) => {
      const item = event.target.closest("[data-open]");
      if (item) location.href = "/mail?open=" + item.dataset.open;
    });

    // Profile photo
    const avatarInput = document.getElementById("avatarInput");
    const removeBtn = document.getElementById("avatarRemove");
    function showAvatar(url) {
      const html = url ? '<img class="avatar-img" src="' + esc(url) + '" alt="">' : '<span class="avatar-initial">' + esc((me.name || "?").charAt(0).toUpperCase()) + "</span>";
      document.getElementById("avatarBig").innerHTML = html;
      document.querySelectorAll(".avatar-btn").forEach((btn) => { btn.innerHTML = html; });
      removeBtn.hidden = !url;
    }
    avatarInput.addEventListener("change", async () => {
      const file = avatarInput.files[0];
      avatarInput.value = "";
      if (!file) return;
      const form = new FormData();
      form.append("avatar", file);
      try {
        const data = await api("POST", "/api/account/avatar", form);
        showAvatar(data.url);
        toast(t("photoSaved"));
      } catch (e) {
        errorEl.textContent = e.status === 422 ? t("photoHint") : t("error");
      }
    });
    removeBtn.addEventListener("click", async () => {
      try {
        await api("DELETE", "/api/account/avatar");
        showAvatar(null);
      } catch (e) {
        toast(t("error"));
      }
    });

    document.getElementById("profileForm").addEventListener("submit", async (event) => {
      event.preventDefault();
      const f = event.target;
      try {
        await api("PUT", "/api/account/profile", { name: f.name.value.trim(), recovery_email: f.recovery_email.value.trim() || null, signature: f.signature.value });
        me.name = f.name.value.trim();
        document.getElementById("whoName").textContent = me.name;
        errorEl.textContent = "";
        toast(t("saved"));
      } catch (e) {
        errorEl.textContent = errorText(e);
      }
    });

    document.getElementById("passwordForm").addEventListener("submit", async (event) => {
      event.preventDefault();
      const f = event.target;
      if (f.password.value.length < 8) return (errorEl.textContent = t("pwShort"));
      if (f.password.value !== f.password_confirmation.value) return (errorEl.textContent = t("pwMismatch"));
      try {
        await api("PUT", "/api/account/password", { password: f.password.value, password_confirmation: f.password_confirmation.value });
        f.reset();
        errorEl.textContent = "";
        toast(t("passwordChanged"));
      } catch (e) {
        errorEl.textContent = errorText(e);
      }
    });

    document.getElementById("forwardingForm").addEventListener("submit", async (event) => {
      event.preventDefault();
      const f = event.target;
      const value = f.forward_to.value.trim();
      if (value.toLowerCase().endsWith("@" + String(window.MAIL_DOMAIN || location.hostname).toLowerCase())) return (errorEl.textContent = t("forwardOwnDomain"));
      try {
        await api("PUT", "/api/account/forwarding", { forward_to: value || null, forward_keep: f.forward_keep.checked });
        errorEl.textContent = "";
        toast(t("saved"));
      } catch (e) {
        errorEl.textContent = errorText(e);
      }
    });

    document.getElementById("pushOn").addEventListener("click", () => {
      store.set("openmeru-push-off", "");
      enablePush().then(renderNotifyState).then(loadBell).catch(() => toast(t("error")));
    });
    document.getElementById("pushOff").addEventListener("click", () => disablePush().then(renderNotifyState).then(loadBell).catch(() => toast(t("error"))));
    document.getElementById("pushTest").addEventListener("click", async () => {
      try {
        await api("POST", "/api/push/test");
        toast(t("pushTestSent"));
      } catch (e) {
        toast(t("error"));
      }
    });

    const deleteForm = document.getElementById("deleteForm");
    if (deleteForm) {
      deleteForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!window.confirm(t("deleteWarning"))) return;
        try {
          const data = await api("DELETE", "/api/account", { password: deleteForm.password.value });
          location.href = data.redirect || WEBMAIL;
        } catch (e) {
          errorEl.textContent = errorText(e);
        }
      });
    }

    loadBell().then(() => {
      autoPush();
      if (location.hash === "#notify") renderNotifyState();
    });
  }
})();
