(function () {
  const shell = document.querySelector("[data-dashboard-shell]");
  if (!shell) return;

  const collapseButton = document.querySelector("[data-sidebar-collapse]");
  const mobileButton = document.querySelector("[data-mobile-menu]");
  const backdrop = document.querySelector("[data-sidebar-backdrop]");
  const sectionTitle = document.querySelector("[data-section-title]");
  const valleyGroup = document.querySelector("[data-valley-group]");
  const valleyToggle = document.querySelector("[data-valley-toggle]");
  const storageKey = "oligarchy_sidebar_collapsed";
  const iconMap = {
    overview: "fa-tachometer",
    valley: "fa-sitemap",
    users: "fa-users",
    pages: "fa-file-text-o",
    blogs: "fa-newspaper-o",
    navigation: "fa-bars",
    settings: "fa-cog",
    activity: "fa-history"
  };

  const loadFontAwesome = () => {
    if (document.querySelector("link[data-font-awesome-sidebar]")) return;
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css";
    link.dataset.fontAwesomeSidebar = "true";
    document.head.appendChild(link);
  };

  const applySidebarIcons = () => {
    loadFontAwesome();
    document.querySelectorAll(".sidebar-nav .nav-icon").forEach((icon) => {
      const label = icon.closest("a, button")?.querySelector(".nav-label")?.textContent?.trim().toLowerCase();
      const iconClass = iconMap[label || ""];
      if (!iconClass) return;
      icon.innerHTML = `<i class="fa ${iconClass}" aria-hidden="true"></i>`;
    });
  };

  applySidebarIcons();

  const routeUsersLinksToUsersPage = () => {
    document.querySelectorAll(".sidebar-nav a, .quick-actions a, .hero-actions a").forEach((link) => {
      const label = link.querySelector(".nav-label")?.textContent?.trim().toLowerCase() || link.textContent.trim().toLowerCase();
      const isUsersLink = label === "users" || link.dataset.sectionLink === "users";
      if (!isUsersLink) return;
      link.href = "/users.php";
      link.removeAttribute("data-section-link");
    });
  };

  routeUsersLinksToUsersPage();

  if (valleyGroup && valleyToggle) {
    valleyToggle.addEventListener("click", () => {
      const isOpen = !valleyGroup.classList.contains("is-open");
      valleyGroup.classList.toggle("is-open", isOpen);
      valleyToggle.setAttribute("aria-expanded", String(isOpen));
    });
  }

  if (window.localStorage.getItem(storageKey) === "true") {
    shell.classList.add("is-collapsed");
    if (collapseButton) collapseButton.setAttribute("aria-expanded", "false");
  }

  if (collapseButton) {
    collapseButton.addEventListener("click", () => {
      const isCollapsed = shell.classList.toggle("is-collapsed");
      collapseButton.setAttribute("aria-expanded", String(!isCollapsed));
      window.localStorage.setItem(storageKey, String(isCollapsed));
    });
  }

  const setMobileOpen = (isOpen) => {
    shell.classList.toggle("is-sidebar-open", isOpen);
    if (mobileButton) mobileButton.setAttribute("aria-expanded", String(isOpen));
  };

  if (mobileButton) mobileButton.addEventListener("click", () => setMobileOpen(!shell.classList.contains("is-sidebar-open")));
  if (backdrop) backdrop.addEventListener("click", () => setMobileOpen(false));
  document.addEventListener("keydown", (event) => { if (event.key === "Escape") setMobileOpen(false); });

  const sections = Array.from(document.querySelectorAll("[data-dashboard-section]"));
  const links = Array.from(document.querySelectorAll("[data-section-link]"));
  const allowedIds = sections.map((section) => section.id);
  const normalizeHash = () => {
    const raw = window.location.hash.replace("#", "").trim().toLowerCase();
    return allowedIds.includes(raw) ? raw : allowedIds[0];
  };

  const showSection = (id) => {
    sections.forEach((section) => {
      const isActive = section.id === id;
      section.classList.toggle("is-active", isActive);
      section.setAttribute("aria-hidden", String(!isActive));
      if (isActive && sectionTitle) sectionTitle.textContent = section.dataset.sectionLabel || section.id;
    });
    links.forEach((link) => link.classList.toggle("is-active", link.dataset.sectionLink === id));
    if (valleyGroup && valleyToggle) {
      const isValleyActive = id === "users";
      valleyGroup.classList.toggle("is-active", isValleyActive);
      valleyGroup.classList.toggle("is-open", isValleyActive || valleyGroup.classList.contains("is-open"));
      valleyToggle.setAttribute("aria-expanded", String(valleyGroup.classList.contains("is-open")));
    }
    setMobileOpen(false);
  };

  links.forEach((link) => {
    link.addEventListener("click", (event) => {
      const id = link.dataset.sectionLink;
      if (!id || !allowedIds.includes(id)) return;
      event.preventDefault();
      if (window.location.hash !== `#${id}`) window.location.hash = id;
      showSection(id);
    });
  });

  window.addEventListener("hashchange", () => showSection(normalizeHash()));
  showSection(normalizeHash());

  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!window.confirm(form.dataset.confirm || "Continue?")) event.preventDefault();
    });
  });

  const setValue = (selector, value) => {
    const field = document.querySelector(selector);
    if (field) field.value = value || "";
  };
  const setChecked = (selector, value) => {
    const field = document.querySelector(selector);
    if (field) field.checked = value === "1" || value === 1 || value === true;
  };

  const userIdField = document.querySelector("[data-user-id]");
  const userForm = userIdField?.closest("form");
  const userPassword = userForm?.querySelector("input[name='password']");
  const userPasswordLabel = userPassword?.closest("label");

  const clientPlaceholderPassword = () => {
    const bytes = new Uint8Array(10);
    if (window.crypto?.getRandomValues) window.crypto.getRandomValues(bytes);
    return `Temporary-${Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("")}`;
  };

  const syncUserPasswordMode = () => {
    if (!userForm || !userPassword || !userPasswordLabel || !userIdField) return;
    const isCreate = userIdField.value === "0" || userIdField.value === "";
    userPasswordLabel.hidden = isCreate;
    userPassword.required = false;
    userPassword.placeholder = isCreate ? "Generated and emailed automatically" : "Leave blank to keep current password";
    if (isCreate) userPassword.value = "";
  };

  if (userForm && userPassword && userIdField) {
    userForm.addEventListener("submit", () => {
      const isCreate = userIdField.value === "0" || userIdField.value === "";
      if (isCreate && userPassword.value.length < 10) userPassword.value = clientPlaceholderPassword();
    });
    syncUserPasswordMode();
  }

  document.querySelectorAll("[data-edit-user]").forEach((button) => {
    button.addEventListener("click", () => {
      setValue("[data-user-id]", button.dataset.id);
      setValue("[data-user-name]", button.dataset.name);
      setValue("[data-user-email]", button.dataset.email);
      setValue("[data-user-role]", button.dataset.role);
      setChecked("[data-user-active]", button.dataset.active);
      const title = document.querySelector("[data-user-form-title]");
      if (title) title.textContent = "Edit user";
      syncUserPasswordMode();
      document.querySelector("[data-user-name]")?.focus();
    });
  });

  document.querySelector("[data-reset-user-form]")?.addEventListener("click", () => {
    const form = document.querySelector("[data-user-id]")?.closest("form");
    if (form) form.reset();
    setValue("[data-user-id]", "0");
    const title = document.querySelector("[data-user-form-title]");
    if (title) title.textContent = "Create user";
    syncUserPasswordMode();
  });

  document.querySelectorAll("[data-edit-page]").forEach((button) => {
    button.addEventListener("click", () => {
      setValue("[data-page-id]", button.dataset.id);
      setValue("[data-page-title]", button.dataset.title);
      setValue("[data-page-slug]", button.dataset.slug);
      setValue("[data-page-meta]", button.dataset.meta);
      setValue("[data-page-body]", button.dataset.body);
      setValue("[data-page-status]", button.dataset.status);
      const title = document.querySelector("[data-page-form-title]");
      if (title) title.textContent = "Edit page";
      document.querySelector("[data-page-title]")?.focus();
    });
  });

  document.querySelector("[data-reset-page-form]")?.addEventListener("click", () => {
    const form = document.querySelector("[data-page-id]")?.closest("form");
    if (form) form.reset();
    setValue("[data-page-id]", "0");
    const title = document.querySelector("[data-page-form-title]");
    if (title) title.textContent = "Create page";
  });

  document.querySelectorAll("[data-edit-nav]").forEach((button) => {
    button.addEventListener("click", () => {
      setValue("[data-nav-id]", button.dataset.id);
      setValue("[data-nav-label]", button.dataset.label);
      setValue("[data-nav-url]", button.dataset.url);
      setValue("[data-nav-sort]", button.dataset.sort);
      setChecked("[data-nav-visible]", button.dataset.visible);
      const title = document.querySelector("[data-nav-form-title]");
      if (title) title.textContent = "Edit link";
      document.querySelector("[data-nav-label]")?.focus();
    });
  });

  document.querySelector("[data-reset-nav-form]")?.addEventListener("click", () => {
    const form = document.querySelector("[data-nav-id]")?.closest("form");
    if (form) form.reset();
    setValue("[data-nav-id]", "0");
    const title = document.querySelector("[data-nav-form-title]");
    if (title) title.textContent = "Create link";
  });
})();
