/* Genmash chat assistant. Add <script src="chatbot.js" defer></script> to each page.
   Edit section 1 (details) and section 2 (answers) to change what the bot says. */
(function () {
  "use strict";

  /* ===== 1. YOUR DETAILS ===== */
  var C = {
    wa: "263718751792", phone: "+263718751792", phoneShow: "+263 71 875 1792",
    other: "0786077652, 0775204255", email: "sales@genmashy.co.zw", place: "Patchway, Kadoma"
  };
  var SERVICES = [["solar", "Solar installation"], ["health", "Solar health check"], ["starlink", "Starlink installation"], ["wifi", "WiFi and access points"], ["electric", "Electric tubing"]];

  /* ===== 2. ANSWERS (words = what people might type) ===== */
  var REQ = function (k) { return [["Request this service", "request:" + k], ["All services", "topic:services"], ["Talk to the team", "team"]]; };
  var TOPICS = {
    hello: { words: ["hi", "hello", "hey", "morning", "afternoon"], text: "Hello! How can I help?" },
    thanks: { words: ["thanks", "thank", "cheers"], text: "You're welcome! Anything else I can help with?" },
    about: { words: ["about", "company", "who are", "genmash"], text: "Genmash distributors is a small, local company in " + C.place + ". We supply and install solar systems, Starlink, WiFi and electric tubing, and we work to leave every client fully satisfied." },
    services: { words: ["services", "service", "offer", "what do you do", "products"], text: "We supply and install:\n- Solar systems and solar health checks\n- Starlink\n- WiFi extenders and access points\n- Electric tubing\n\nPick one to learn more.", chips: [["Solar", "topic:solar"], ["Solar health check", "topic:health"], ["Starlink", "topic:starlink"], ["WiFi", "topic:wifi"], ["Electric tubing", "topic:electric"]] },
    solar: { words: ["solar", "panel", "inverter", "battery", "batteries", "backup", "load shedding", "power"], text: "We size a solar system to your load, supply the panels, inverter and battery, then mount, wire and test it. You get a how-to-use guide at handover.", chips: REQ("solar") },
    health: { words: ["health", "health check", "fault", "faulty", "not charging", "inspect"], text: "Our solar health check inspects your existing system (panels, inverter, batteries and wiring), tests performance and fixes any faults found.", chips: REQ("health") },
    starlink: { words: ["starlink", "dish", "satellite", "internet"], text: "We supply Starlink kits and install them: a clear-view site check, mounting, cabling, router setup and testing.", chips: REQ("starlink") },
    wifi: { words: ["wifi", "wi-fi", "extender", "access point", "dead spot", "signal", "router"], text: "We supply and set up WiFi extenders and configure access points to remove dead spots. We check the signal first and run a speed test after installation.", chips: REQ("wifi") },
    electric: { words: ["electric", "electrical", "tubing", "conduit", "wiring"], text: "We supply and fit conduit and tubing so your electrical wiring is neat and protected, from site check and layout to final inspection.", chips: REQ("electric") },
    price: { words: ["price", "prices", "cost", "how much", "quote", "quotation", "cheap", "pay"], text: "Prices depend on your site and the equipment, so we quote per job. Tap Request a job and I'll prepare your details for the team, or message them on WhatsApp.", chips: [["Request a job", "request"], ["Talk to the team", "team"]] },
    place: { words: ["where", "location", "address", "kadoma", "patchway", "visit", "directions"], text: "We are in " + C.place + ".", chips: [["Contact details", "topic:contact"], ["Talk to the team", "team"]] },
    hours: { words: ["hours", "open", "opening", "close", "closing", "time"], text: "I don't have our opening hours here. Message or call the team to confirm a good time.", chips: [["Talk to the team", "team"]] },
    contact: { words: ["phone", "call", "number", "email", "whatsapp", "contact"], text: "You can reach us on " + C.phoneShow + " (phone and WhatsApp), or " + C.other + ", or by email at " + C.email + ".", links: [["Chat on WhatsApp", "https://wa.me/" + C.wa, 1], ["Call us", "tel:" + C.phone], ["Email us", "mailto:" + C.email]] }
  };
  var MENU = [["Our services", "topic:services"], ["Request a job", "request"], ["Contact details", "topic:contact"], ["About Genmash", "topic:about"], ["Talk to the team", "team"]];
  var CANCEL = [["Cancel", "cancel"]];

  /* ===== 3. THE CHAT ENGINE (you can leave this alone) ===== */
  var KEY = "gmchat", st = { open: false, msgs: [] }, flow = null, lastQ = "", chain = Promise.resolve();
  var el = {}, script = document.currentScript;
  try { var saved = JSON.parse(sessionStorage.getItem(KEY)); if (saved) st = saved; } catch (e) {}
  function save() { try { sessionStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
  function $(t, c, x) { var n = document.createElement(t); if (c) n.className = c; if (x) n.textContent = x; return n; }

  function addMsg(from, text, links, keep) {
    clearChips();
    var m = $("div", "gm-msg gm-" + from, text);
    if (links && links.length) {
      var box = $("div", "gm-links");
      links.forEach(function (l) {
        var a = $("a", l[2] ? "gm-main" : "", l[0]); a.href = l[1];
        if (/^http/.test(l[1])) { a.target = "_blank"; a.rel = "noopener"; }
        box.appendChild(a);
      });
      m.appendChild(box);
    }
    el.log.appendChild(m); el.log.scrollTop = el.log.scrollHeight;
    if (!keep) { st.msgs.push([from, text, links || null]); st.msgs = st.msgs.slice(-40); save(); }
  }
  function clearChips() { var c = el.log.querySelector(".gm-chips"); if (c) c.remove(); }
  function showChips(chips) {
    if (!chips) return;
    var box = $("div", "gm-chips");
    chips.forEach(function (c) {
      var b = $("button", "", c[0]); b.type = "button";
      b.onclick = function () { addMsg("user", c[0]); act(c[1]); };
      box.appendChild(b);
    });
    el.log.appendChild(box); el.log.scrollTop = el.log.scrollHeight;
  }
  function say(text, links, chips) {
    chain = chain.then(function () {
      return new Promise(function (done) {
        var d = $("div", "gm-dots"); d.innerHTML = "<i></i><i></i><i></i>"; el.log.appendChild(d); el.log.scrollTop = el.log.scrollHeight;
        setTimeout(function () { d.remove(); addMsg("bot", text, links); showChips(chips); done(); }, 450);
      });
    });
  }

  function find(text) {
    var t = text.toLowerCase(), tokens = t.split(/[^a-z0-9]+/), best = null, top = 0;
    Object.keys(TOPICS).forEach(function (id) {
      var score = 0;
      TOPICS[id].words.forEach(function (w) {
        if (w.indexOf(" ") > -1 || w.indexOf("-") > -1) { if (t.indexOf(w) > -1) score += 2; }
        else if (tokens.some(function (k) { return w.length <= 3 ? k === w : k.indexOf(w) === 0; })) score += 1;
      });
      if (score > top) { top = score; best = id; }
    });
    return best;
  }
  function topic(id) { var o = TOPICS[id]; say(o.text, o.links, o.chips || [["Back to menu", "menu"]]); }
  function team() {
    var msg = "Hello Genmash distributors, I was chatting on your website and would like help from the team." + (lastQ ? "\nMy question: " + lastQ : "");
    say("Our team will be glad to help. WhatsApp is the fastest way to reach them.", [["Message the team on WhatsApp", "https://wa.me/" + C.wa + "?text=" + encodeURIComponent(msg), 1], ["Call " + C.phoneShow, "tel:" + C.phone], ["Email us", "mailto:" + C.email]], [["Back to menu", "menu"]]);
  }
  function svcName(text) {
    var t = text.toLowerCase(), hit = null;
    SERVICES.forEach(function (s) { if (t === s[0] || t === s[1].toLowerCase()) hit = s[1]; });
    var id = hit ? null : find(text); if (!hit && id && SERVICES.some(function (s) { return s[0] === id; })) hit = SERVICES.filter(function (s) { return s[0] === id; })[0][1];
    return hit || text.trim().slice(0, 80);
  }

  /* "Request a job" steps: service > name > phone > details > WhatsApp */
  function request(key) {
    flow = { step: key ? "name" : "service", d: { service: key ? svcName(key) : "" } };
    if (key) return say("Happy to help with " + flow.d.service + ". What is your name?", null, CANCEL);
    say("Happy to help. Which service do you need?", null, SERVICES.map(function (s) { return [s[1], "svc:" + s[0]]; }).concat([["Something else", "svc:other"]], CANCEL));
  }
  function step(text) {
    var f = flow, v = text.trim();
    if (/^(cancel|stop|menu|exit)$/i.test(v)) return act("cancel");
    if (f.step === "service") { f.d.service = svcName(v); f.step = "name"; say("What is your name?", null, CANCEL); }
    else if (f.step === "name") { f.d.name = v.slice(0, 60); f.step = "phone"; say("Thanks, " + f.d.name.split(" ")[0] + ". What is the best phone number to reach you (WhatsApp if possible)?", null, CANCEL); }
    else if (f.step === "phone") {
      if (v.replace(/\D/g, "").length < 7) return say("That does not look like a full number. Please type it again, for example 0771234567.", null, CANCEL);
      f.d.phone = v.slice(0, 30); f.step = "details";
      say("Is there anything we should know, such as your location or the problem? Or tap Skip.", null, [["Skip", "skip"]].concat(CANCEL));
    } else if (f.step === "details") { f.d.details = v.slice(0, 300); finish(); }
  }
  function finish() {
    var d = flow.d, msg = "Hello Genmash distributors, I would like help.\nService: " + d.service + "\nName: " + d.name + "\nPhone: " + d.phone + (d.details ? "\nDetails: " + d.details : "") + "\n(Sent from the website chat)";
    flow = null;
    say("Thank you, " + d.name.split(" ")[0] + ". Your request is ready. Tap the button to send it to our team on WhatsApp. Nothing is sent until you do.", [["Send on WhatsApp", "https://wa.me/" + C.wa + "?text=" + encodeURIComponent(msg), 1], ["Call us instead", "tel:" + C.phone]], [["Back to menu", "menu"]]);
  }

  function act(a) {
    if (a === "menu") { flow = null; say("What would you like to do?", null, MENU); }
    else if (a === "request") request();
    else if (a === "team") { flow = null; team(); }
    else if (a === "cancel") { flow = null; say("No problem, I've cancelled that. What else can I help with?", null, MENU); }
    else if (a === "skip" && flow) finish();
    else if (a.indexOf("request:") === 0) request(a.slice(8));
    else if (a.indexOf("svc:") === 0) { if (a === "svc:other") { say("Please type the service you need.", null, CANCEL); } else step(a.slice(4)); }
    else if (a.indexOf("topic:") === 0) { flow = null; topic(a.slice(6)); }
  }
  function send(text) {
    text = text.trim(); if (!text) return;
    addMsg("user", text);
    if (flow) return step(text);
    var id = find(text);
    if (/\b(human|agent|person|staff|someone|speak|team)\b/i.test(text)) { lastQ = text.slice(0, 200); return team(); }
    if (id) return topic(id);
    lastQ = text.slice(0, 200);
    say("I'm not sure about that one, but our team can help directly. You can message them, or pick an option below.", null, [["Talk to the team", "team"], ["Our services", "topic:services"], ["Request a job", "request"]]);
  }

  function setOpen(open) {
    st.open = open; save();
    el.box.hidden = !open; el.btn.setAttribute("aria-expanded", open);
    document.body.classList.toggle("gm-open", open);
    if (open) { el.input.focus(); if (!el.started) { el.started = true; if (st.msgs.length) { st.msgs.forEach(function (m) { addMsg(m[0], m[1], m[2], true); }); showChips(MENU); } else say("Hi, I'm the Genmash assistant. I can answer questions about our services, help you request a job, or connect you with our team.", null, MENU); } }
    else el.btn.focus();
  }
  function build() {
    el.btn = $("button", "gm-launch");
    el.btn.type = "button"; el.btn.setAttribute("aria-controls", "gm-box"); el.btn.setAttribute("aria-expanded", "false");
    el.btn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-5.9A8 8 0 1 1 21 12z"/></svg><span>Need help?</span>';
    el.box = $("section", "gm-box"); el.box.id = "gm-box"; el.box.hidden = true; el.box.setAttribute("aria-label", "Genmash chat assistant");
    el.box.innerHTML = '<div class="gm-head"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 2 4.5 13.5h6L9.5 22l10-12.5h-6.2z"/></svg><div><b>Genmash assistant</b><small>Instant answers, or we connect you to the team</small></div><button type="button">Close</button></div><div class="gm-log" role="log" aria-live="polite"></div><form class="gm-form"><input type="text" maxlength="300" placeholder="Type your message" aria-label="Your message" autocomplete="off"><button type="submit">Send</button></form>';
    el.log = el.box.querySelector(".gm-log"); el.input = el.box.querySelector("input");
    el.box.querySelector(".gm-head button").onclick = function () { setOpen(false); };
    el.box.querySelector("form").onsubmit = function (e) { e.preventDefault(); var v = el.input.value; el.input.value = ""; send(v); };
    el.btn.onclick = function () { setOpen(el.box.hidden); };
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !el.box.hidden) setOpen(false); });
    document.body.appendChild(el.box); document.body.appendChild(el.btn);
    if (st.open) setOpen(true);
  }

  /* Load the stylesheet first so the widget never flashes unstyled */
  var link = $("link"); link.rel = "stylesheet";
  link.href = (script && script.src ? script.src.replace(/chatbot\.js.*$/, "") : "") + "chatbot.css";
  link.onload = link.onerror = build; document.head.appendChild(link);
})();
