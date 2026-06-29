(function () {
  const playgroundSubnav = document.querySelector("[data-playground-subnav]");
  if (playgroundSubnav && !playgroundSubnav.querySelector("a[href='/prospects.php']")) {
    const link = document.createElement("a");
    link.href = "/prospects.php";
    link.className = "is-active";
    link.setAttribute("aria-current", "page");
    link.innerHTML = '<span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Prospects</span>';
    playgroundSubnav.appendChild(link);
    const group = playgroundSubnav.closest("[data-playground-group]");
    group?.classList.add("is-open", "is-active");
    group?.querySelector("[data-playground-toggle]")?.setAttribute("aria-expanded", "true");
  }

  const heroActions = document.querySelector(".prospects-header .hero-actions");
  if (heroActions && !heroActions.querySelector("a[href='/prospect-sync.php']")) {
    const syncLink = document.createElement("a");
    syncLink.href = "/prospect-sync.php";
    syncLink.className = "secondary-action";
    syncLink.textContent = "Sync Google Sheet";
    heroActions.appendChild(syncLink);
  }

  const modalIds = ["prospect-form", "prospect-import", "prospect-detail"];
  const modals = modalIds.map((id) => document.getElementById(id)).filter(Boolean);
  const closeModal = () => {
    if (!modalIds.includes(window.location.hash.slice(1))) return;
    history.pushState("", document.title, window.location.pathname + window.location.search);
    document.body.classList.remove("prospect-modal-open");
  };
  const syncModalState = () => {
    const activeId = window.location.hash.slice(1);
    document.body.classList.toggle("prospect-modal-open", modalIds.includes(activeId));
  };

  modals.forEach((modal) => {
    if (!modal.querySelector("[data-prospect-modal-close]")) {
      const close = document.createElement("a");
      close.href = window.location.pathname + window.location.search.replace(/([?&])open=[^&]*&?/, "$1").replace(/[?&]$/, "");
      close.className = "prospect-modal-close";
      close.setAttribute("aria-label", "Close dialog");
      close.setAttribute("data-prospect-modal-close", "");
      close.textContent = "×";
      modal.insertBefore(close, modal.firstChild);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeModal();
  });
  document.addEventListener("click", (event) => {
    const activeId = window.location.hash.slice(1);
    if (!modalIds.includes(activeId)) return;
    const activeModal = document.getElementById(activeId);
    if (activeModal && !activeModal.contains(event.target) && !event.target.closest("a[href^='#prospect-']")) closeModal();
  });
  window.addEventListener("hashchange", syncModalState);
  syncModalState();

  const viewButtons = Array.from(document.querySelectorAll("[data-prospect-view-button]"));
  const views = Array.from(document.querySelectorAll("[data-prospect-view]"));

  const showView = (viewName) => {
    viewButtons.forEach((button) => {
      const isActive = button.dataset.prospectViewButton === viewName;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-selected", String(isActive));
    });
    views.forEach((view) => {
      const isActive = view.dataset.prospectView === viewName;
      view.classList.toggle("is-active", isActive);
      view.hidden = !isActive;
    });
  };

  viewButtons.forEach((button) => {
    button.addEventListener("click", () => showView(button.dataset.prospectViewButton));
  });

  const search = document.querySelector("[data-prospect-search]");
  const status = document.querySelector("[data-prospect-status]");
  const owner = document.querySelector("[data-prospect-owner]");
  const priority = document.querySelector("[data-prospect-priority]");
  const count = document.querySelector("[data-prospect-result-count]");
  const rows = Array.from(document.querySelectorAll("[data-prospect-row]"));

  const applyFilters = () => {
    const query = (search?.value || "").trim().toLowerCase();
    const statusValue = status?.value || "";
    const ownerValue = owner?.value || "";
    const priorityValue = priority?.value || "";
    let visible = 0;

    rows.forEach((row) => {
      const matches = (!query || row.dataset.search.includes(query))
        && (!statusValue || row.dataset.status === statusValue)
        && (!ownerValue || row.dataset.owner === ownerValue)
        && (!priorityValue || row.dataset.priority === priorityValue);
      row.hidden = !matches;
      if (matches) visible += 1;
    });

    if (count) count.textContent = `${visible} prospect${visible === 1 ? "" : "s"}`;
  };

  [search, status, owner, priority].forEach((control) => {
    control?.addEventListener("input", applyFilters);
    control?.addEventListener("change", applyFilters);
  });

  document.querySelector("[data-customize-dashboard]")?.addEventListener("click", () => {
    document.querySelectorAll("[data-prospect-widget]").forEach((widget) => {
      widget.classList.toggle("is-configuring");
    });
  });

  document.querySelectorAll("[data-prospect-widget] .widget-actions button").forEach((button) => {
    button.addEventListener("click", () => {
      const widget = button.closest("[data-prospect-widget]");
      if (!widget) return;
      if (button.textContent.trim().toLowerCase() === "remove") {
        widget.classList.toggle("is-muted");
      } else {
        widget.classList.toggle("is-configuring");
      }
    });
  });
})();