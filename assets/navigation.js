(function () {
  const SERVICES = [
    ["AI & Automation", "/ai-automation.html"],
    ["Help Desk", "/help-desk.html"],
    ["MSP", "/msp.html"],
    ["Business Systems", "/business-systems.html"],
    ["ITAD", "/itad.html"],
    ["ITAM", "/itam.html"]
  ];

  const normalizePath = (href) => {
    try {
      const url = new URL(href, window.location.origin);
      return url.pathname === "/index.html" ? "/" : url.pathname;
    } catch (error) {
      return href;
    }
  };

  const currentPath = normalizePath(window.location.pathname || "/");
  const isCurrent = (href) => normalizePath(href) === currentPath;

  const makeLink = (label, href, className) => {
    const link = document.createElement("a");
    link.href = href;
    link.textContent = label;
    if (className) link.className = className;
    if (isCurrent(href)) link.setAttribute("aria-current", "page");
    return link;
  };

  const closeDropdown = (dropdown, trigger) => {
    dropdown.classList.remove("is-open");
    trigger.setAttribute("aria-expanded", "false");
  };

  const closeMobileMenu = (navToggle, navLinks) => {
    navLinks.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded", "false");
  };

  const installNavigation = () => {
    const navLinks = document.getElementById("primary-navigation");
    const navToggle = document.querySelector(".nav-toggle");
    if (!navLinks || !navToggle || navLinks.dataset.navigationReady === "true") return;

    navLinks.dataset.navigationReady = "true";
    navLinks.textContent = "";
    navLinks.append(makeLink("Home", "/"), makeLink("About Us", "/about.html"));

    const dropdown = document.createElement("div");
    dropdown.className = "nav-dropdown";

    const trigger = document.createElement("button");
    trigger.className = "nav-dropdown-trigger nav-dropdown-toggle";
    trigger.type = "button";
    trigger.textContent = "Services";
    trigger.setAttribute("aria-expanded", "false");
    trigger.setAttribute("aria-haspopup", "true");
    if (SERVICES.some(([, href]) => isCurrent(href))) trigger.setAttribute("aria-current", "page");

    const menu = document.createElement("div");
    menu.className = "nav-dropdown-menu";
    menu.setAttribute("role", "menu");
    SERVICES.forEach(([label, href]) => {
      const item = makeLink(label, href);
      item.setAttribute("role", "menuitem");
      menu.appendChild(item);
    });

    dropdown.append(trigger, menu);
    navLinks.append(
      dropdown,
      makeLink("Blogs", "/blogs.php"),
      makeLink("Contact Us", "/contact.html"),
      makeLink("Get Quote", "/contact.html", "nav-cta")
    );

    const freshToggle = navToggle.cloneNode(true);
    navToggle.replaceWith(freshToggle);

    freshToggle.addEventListener("click", () => {
      const isOpen = navLinks.classList.toggle("is-open");
      freshToggle.setAttribute("aria-expanded", String(isOpen));
    });

    trigger.addEventListener("click", () => {
      const isOpen = dropdown.classList.toggle("is-open");
      trigger.setAttribute("aria-expanded", String(isOpen));
    });

    navLinks.addEventListener("click", (event) => {
      if (event.target instanceof HTMLAnchorElement) {
        closeDropdown(dropdown, trigger);
        closeMobileMenu(freshToggle, navLinks);
      }
    });

    document.addEventListener("click", (event) => {
      if (!dropdown.contains(event.target)) closeDropdown(dropdown, trigger);
      if (!navLinks.contains(event.target) && !freshToggle.contains(event.target)) closeMobileMenu(freshToggle, navLinks);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeDropdown(dropdown, trigger);
        closeMobileMenu(freshToggle, navLinks);
      }
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", installNavigation, { once: true });
  } else {
    installNavigation();
  }
})();
