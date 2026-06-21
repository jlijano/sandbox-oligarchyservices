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
