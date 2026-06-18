(function () {
  const logoDataUri = "data:image/png;base64,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";

  const style = document.createElement("style");
  style.textContent = `
    .brand-loader{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;overflow:hidden;background:radial-gradient(circle at 50% 50%,rgba(91,213,255,.14),transparent 22rem),#020607;opacity:1;visibility:visible;transition:opacity 520ms ease,visibility 520ms ease}.brand-loader::before{content:"";position:absolute;inset:-20%;background-image:linear-gradient(rgba(91,213,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(91,213,255,.08) 1px,transparent 1px);background-size:42px 42px;transform:perspective(700px) rotateX(58deg) translateY(8%);transform-origin:center;animation:board-drift 2600ms linear infinite;opacity:.74}.brand-loader::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(105,225,255,.14),transparent);animation:wide-scan 1500ms ease-in-out infinite;mix-blend-mode:screen}.brand-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}.loader-board{position:relative;width:min(460px,78vw);min-height:220px;display:grid;place-items:center;isolation:isolate}.brand-loader img{position:relative;z-index:2;width:min(280px,70vw);height:auto;filter:drop-shadow(0 0 18px rgba(170,240,255,.86));clip-path:inset(0 100% 0 0);animation:logo-print 1500ms cubic-bezier(.22,1,.36,1) 160ms forwards,logo-glow 1000ms ease-in-out 1700ms infinite alternate}.circuit-line{position:absolute;z-index:1;height:2px;width:var(--w);left:var(--x);top:var(--y);background:linear-gradient(90deg,transparent,rgba(91,213,255,.92),rgba(255,255,255,.76));box-shadow:0 0 10px rgba(91,213,255,.75);transform-origin:var(--origin);transform:scaleX(0) rotate(var(--r));animation:trace-in 1100ms ease-out var(--d) infinite}.node{position:absolute;z-index:1;left:var(--x);top:var(--y);width:9px;height:9px;border-radius:999px;background:#7be9ff;box-shadow:0 0 0 4px rgba(91,213,255,.12),0 0 18px rgba(91,213,255,.88);animation:node-pulse 950ms ease-in-out var(--d) infinite alternate}@keyframes board-drift{to{background-position:42px 42px}}@keyframes wide-scan{0%,100%{transform:translateX(-95%);opacity:0}45%,55%{opacity:1}100%{transform:translateX(95%)}}@keyframes logo-print{to{clip-path:inset(0 0 0 0)}}@keyframes logo-glow{from{filter:drop-shadow(0 0 12px rgba(170,240,255,.62))}to{filter:drop-shadow(0 0 28px rgba(170,240,255,1))}}@keyframes trace-in{0%{transform:scaleX(0) rotate(var(--r));opacity:0}20%,70%{opacity:1}100%{transform:scaleX(1) rotate(var(--r));opacity:0}}@keyframes node-pulse{from{transform:scale(.7);opacity:.42}to{transform:scale(1.22);opacity:1}}@media(max-width:620px){.loader-board{width:78vw;min-height:180px}.brand-loader img{width:min(240px,70vw)}}@media(prefers-reduced-motion:reduce){.brand-loader,.brand-loader::before,.brand-loader::after,.brand-loader img,.circuit-line,.node{transition:none;animation:none}.brand-loader img{clip-path:none}}
    .site-header{justify-content:space-between!important}.site-header .brand{display:inline-flex!important;position:relative;flex:0 0 auto;width:190px;height:42px;min-width:0!important;align-items:center;gap:0;background:transparent!important;color:transparent!important;font-size:0!important;text-decoration:none}.site-header .brand::before{content:"";display:block;width:190px;height:42px;background:url("${logoDataUri}") left center/contain no-repeat}.site-header .brand-mark{display:none!important}.site-header .brand>span:not(.brand-mark){position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap}@media(max-width:620px){.site-header .brand{width:154px;height:34px}.site-header .brand::before{width:154px;height:34px}}
    .nav-links .nav-cta{display:inline-flex;min-width:98px;min-height:44px;align-items:center;justify-content:center;border-radius:999px;padding:0 24px!important;background:#a40712;color:#fff!important;font-weight:500!important;line-height:1;box-shadow:inset 0 1px 0 rgba(255,255,255,.14)}.nav-links .nav-cta:hover,.nav-links .nav-cta:focus-visible{background:#b70a17;color:#fff!important}@media(max-width:1120px){.nav-links .nav-cta{width:auto;align-self:flex-start;padding:0 24px!important;text-align:center}}
  `;
  document.head.appendChild(style);

  const loader = document.createElement("div");
  loader.className = "brand-loader";
  loader.setAttribute("role", "status");
  loader.setAttribute("aria-label", "Loading Oligarchy Services");
  loader.innerHTML = `<div class="loader-board"><span class="circuit-line" style="--x:2%;--y:28%;--w:34%;--r:0deg;--origin:left;--d:80ms"></span><span class="circuit-line" style="--x:64%;--y:28%;--w:34%;--r:180deg;--origin:left;--d:180ms"></span><span class="circuit-line" style="--x:4%;--y:72%;--w:32%;--r:0deg;--origin:left;--d:280ms"></span><span class="circuit-line" style="--x:64%;--y:72%;--w:32%;--r:180deg;--origin:left;--d:380ms"></span><span class="node" style="--x:2%;--y:26%;--d:80ms"></span><span class="node" style="--x:96%;--y:26%;--d:180ms"></span><span class="node" style="--x:4%;--y:70%;--d:280ms"></span><span class="node" style="--x:94%;--y:70%;--d:380ms"></span><img src="${logoDataUri}" alt="Oligarchy"></div>`;
  document.body.prepend(loader);

  const hideLoader = () => setTimeout(() => {
    loader.classList.add("is-hidden");
    setTimeout(() => loader.remove(), 560);
  }, 1600);

  if (document.readyState === "complete") hideLoader();
  else window.addEventListener("load", hideLoader, { once: true });

  const services = [["ITAD","/itad.html"],["ITAM","/itam.html"],["Help Desk","/help-desk.html"],["Business Systems","/business-systems.html"],["AI & Automation","/ai-automation.html"],["Projects","/projects.html"]];
  const navLinks = document.getElementById("primary-navigation");
  if (navLinks) {
    const currentPath = window.location.pathname || "/";
    const isCurrent = (href) => href === currentPath || (href === "/" && currentPath === "/index.html");
    const makeLink = (label, href, className) => {
      const link = document.createElement("a");
      link.href = href;
      link.textContent = label;
      if (className) link.className = className;
      if (isCurrent(href)) link.setAttribute("aria-current", "page");
      return link;
    };

    navLinks.innerHTML = "";
    navLinks.append(makeLink("Home", "/"), makeLink("About Us", "/about.html"));

    const dropdown = document.createElement("div");
    dropdown.className = "nav-dropdown";
    const trigger = document.createElement("button");
    trigger.className = "nav-dropdown-trigger";
    trigger.type = "button";
    trigger.textContent = "Services";
    trigger.setAttribute("aria-expanded", "false");
    trigger.setAttribute("aria-haspopup", "true");
    if (services.some(([, href]) => isCurrent(href))) trigger.setAttribute("aria-current", "page");

    const menu = document.createElement("div");
    menu.className = "nav-dropdown-menu";
    menu.setAttribute("role", "menu");
    services.forEach(([label, href]) => {
      const item = makeLink(label, href);
      item.setAttribute("role", "menuitem");
      menu.appendChild(item);
    });
    dropdown.append(trigger, menu);
    navLinks.append(dropdown, makeLink("Contact Us", "/contact.html"), makeLink("Get Quote", "/contact.html", "nav-cta"));

    trigger.addEventListener("click", () => {
      const isOpen = dropdown.classList.toggle("is-open");
      trigger.setAttribute("aria-expanded", String(isOpen));
    });
    document.addEventListener("click", (event) => {
      if (!dropdown.contains(event.target)) {
        dropdown.classList.remove("is-open");
        trigger.setAttribute("aria-expanded", "false");
      }
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        dropdown.classList.remove("is-open");
        trigger.setAttribute("aria-expanded", "false");
      }
    });
  }

  const navToggle = document.querySelector(".nav-toggle");
  if (navToggle && navLinks) {
    navToggle.addEventListener("click", () => {
      const isOpen = navLinks.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", String(isOpen));
    });
  }

  const year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  const optOutButton = document.getElementById("analytics-opt-out");
  if (optOutButton) {
    optOutButton.addEventListener("click", () => {
      window.localStorage.setItem("oligarchy_analytics_opt_out", "true");
      optOutButton.textContent = "Analytics opt-out saved";
      optOutButton.setAttribute("disabled", "disabled");
    });
  }

  const config = window.OLIGARCHY_ANALYTICS || {};
  const optedOut = window.localStorage.getItem("oligarchy_analytics_opt_out") === "true";
  const doNotTrack = navigator.doNotTrack === "1" || window.doNotTrack === "1" || navigator.msDoNotTrack === "1";
  if (!config.enabled || optedOut || (config.respectDoNotTrack && doNotTrack)) return;

  if (config.provider === "plausible" && config.domain) {
    const script = document.createElement("script");
    script.defer = true;
    script.dataset.domain = config.domain;
    script.src = config.scriptUrl || "https://plausible.io/js/script.js";
    document.head.appendChild(script);
    window.plausible = window.plausible || function () { (window.plausible.q = window.plausible.q || []).push(arguments); };
    document.querySelectorAll("[data-track]").forEach((element) => element.addEventListener("click", () => window.plausible(element.getAttribute("data-track"))));
  }
})();
