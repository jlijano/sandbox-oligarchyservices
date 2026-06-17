(function () {
  const logoDataUri = "data:image/png;base64,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";

  const loaderStyle = document.createElement("style");
  loaderStyle.textContent = `
    .brand-loader {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: grid;
      place-items: center;
      overflow: hidden;
      background: radial-gradient(circle at 50% 50%, rgba(91, 213, 255, 0.14), transparent 22rem), #020607;
      opacity: 1;
      visibility: visible;
      transition: opacity 520ms ease, visibility 520ms ease;
    }

    .brand-loader::before {
      content: "";
      position: absolute;
      inset: -20%;
      background-image: linear-gradient(rgba(91, 213, 255, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(91, 213, 255, 0.08) 1px, transparent 1px);
      background-size: 42px 42px;
      transform: perspective(700px) rotateX(58deg) translateY(8%);
      transform-origin: center;
      animation: board-drift 2600ms linear infinite;
      opacity: 0.74;
    }

    .brand-loader::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, rgba(105, 225, 255, 0.14), transparent);
      animation: wide-scan 1500ms ease-in-out infinite;
      mix-blend-mode: screen;
    }

    .brand-loader.is-hidden {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .loader-board {
      position: relative;
      width: min(1180px, 94vw);
      min-height: 360px;
      display: grid;
      place-items: center;
      isolation: isolate;
    }

    .brand-loader img {
      position: relative;
      z-index: 2;
      width: min(1120px, 92vw);
      height: auto;
      filter: drop-shadow(0 0 18px rgba(170, 240, 255, 0.86));
      clip-path: inset(0 100% 0 0);
      animation: logo-print 1500ms cubic-bezier(0.22, 1, 0.36, 1) 160ms forwards, logo-glow 1000ms ease-in-out 1700ms infinite alternate;
    }

    .circuit-line {
      position: absolute;
      z-index: 1;
      height: 2px;
      width: var(--w);
      left: var(--x);
      top: var(--y);
      background: linear-gradient(90deg, transparent, rgba(91, 213, 255, 0.92), rgba(255, 255, 255, 0.76));
      box-shadow: 0 0 10px rgba(91, 213, 255, 0.75);
      transform-origin: var(--origin);
      transform: scaleX(0) rotate(var(--r));
      animation: trace-in 1100ms ease-out var(--d) infinite;
    }

    .node {
      position: absolute;
      z-index: 1;
      left: var(--x);
      top: var(--y);
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: #7be9ff;
      box-shadow: 0 0 0 4px rgba(91, 213, 255, 0.12), 0 0 18px rgba(91, 213, 255, 0.88);
      animation: node-pulse 950ms ease-in-out var(--d) infinite alternate;
    }

    @keyframes board-drift { to { background-position: 42px 42px; } }
    @keyframes wide-scan { 0%, 100% { transform: translateX(-95%); opacity: 0; } 45%, 55% { opacity: 1; } 100% { transform: translateX(95%); } }
    @keyframes logo-print { to { clip-path: inset(0 0 0 0); } }
    @keyframes logo-glow { from { filter: drop-shadow(0 0 12px rgba(170, 240, 255, 0.62)); } to { filter: drop-shadow(0 0 28px rgba(170, 240, 255, 1)); } }
    @keyframes trace-in { 0% { transform: scaleX(0) rotate(var(--r)); opacity: 0; } 20%, 70% { opacity: 1; } 100% { transform: scaleX(1) rotate(var(--r)); opacity: 0; } }
    @keyframes node-pulse { from { transform: scale(0.7); opacity: 0.42; } to { transform: scale(1.22); opacity: 1; } }

    @media (max-width: 620px) {
      .loader-board { min-height: 260px; }
      .brand-loader img { width: 94vw; }
    }

    @media (prefers-reduced-motion: reduce) {
      .brand-loader, .brand-loader::before, .brand-loader::after, .brand-loader img, .circuit-line, .node {
        transition: none;
        animation: none;
      }
      .brand-loader img { clip-path: none; }
    }
  `;
  document.head.appendChild(loaderStyle);

  const loader = document.createElement("div");
  loader.className = "brand-loader";
  loader.setAttribute("role", "status");
  loader.setAttribute("aria-label", "Loading Oligarchy Services");
  loader.innerHTML = `
    <div class="loader-board">
      <span class="circuit-line" style="--x:2%;--y:28%;--w:34%;--r:0deg;--origin:left;--d:80ms"></span>
      <span class="circuit-line" style="--x:64%;--y:28%;--w:34%;--r:180deg;--origin:left;--d:180ms"></span>
      <span class="circuit-line" style="--x:4%;--y:72%;--w:32%;--r:0deg;--origin:left;--d:280ms"></span>
      <span class="circuit-line" style="--x:64%;--y:72%;--w:32%;--r:180deg;--origin:left;--d:380ms"></span>
      <span class="node" style="--x:2%;--y:26%;--d:80ms"></span>
      <span class="node" style="--x:96%;--y:26%;--d:180ms"></span>
      <span class="node" style="--x:4%;--y:70%;--d:280ms"></span>
      <span class="node" style="--x:94%;--y:70%;--d:380ms"></span>
      <img src="${logoDataUri}" alt="Oligarchy">
    </div>`;
  document.body.prepend(loader);

  const hideLoader = () => {
    window.setTimeout(() => {
      loader.classList.add("is-hidden");
      window.setTimeout(() => loader.remove(), 560);
    }, 1600);
  };

  if (document.readyState === "complete") {
    hideLoader();
  } else {
    window.addEventListener("load", hideLoader, { once: true });
  }

  const headerStyle = document.createElement("style");
  headerStyle.textContent = `
    .nav-links .nav-cta {
      display: inline-flex;
      min-width: 98px;
      min-height: 44px;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 0 24px !important;
      background: #a40712;
      color: #ffffff !important;
      font-weight: 500 !important;
      line-height: 1;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
    }

    .nav-links .nav-cta:hover,
    .nav-links .nav-cta:focus-visible {
      background: #b70a17;
      color: #ffffff !important;
    }

    @media (max-width: 1120px) {
      .nav-links .nav-cta {
        width: auto;
        align-self: flex-start;
        padding: 0 24px !important;
        text-align: center;
      }
    }
  `;
  document.head.appendChild(headerStyle);

  const services = [
    ["ITAD", "/itad.html"],
    ["ITAM", "/itam.html"],
    ["Help Desk", "/help-desk.html"],
    ["Business Systems", "/business-systems.html"],
    ["AI & Automation", "/ai-automation.html"],
    ["Projects", "/projects.html"]
  ];

  const navLinks = document.getElementById("primary-navigation");
  if (navLinks) {
    const currentPath = window.location.pathname || "/";
    const isCurrent = (href) => href === currentPath || (href === "/" && currentPath === "/index.html");
    const serviceIsCurrent = services.some(([, href]) => isCurrent(href));

    navLinks.innerHTML = "";

    const makeLink = (label, href, className) => {
      const link = document.createElement("a");
      link.href = href;
      link.textContent = label;
      if (className) {
        link.className = className;
      }
      if (isCurrent(href)) {
        link.setAttribute("aria-current", "page");
      }
      return link;
    };

    navLinks.append(makeLink("Home", "/"), makeLink("About Us", "/about.html"));

    const dropdown = document.createElement("div");
    dropdown.className = "nav-dropdown";

    const trigger = document.createElement("button");
    trigger.className = "nav-dropdown-trigger";
    trigger.type = "button";
    trigger.textContent = "Services";
    trigger.setAttribute("aria-expanded", "false");
    trigger.setAttribute("aria-haspopup", "true");
    if (serviceIsCurrent) {
      trigger.setAttribute("aria-current", "page");
    }

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
  if (year) {
    year.textContent = new Date().getFullYear();
  }

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

  if (!config.enabled || optedOut || (config.respectDoNotTrack && doNotTrack)) {
    return;
  }

  if (config.provider === "plausible" && config.domain) {
    const script = document.createElement("script");
    script.defer = true;
    script.dataset.domain = config.domain;
    script.src = config.scriptUrl || "https://plausible.io/js/script.js";
    document.head.appendChild(script);

    window.plausible = window.plausible || function () {
      (window.plausible.q = window.plausible.q || []).push(arguments);
    };

    document.querySelectorAll("[data-track]").forEach((element) => {
      element.addEventListener("click", () => {
        window.plausible(element.getAttribute("data-track"));
      });
    });
  }
})();
