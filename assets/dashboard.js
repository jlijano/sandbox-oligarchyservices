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
    carrier: "fa-envelope-o",
    switchboard: "fa-comments-o",
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

  const ensurePlaygroundNavigation = () => {
    const playgroundSubnav = document.querySelector("[data-playground-subnav]");
    if (!playgroundSubnav) return;
    let prospectsLink = playgroundSubnav.querySelector("a[href='/prospects.php']");
    if (!prospectsLink) {
      prospectsLink = document.createElement("a");
      prospectsLink.href = "/prospects.php";
      prospectsLink.innerHTML = '<span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Prospects</span>';
      const navigationLink = playgroundSubnav.querySelector("a[href='/dashboard.php#navigation']");
      if (navigationLink) playgroundSubnav.insertBefore(prospectsLink, navigationLink);
      else playgroundSubnav.appendChild(prospectsLink);
    }

    let carrierLink = playgroundSubnav.querySelector("a[href='/carrier']");
    if (!carrierLink) {
      carrierLink = document.createElement("a");
      carrierLink.href = "/carrier";
      carrierLink.innerHTML = '<span class="nav-icon" aria-hidden="true">C</span><span class="nav-label">Carrier</span>';
      prospectsLink.insertAdjacentElement("afterend", carrierLink);
    }

    let switchboardLink = playgroundSubnav.querySelector("a[href='/switchboard']");
    if (!switchboardLink) {
      switchboardLink = document.createElement("a");
      switchboardLink.href = "/switchboard";
      switchboardLink.innerHTML = '<span class="nav-icon" aria-hidden="true">S</span><span class="nav-label">Switchboard</span>';
      carrierLink.insertAdjacentElement("afterend", switchboardLink);
    }
  };

  const movePagesToDedicatedManager = () => {
    const currentPath = window.location.pathname.replace(/\/+$/, "") || "/";
    document.querySelectorAll("a[data-section-link='pages'], a[href='/dashboard.php#pages'], a[href='#pages']").forEach((link) => {
      link.href = "/pages.php";
      delete link.dataset.sectionLink;
      if (currentPath === "/pages.php") {
        link.classList.add("is-active");
        link.setAttribute("aria-current", "page");
      }
    });
    const inlinePagesSection = document.getElementById("pages");
    if (inlinePagesSection && currentPath === "/dashboard.php") {
      inlinePagesSection.remove();
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

  ensurePlaygroundNavigation();
  movePagesToDedicatedManager();
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

  const enhanceCarrierMailbox = () => {
    if (!document.body.classList.contains("carrier-body") || document.querySelector("[data-carrier-enhanced]")) return;
    const inbox = document.querySelector(".carrier-inbox");
    const list = document.querySelector(".carrier-list");
    const searchbar = document.querySelector(".carrier-searchbar");
    if (!inbox || !list || !searchbar) return;

    document.documentElement.dataset.carrierEnhanced = "true";
    const style = document.createElement("style");
    style.dataset.carrierEnhanced = "true";
    style.textContent = `
      .carrier-row { grid-template-columns: 34px 34px minmax(0, 1fr); }
      .carrier-bulk-cell { display: grid; place-items: center; }
      .carrier-bulk-check, .carrier-select-all { width: 16px; height: 16px; accent-color: #b00714; }
      .carrier-bulk-toolbar, .carrier-advanced-filters { display: grid; gap: 8px; align-items: center; border-bottom: 1px solid rgba(120,124,132,.3); background: #111114; padding: 9px 10px; }
      .carrier-bulk-toolbar { grid-template-columns: auto minmax(126px, .26fr) minmax(126px, .26fr) auto minmax(0, 1fr); }
      .carrier-advanced-filters { grid-template-columns: repeat(3, minmax(112px, 1fr)); }
      .carrier-bulk-toolbar label, .carrier-bulk-toolbar output { color: #aeb3bd; font-size: .78rem; font-weight: 750; }
      .carrier-bulk-toolbar select, .carrier-advanced-filters select { min-height: 32px; border: 1px solid rgba(90,93,99,.7); border-radius: 3px; background: #0d0d10; color: #f4f5f7; padding: 0 8px; }
      .carrier-bulk-toolbar button { min-height: 32px; border: 1px solid #c40917; border-radius: 3px; background: #a40712; color: #fff; padding: 0 12px; font-weight: 800; }
      .carrier-bulk-toolbar button:disabled { opacity: .52; cursor: not-allowed; }
      .carrier-quick-status { margin: 0 0 0 auto; }
      .carrier-quick-status select { min-height: 28px; max-width: 126px; border: 1px solid rgba(120,124,132,.42); border-radius: 999px; background: rgba(255,255,255,.05); color: #fff; font-size: .72rem; font-weight: 850; padding: 0 8px; }
      .carrier-context-panel { display: grid; gap: 9px; margin: 14px 20px 0; border: 1px solid rgba(120,124,132,.28); border-radius: 6px; background: #141417; padding: 12px; }
      .carrier-context-panel h3 { margin: 0; color: #f4f5f7; font-size: .92rem; }
      .carrier-context-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
      .carrier-context-grid span { display: grid; gap: 2px; min-width: 0; color: #9da3ad; font-size: .72rem; font-weight: 750; text-transform: uppercase; }
      .carrier-context-grid strong, .carrier-context-grid a { min-width: 0; overflow-wrap: anywhere; color: #f4f5f7; font-size: .84rem; text-transform: none; }
      .carrier-context-actions { display: flex; flex-wrap: wrap; gap: 8px; }
      @media (max-width: 900px) { .carrier-bulk-toolbar, .carrier-advanced-filters { grid-template-columns: 1fr 1fr; } .carrier-bulk-toolbar output { grid-column: 1 / -1; } }
      @media (max-width: 700px) { .carrier-row { grid-template-columns: 34px 34px minmax(0, 1fr); } .carrier-bulk-toolbar, .carrier-advanced-filters, .carrier-context-grid { grid-template-columns: 1fr; } .carrier-quick-status { margin: 4px 0 0; } }
    `;
    document.head.appendChild(style);

    const rows = Array.from(list.querySelectorAll(".carrier-row"));
    rows.forEach((row) => {
      const id = row.querySelector("input[name='carrier_id']")?.value || "";
      const sender = row.querySelector(".sender")?.textContent?.trim() || "";
      const subject = row.querySelector(".subject strong")?.textContent?.trim() || "";
      const preview = row.querySelector(".subject small")?.textContent?.trim() || "";
      const status = row.querySelector(".status")?.textContent?.trim() || "";
      const priority = row.querySelector(".priority")?.textContent?.trim() || "";
      const date = row.querySelector("time")?.getAttribute("datetime") || "";
      row.dataset.carrierText = `${sender} ${subject} ${preview}`.toLowerCase();
      row.dataset.carrierStatus = status;
      row.dataset.carrierPriority = priority;
      row.dataset.carrierDate = date;
      row.dataset.carrierRead = row.classList.contains("is-unread") ? "unread" : "read";
      if (!row.querySelector(".carrier-bulk-cell")) {
        const cell = document.createElement("label");
        cell.className = "carrier-bulk-cell";
        cell.innerHTML = `<span class="sr-only">Select ${subject || "carrier email"}</span><input class="carrier-bulk-check" type="checkbox" value="${id}">`;
        row.insertBefore(cell, row.firstElementChild);
      }
    });

    const advanced = document.createElement("div");
    advanced.className = "carrier-advanced-filters";
    advanced.innerHTML = `
      <select data-carrier-local-read aria-label="Filter loaded messages by read state"><option value="">Read state</option><option value="unread">Unread only</option><option value="read">Read only</option></select>
      <select data-carrier-local-priority aria-label="Filter loaded messages by priority"><option value="">All priorities</option><option value="High">High</option><option value="Normal">Normal</option><option value="Low">Low</option></select>
      <select data-carrier-local-sort aria-label="Sort loaded messages"><option value="current">Current order</option><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="sender">Sender A-Z</option><option value="status">Status A-Z</option><option value="priority">Priority</option></select>
    `;
    searchbar.insertAdjacentElement("afterend", advanced);

    const bulk = document.createElement("form");
    bulk.className = "carrier-bulk-toolbar";
    bulk.dataset.carrierEnhanced = "true";
    bulk.innerHTML = `
      <label><input class="carrier-select-all" type="checkbox" data-carrier-select-all> Select visible</label>
      <select name="bulk_action" aria-label="Bulk action"><option value="mark_read">Mark read</option><option value="mark_unread">Mark unread</option><option value="archive_carrier_email">Archive</option></select>
      <select name="bulk_scope" aria-label="Bulk scope"><option value="selected">Selected messages</option><option value="visible">All visible messages</option></select>
      <button type="submit" disabled>Apply</button>
      <output>0 selected</output>
    `;
    advanced.insertAdjacentElement("afterend", bulk);

    const visibleRows = () => rows.filter((row) => !row.hidden);
    const checkedBoxes = () => rows.map((row) => row.querySelector(".carrier-bulk-check")).filter((box) => box?.checked && !box.closest(".carrier-row")?.hidden);
    const syncBulkState = () => {
      const selected = checkedBoxes().length;
      bulk.querySelector("button").disabled = selected === 0 && bulk.elements.bulk_scope.value === "selected";
      bulk.querySelector("output").textContent = `${selected} selected`;
    };

    const applyLocalFilters = () => {
      const readFilter = advanced.querySelector("[data-carrier-local-read]").value;
      const priorityFilter = advanced.querySelector("[data-carrier-local-priority]").value;
      const sort = advanced.querySelector("[data-carrier-local-sort]").value;
      rows.forEach((row) => {
        const showRead = !readFilter || row.dataset.carrierRead === readFilter;
        const showPriority = !priorityFilter || row.dataset.carrierPriority === priorityFilter;
        row.hidden = !(showRead && showPriority);
      });
      const priorityRank = { High: 0, Normal: 1, Low: 2 };
      if (sort !== "current") {
        [...rows].sort((a, b) => {
          if (sort === "newest") return String(b.dataset.carrierDate).localeCompare(String(a.dataset.carrierDate));
          if (sort === "oldest") return String(a.dataset.carrierDate).localeCompare(String(b.dataset.carrierDate));
          if (sort === "sender") return String(a.querySelector(".sender")?.textContent || "").localeCompare(String(b.querySelector(".sender")?.textContent || ""));
          if (sort === "status") return String(a.dataset.carrierStatus).localeCompare(String(b.dataset.carrierStatus));
          if (sort === "priority") return (priorityRank[a.dataset.carrierPriority] ?? 9) - (priorityRank[b.dataset.carrierPriority] ?? 9);
          return 0;
        }).forEach((row) => list.appendChild(row));
      }
      syncBulkState();
    };

    advanced.addEventListener("change", applyLocalFilters);
    bulk.addEventListener("change", (event) => {
      if (event.target.matches("[data-carrier-select-all]")) {
        visibleRows().forEach((row) => {
          const box = row.querySelector(".carrier-bulk-check");
          if (box) box.checked = event.target.checked;
        });
      }
      syncBulkState();
    });
    list.addEventListener("change", (event) => {
      if (event.target.matches(".carrier-bulk-check")) syncBulkState();
    });
    bulk.addEventListener("submit", async (event) => {
      event.preventDefault();
      const action = bulk.elements.bulk_action.value;
      const targets = bulk.elements.bulk_scope.value === "visible"
        ? visibleRows().map((row) => row.querySelector(".carrier-bulk-check")).filter(Boolean)
        : checkedBoxes();
      if (!targets.length) return;
      const submitButton = bulk.querySelector("button");
      submitButton.disabled = true;
      submitButton.textContent = "Applying...";
      try {
        for (const box of targets) {
          const row = box.closest(".carrier-row");
          const sourceForm = row?.querySelector(".star-form");
          if (!sourceForm) continue;
          const formData = new FormData(sourceForm);
          formData.set("carrier_id", box.value);
          formData.set("action", action);
          await window.fetch("/carrier", { method: "POST", body: formData, credentials: "same-origin" });
        }
        window.location.reload();
      } catch (error) {
        submitButton.textContent = "Retry";
        submitButton.disabled = false;
        window.alert("Carrier bulk action could not finish. Please refresh and try again.");
      }
    });

    const editForm = document.querySelector("#edit-carrier form");
    const editStatus = editForm?.querySelector("select[name='status']");
    const previewActions = document.querySelector(".preview-actions");
    if (editForm && editStatus && previewActions && !previewActions.querySelector(".carrier-quick-status")) {
      const quick = document.createElement("label");
      quick.className = "carrier-quick-status";
      quick.innerHTML = `<span class="sr-only">Quick status</span><select aria-label="Quickly change carrier status">${Array.from(editStatus.options).map((option) => `<option value="${option.value}" ${option.selected ? "selected" : ""}>${option.textContent}</option>`).join("")}</select>`;
      quick.querySelector("select").addEventListener("change", (event) => {
        editStatus.value = event.target.value;
        if (editForm.requestSubmit) editForm.requestSubmit();
        else editForm.submit();
      });
      previewActions.appendChild(quick);
    }

    const previewCard = document.querySelector(".preview-card");
    const previewMessage = document.querySelector(".preview-message");
    if (previewCard && previewMessage && !previewCard.querySelector(".carrier-context-panel")) {
      const fromText = document.querySelector(".preview-from")?.textContent?.trim() || "";
      const subject = document.querySelector(".reading-pane-header h2")?.textContent?.trim() || "Carrier email";
      const emailMatch = fromText.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
      const email = emailMatch ? emailMatch[0] : "";
      const status = document.querySelector(".preview-actions .status")?.textContent?.trim() || "";
      const priority = document.querySelector(".preview-meta")?.textContent?.split(" priority")?.[0]?.split(" · ")?.pop()?.trim() || "";
      const panel = document.createElement("section");
      panel.className = "carrier-context-panel";
      panel.innerHTML = `
        <h3>Lead context</h3>
        <div class="carrier-context-grid">
          <span>Contact<strong>${fromText.replace(/</g, "&lt;") || "No contact name"}</strong></span>
          <span>Email${email ? `<a href="mailto:${email}">${email}</a>` : "<strong>No sender email</strong>"}</span>
          <span>Status<strong>${status || "Not set"}</strong></span>
          <span>Priority<strong>${priority || "Not set"}</strong></span>
        </div>
        <div class="carrier-context-actions">
          ${email ? `<a class="secondary-action" href="mailto:${email}?subject=${encodeURIComponent("Follow up: " + subject)}">Follow up</a>` : ""}
          <a class="secondary-action" href="#edit-carrier">Add note / update record</a>
        </div>
      `;
      previewMessage.insertAdjacentElement("beforebegin", panel);
    }

    syncBulkState();
  };

  enhanceCarrierMailbox();

  const enhanceCarrierComposeWindows = () => {
    if (!document.body.classList.contains("carrier-body")) return;
    const modals = ["compose-carrier", "reply-carrier", "forward-carrier"]
      .map((id) => document.getElementById(id))
      .filter(Boolean);
    if (!modals.length) return;

    if (!document.querySelector("style[data-carrier-compose-window]")) {
      const style = document.createElement("style");
      style.dataset.carrierComposeWindow = "true";
      style.textContent = `
        #compose-carrier.carrier-modal:target,
        #reply-carrier.carrier-modal:target,
        #forward-carrier.carrier-modal:target {
          inset: auto 24px 0 auto;
          width: min(540px, calc(100vw - 32px));
          height: min(480px, calc(100dvh - 88px));
          max-height: min(480px, calc(100dvh - 88px));
          transform: none;
          overflow: visible;
          border-radius: 8px 8px 0 0;
          box-shadow: 0 0 0 100vmax rgba(0,0,0,.14), 0 18px 48px rgba(0,0,0,.5);
        }
        #reply-carrier.carrier-modal:target,
        #forward-carrier.carrier-modal:target {
          width: min(600px, calc(100vw - 32px));
          height: min(520px, calc(100dvh - 88px));
          max-height: min(520px, calc(100dvh - 88px));
        }
        #compose-carrier .carrier-form,
        #reply-carrier .carrier-form,
        #forward-carrier .carrier-form {
          height: 100%;
          max-height: none;
        }
        #compose-carrier .carrier-form h2,
        #reply-carrier .carrier-form h2,
        #forward-carrier .carrier-form h2 {
          padding-right: 106px;
          cursor: default;
          user-select: none;
        }
        .compose-window-controls {
          position: absolute;
          top: 7px;
          right: 42px;
          z-index: 6;
          display: flex;
          align-items: center;
          gap: 2px;
        }
        .compose-window-control {
          display: grid;
          place-items: center;
          width: 28px;
          height: 28px;
          border: 0;
          border-radius: 3px;
          background: transparent;
          color: #d7dbe2;
          font: 800 1rem/1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
          cursor: pointer;
        }
        .compose-window-control:hover,
        .compose-window-control:focus-visible {
          background: rgba(255,255,255,.1);
          color: #fff;
          outline: 0;
        }
        .carrier-modal.is-minimized:target {
          width: min(320px, calc(100vw - 20px)) !important;
          height: 42px !important;
          max-height: 42px !important;
          overflow: hidden !important;
          box-shadow: 0 12px 32px rgba(0,0,0,.44) !important;
        }
        .carrier-modal.is-minimized .carrier-form {
          height: 42px !important;
          grid-template-rows: 42px !important;
          overflow: hidden !important;
        }
        .carrier-modal.is-minimized .carrier-form-grid,
        .carrier-modal.is-minimized .button.primary {
          display: none !important;
        }
        .carrier-modal.is-minimized .carrier-form h2 {
          min-height: 42px !important;
          border-bottom: 0 !important;
          cursor: pointer;
        }
        .carrier-modal.is-maximized:target {
          inset: 82px 24px 24px 292px !important;
          width: auto !important;
          height: auto !important;
          max-height: none !important;
          border-radius: 8px !important;
          box-shadow: 0 0 0 100vmax rgba(0,0,0,.22), 0 18px 56px rgba(0,0,0,.52) !important;
        }
        .carrier-modal.is-maximized .carrier-form {
          border-radius: 8px !important;
        }
        .carrier-modal.is-maximized .carrier-form-grid {
          overflow: auto;
        }
        @media (max-width: 980px) {
          .carrier-modal.is-maximized:target {
            inset: 72px 12px 12px 12px !important;
          }
        }
        @media (max-width: 700px) {
          #compose-carrier.carrier-modal:target,
          #reply-carrier.carrier-modal:target,
          #forward-carrier.carrier-modal:target {
            inset: auto 8px 0 8px;
            width: auto;
            height: min(500px, calc(100dvh - 36px));
            max-height: calc(100dvh - 36px);
          }
          .carrier-modal.is-minimized:target {
            width: auto !important;
            left: 8px !important;
            right: 8px !important;
          }
          .compose-window-controls {
            right: 38px;
          }
        }
      `;
      document.head.appendChild(style);
    }

    const syncControls = (modal) => {
      const maximize = modal.querySelector("[data-compose-maximize]");
      const isMaximized = modal.classList.contains("is-maximized");
      if (maximize) {
        maximize.setAttribute("aria-pressed", String(isMaximized));
        maximize.setAttribute("aria-label", isMaximized ? "Restore compose window" : "Maximize compose window");
        maximize.title = isMaximized ? "Restore" : "Maximize";
      }
    };

    modals.forEach((modal) => {
      if (!modal.querySelector(".compose-window-controls")) {
        const controls = document.createElement("div");
        controls.className = "compose-window-controls";
        controls.setAttribute("aria-label", "Compose window controls");
        controls.innerHTML = `
          <button class="compose-window-control" type="button" data-compose-minimize aria-label="Minimize compose window" title="Minimize">-</button>
          <button class="compose-window-control" type="button" data-compose-maximize aria-label="Maximize compose window" aria-pressed="false" title="Maximize">□</button>
        `;
        const close = modal.querySelector(".modal-close");
        if (close) close.insertAdjacentElement("afterend", controls);
        else modal.insertAdjacentElement("afterbegin", controls);
      }

      modal.querySelector("[data-compose-minimize]")?.addEventListener("click", () => {
        modal.classList.add("is-minimized");
        modal.classList.remove("is-maximized");
        syncControls(modal);
      });
      modal.querySelector("[data-compose-maximize]")?.addEventListener("click", () => {
        const shouldMaximize = !modal.classList.contains("is-maximized");
        modal.classList.toggle("is-maximized", shouldMaximize);
        modal.classList.remove("is-minimized");
        syncControls(modal);
      });
      modal.querySelector(".carrier-form h2")?.addEventListener("click", () => {
        if (!modal.classList.contains("is-minimized")) return;
        modal.classList.remove("is-minimized");
        syncControls(modal);
      });
      syncControls(modal);
    });

    window.addEventListener("hashchange", () => {
      modals.forEach((modal) => {
        if (window.location.hash !== `#${modal.id}`) {
          modal.classList.remove("is-minimized", "is-maximized");
        }
        syncControls(modal);
      });
    });
  };

  enhanceCarrierComposeWindows();

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
