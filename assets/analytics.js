(function () {
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
