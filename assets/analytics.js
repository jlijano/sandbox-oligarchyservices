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
