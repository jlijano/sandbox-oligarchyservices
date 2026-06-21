(function () {
  const shell = document.querySelector("[data-dashboard-shell]");
  if (!shell) return;

  const collapseButton = document.querySelector("[data-sidebar-collapse]");
  const mobileButton = document.querySelector("[data-mobile-menu]");
  const backdrop = document.querySelector("[data-sidebar-backdrop]");
  const sectionTitle = document.querySelector("[data-section-title]");
  const storageKey = "oligarchy_sidebar_collapsed";
  const iconMap = {
    overview: "fa-tachometer",
    requests: "fa-ticket",
    valley: "fa-sitemap",
    users: "fa-users",
    roles: "fa-id-badge",
    companies: "fa-building",
    departments: "fa-object-group",
    playground: "fa-flask",
    agents: "fa-android",
    prospects: "fa-address-card-o",
    pages: "fa-file-text-o",
    blogs: "fa-newspaper-o",
    navigation: "fa-bars",
    automation: "fa-bolt",
    settings: "fa-cog",
    "system settings": "fa-cog",
    activity: "fa-history",
    "system health": "fa-heartbeat",
    "mail trace": "fa-envelope-o"
  };

  const loadFontAwesome = () => {
    if (document.querySelector("link[data-font-awesome-sidebar]")) return;
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css";
    link.dataset.fontAwesomeSidebar = "true";
    document.head.appendChild(link);
  };

  const ensureProspectsNavigation = () => {
    const playgroundSubnav = document.querySelector("[data-playground-subnav]");
    if (!playgroundSubnav || playgroundSubnav.querySelector("a[href='/prospects.php']")) return;
    const link = document.createElement("a");
    link.href = "/prospects.php";
    link.innerHTML = '<span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Prospects</span>';
    const navigationLink = playgroundSubnav.querySelector("a[href='/dashboard.php#navigation']");
    if (navigationLink) {
      playgroundSubnav.insertBefore(link, navigationLink);
    } else {
      playgroundSubnav.appendChild(link);
    }
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

  const getLinkTarget = (link) => {
    try {
      const url = new URL(link.getAttribute("href") || "", window.location.href);
      return {
        path: url.pathname.replace(/\/+$/, "") || "/",
        hash: url.hash.replace("#", "").trim().toLowerCase()
      };
    } catch (error) {
      return { path: "", hash: "" };
    }
  };

  const setLinkActive = (link, isActive) => {
    link.classList.toggle("is-active", isActive);
    if (isActive) {
      link.setAttribute("aria-current", "page");
    } else {
      link.removeAttribute("aria-current");
    }
  };

  const syncSidebarGroups = () => {
    document.querySelectorAll("[data-valley-group], [data-playground-group], [data-settings-group]").forEach((group) => {
      const hasActiveLink = Boolean(group.querySelector("a.is-active"));
      group.classList.toggle("is-active", hasActiveLink);
      if (hasActiveLink) group.classList.add("is-open");
      const toggle = group.querySelector("[data-valley-toggle], [data-playground-toggle], [data-settings-toggle]");
      if (toggle) toggle.setAttribute("aria-expanded", String(group.classList.contains("is-open")));
    });
  };

  const syncSidebarActiveState = () => {
    const currentPath = window.location.pathname.replace(/\/+$/, "") || "/";
    const currentHash = window.location.hash.replace("#", "").trim().toLowerCase();
    const isDashboard = currentPath === "/dashboard.php";

    document.querySelectorAll(".sidebar-nav a").forEach((link) => {
      const target = getLinkTarget(link);
      const sectionId = link.dataset.sectionLink?.trim().toLowerCase() || "";
      const isSectionLink = Boolean(sectionId);
      const isActive = isSectionLink
        ? isDashboard && currentHash === sectionId
        : currentPath === target.path && (!target.hash || currentHash === target.hash);
      setLinkActive(link, isActive);
    });

    syncSidebarGroups();
  };

  ensureProspectsNavigation();
  applySidebarIcons();

  document.addEventListener("click", (event) => {
    const toggle = event.target.closest("[data-valley-toggle], [data-playground-toggle], [data-settings-toggle]");
    if (!toggle) return;
    const group = toggle.closest("[data-valley-group], [data-playground-group], [data-settings-group]");
    if (!group) return;
    if (shell.classList.contains("is-collapsed")) {
      shell.classList.remove("is-collapsed");
      if (collapseButton) collapseButton.setAttribute("aria-expanded", "true");
      window.localStorage.setItem(storageKey, "false");
    }
    const isOpen = !group.classList.contains("is-open");
    group.classList.toggle("is-open", isOpen);
    toggle.setAttribute("aria-expanded", String(isOpen));
  });

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
  const allowedIds = sections.map((section) => section.id).filter(Boolean);
  const normalizeHash = () => {
    const raw = window.location.hash.replace("#", "").trim().toLowerCase();
    return allowedIds.includes(raw) ? raw : allowedIds[0];
  };

  const showSection = (id) => {
    if (!id) return;
    sections.forEach((section) => {
      const isActive = section.id === id;
      section.classList.toggle("is-active", isActive);
      section.setAttribute("aria-hidden", String(!isActive));
      if (isActive && sectionTitle) sectionTitle.textContent = section.dataset.sectionLabel || section.id;
    });
    links.forEach((link) => setLinkActive(link, link.dataset.sectionLink === id));
    syncSidebarGroups();
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

  if (allowedIds.length) {
    window.addEventListener("hashchange", () => showSection(normalizeHash()));
    showSection(normalizeHash());
  } else {
    syncSidebarActiveState();
  }

  window.addEventListener("hashchange", syncSidebarActiveState);

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
  const userModal = document.querySelector("[data-user-modal]");
  const addUserButton = document.querySelector("[data-add-user]");
  const userCompany = document.querySelector("[data-user-company]");
  const userDepartment = document.querySelector("[data-user-department]");

  const syncDepartmentOptions = () => {
    if (!userCompany || !userDepartment) return;
    const companyId = userCompany.value;
    Array.from(userDepartment.options).forEach((option) => {
      if (!option.value) return;
      const optionCompany = option.dataset.companyId || "";
      const visible = !companyId || !optionCompany || optionCompany === companyId;
      option.hidden = !visible;
      if (!visible && option.selected) userDepartment.value = "";
    });
  };

  const openUserModal = () => {
    if (!userModal) return;
    userModal.hidden = false;
    document.body.classList.add("user-modal-open");
    window.setTimeout(() => document.querySelector("[data-user-name]")?.focus(), 0);
  };

  const closeUserModal = () => {
    if (!userModal) return;
    userModal.hidden = true;
    document.body.classList.remove("user-modal-open");
  };

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

  const resetUserForm = () => {
    if (userForm) userForm.reset();
    setValue("[data-user-id]", "0");
    setChecked("[data-user-active]", true);
    const title = document.querySelector("[data-user-form-title]");
    if (title) title.textContent = "Create user";
    syncUserPasswordMode();
    syncDepartmentOptions();
  };

  if (userForm && userPassword && userIdField) {
    userForm.addEventListener("submit", () => {
      const isCreate = userIdField.value === "0" || userIdField.value === "";
      if (isCreate && userPassword.value.length < 10) userPassword.value = clientPlaceholderPassword();
    });
    syncUserPasswordMode();
  }

  userCompany?.addEventListener("change", syncDepartmentOptions);
  addUserButton?.addEventListener("click", () => {
    resetUserForm();
    openUserModal();
  });
  document.querySelectorAll("[data-user-modal-close]").forEach((button) => button.addEventListener("click", closeUserModal));

  document.querySelectorAll("[data-edit-user]").forEach((button) => {
    button.addEventListener("click", () => {
      setValue("[data-user-id]", button.dataset.id);
      setValue("[data-user-name]", button.dataset.name);
      setValue("[data-user-email]", button.dataset.email);
      setValue("[data-user-role]", button.dataset.role);
      setValue("[data-user-company]", button.dataset.company);
      setValue("[data-user-department]", button.dataset.department);
      setChecked("[data-user-active]", button.dataset.active);
      const title = document.querySelector("[data-user-form-title]");
      if (title) title.textContent = "Edit user";
      syncUserPasswordMode();
      syncDepartmentOptions();
      openUserModal();
    });
  });

  document.querySelector("[data-reset-user-form]")?.addEventListener("click", () => {
    resetUserForm();
    closeUserModal();
  });

  const accessModal = document.querySelector("[data-access-modal]");
  const accessForm = document.querySelector("[data-access-id]")?.closest("form");
  const openAccessModal = () => {
    if (!accessModal) return;
    accessModal.hidden = false;
    document.body.classList.add("access-modal-open");
    window.setTimeout(() => document.querySelector("[data-access-name]")?.focus(), 0);
  };
  const closeAccessModal = () => {
    if (!accessModal) return;
    accessModal.hidden = true;
    document.body.classList.remove("access-modal-open");
  };
  const resetAccessForm = () => {
    if (accessForm) accessForm.reset();
    setValue("[data-access-id]", "0");
    setValue("[data-access-company]", "");
    setChecked("[data-access-active]", true);
    document.querySelectorAll("[data-access-module]").forEach((field) => { field.checked = false; });
    const title = document.querySelector("[data-access-form-title]");
    if (title) title.textContent = title.textContent.replace(/^Edit /, "Create ");
  };

  document.querySelector("[data-add-access]")?.addEventListener("click", () => {
    resetAccessForm();
    openAccessModal();
  });
  document.querySelectorAll("[data-access-modal-close]").forEach((button) => button.addEventListener("click", closeAccessModal));
  document.querySelector("[data-reset-access-form]")?.addEventListener("click", () => {
    resetAccessForm();
    closeAccessModal();
  });
  document.querySelectorAll("[data-edit-access]").forEach((button) => {
    button.addEventListener("click", () => {
      resetAccessForm();
      setValue("[data-access-id]", button.dataset.id);
      setValue("[data-access-name]", button.dataset.name);
      setValue("[data-access-notes]", button.dataset.notes);
      setValue("[data-access-company]", button.dataset.company);
      setChecked("[data-access-active]", button.dataset.active);
      const selected = (button.dataset.modules || "").split(",").filter(Boolean);
      document.querySelectorAll("[data-access-module]").forEach((field) => {
        field.checked = selected.includes(field.value);
      });
      const title = document.querySelector("[data-access-form-title]");
      if (title) title.textContent = title.textContent.replace(/^Create /, "Edit ");
      openAccessModal();
    });
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

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (userModal && !userModal.hidden) closeUserModal();
      if (accessModal && !accessModal.hidden) closeAccessModal();
    }
  });
})();
