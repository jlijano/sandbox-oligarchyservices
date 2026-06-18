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
