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
      background: rgba(3, 6, 7, 0.96);
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
    window.setTimeout(() => {
      loader.classList.add("is-hidden");
      window.setTimeout(() => loader.remove(), 500);
    }, 900);
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
