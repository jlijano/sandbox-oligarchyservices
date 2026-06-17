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
      background: radial-gradient(circle at center, #0b2430 0%, #061015 44%, #030607 100%);
      opacity: 1;
      visibility: visible;
      transition: opacity 420ms ease, visibility 420ms ease;
    }

    .brand-loader.is-hidden {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .brand-loader img {
      width: min(250px, 68vw);
      height: auto;
      animation: loader-pulse 1100ms ease-in-out infinite alternate;
    }

    @keyframes loader-pulse {
      from { opacity: 0.72; transform: scale(0.985); }
      to { opacity: 1; transform: scale(1); }
    }

    @media (prefers-reduced-motion: reduce) {
      .brand-loader,
      .brand-loader img {
        transition: none;
        animation: none;
      }
    }
  `;
  document.head.appendChild(loaderStyle);

  const loader = document.createElement("div");
  loader.className = "brand-loader";
  loader.setAttribute("role", "status");
  loader.setAttribute("aria-label", "Loading Oligarchy Services");
  loader.innerHTML = `<img src="${logoDataUri}" alt="Oligarchy">`;
  document.body.prepend(loader);

  const hideLoader = () => {
    const minimumDisplayMs = 900;
    window.setTimeout(() => {
      loader.classList.add("is-hidden");
      window.setTimeout(() => loader.remove(), 500);
    }, minimumDisplayMs);
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

    navLinks.append(
      makeLink("Home", "/"),
      makeLink("About Us", "/about.html")
    );

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
    navLinks.append(
      dropdown,
      makeLink("Contact Us", "/contact.html"),
      makeLink("Get Quote", "/contact.html", "nav-cta")
    );

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
  const doNotTrack =
    navigator.doNotTrack === "1" ||
    window.doNotTrack === "1" ||
    navigator.msDoNotTrack === "1";

  if (!config.enabled || optedOut || (config.respectDoNotTrack && doNotTrack)) {
    return;
  }

  if (config.provider === "plausible" && config.domain) {
    const script = document.createElement("script");
    script.defer = true;
    script.dataset.domain = config.domain;
    script.src = config.scriptUrl || "https://plausible.io/js/script.js";
    document.head.appendChild(script);

    window.plausible =
      window.plausible ||
      function () {
        (window.plausible.q = window.plausible.q || []).push(arguments);
      };

    document.querySelectorAll("[data-track]").forEach((element) => {
      element.addEventListener("click", () => {
        window.plausible(element.getAttribute("data-track"));
      });
    });
  }
})();
