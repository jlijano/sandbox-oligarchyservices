(function () {
  const importTemplateHref = "/assets/prospects-import-template.csv";
  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  const playgroundSubnav = qs("[data-playground-subnav]");
  if (playgroundSubnav && !qs("a[href='/prospects.php']", playgroundSubnav)) {
    const link = document.createElement("a");
    link.href = "/prospects.php";
    link.className = "is-active";
    link.setAttribute("aria-current", "page");
    link.innerHTML = '<span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Prospects</span>';
    playgroundSubnav.appendChild(link);
    const group = playgroundSubnav.closest("[data-playground-group]");
    group?.classList.add("is-open", "is-active");
    qs("[data-playground-toggle]", group)?.setAttribute("aria-expanded", "true");
  }

  const addActionLink = (parent, href, text, downloadName) => {
    if (!parent || qs(`a[href='${href}']`, parent)) return;
    const link = document.createElement("a");
    link.href = href;
    link.className = "secondary-action";
    link.textContent = text;
    if (downloadName) link.download = downloadName;
    parent.appendChild(link);
  };

  addActionLink(qs(".prospects-header .hero-actions"), importTemplateHref, "Export CSV Template", "prospects-import-template.csv");
  addActionLink(qs(".prospects-header .hero-actions"), "/prospect-sync.php", "Sync Google Sheet");
  addActionLink(qs("#prospect-import .table-heading"), importTemplateHref, "Export CSV Template", "prospects-import-template.csv");

  const modalIds = ["prospect-form", "prospect-import", "prospect-detail"];
  const closeModal = () => {
    if (!modalIds.includes(window.location.hash.slice(1))) return;
    history.pushState("", document.title, window.location.pathname + window.location.search);
    document.body.classList.remove("prospect-modal-open");
  };
  const syncModalState = () => {
    document.body.classList.toggle("prospect-modal-open", modalIds.includes(window.location.hash.slice(1)));
  };

  modalIds.map((id) => document.getElementById(id)).filter(Boolean).forEach((modal) => {
    if (qs("[data-prospect-modal-close]", modal)) return;
    const close = document.createElement("a");
    close.href = window.location.pathname + window.location.search.replace(/([?&])open=[^&]*&?/, "$1").replace(/[?&]$/, "");
    close.className = "prospect-modal-close";
    close.setAttribute("aria-label", "Close dialog");
    close.setAttribute("data-prospect-modal-close", "");
    close.textContent = "x";
    modal.insertBefore(close, modal.firstChild);
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

  const viewButtons = qsa("[data-prospect-view-button]");
  const views = qsa("[data-prospect-view]");
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
  viewButtons.forEach((button) => button.addEventListener("click", () => showView(button.dataset.prospectViewButton)));

  const search = qs("[data-prospect-search]");
  const status = qs("[data-prospect-status]");
  const owner = qs("[data-prospect-owner]");
  const priority = qs("[data-prospect-priority]");
  const count = qs("[data-prospect-result-count]");
  const rows = qsa("[data-prospect-row]");
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

  const board = qs(".prospect-board");
  const csrfToken = qs("input[name='csrf_token']")?.value || "";
  if (board) {
    let draggedCard = null;
    let originalColumn = null;
    let originalNextSibling = null;
    let originalStatus = "";

    const message = document.createElement("div");
    message.className = "kanban-status-message";
    message.setAttribute("role", "status");
    message.hidden = true;
    board.insertAdjacentElement("beforebegin", message);

    const setMessage = (text, isError = false) => {
      message.textContent = text;
      message.hidden = text === "";
      message.classList.toggle("is-error", isError);
      if (text !== "") window.setTimeout(() => setMessage(""), 3500);
    };
    const cardId = (card) => {
      const href = qs("a[href*='open=']", card)?.getAttribute("href") || "";
      try {
        return new URL(href, window.location.origin).searchParams.get("open") || "";
      } catch (error) {
        return "";
      }
    };
    const columnStatus = (column) => qs(".kanban-column-header h3", column)?.textContent.trim() || "";
    const updateColumnCount = (column) => {
      const badge = qs(".kanban-column-header span", column);
      if (badge) badge.textContent = String(qsa(".kanban-card", column).length);
    };
    const updateAllCounts = () => qsa(".kanban-column").forEach(updateColumnCount);
    const clearDropTargets = () => qsa(".kanban-column.is-drop-target").forEach((column) => column.classList.remove("is-drop-target"));
    const resetDragState = () => {
      draggedCard = null;
      originalColumn = null;
      originalNextSibling = null;
      originalStatus = "";
    };
    const moveCardBack = (card, column, nextSibling, statusValue) => {
      if (!card || !column) return;
      if (nextSibling && nextSibling.parentElement === column) column.insertBefore(card, nextSibling);
      else column.appendChild(card);
      card.dataset.kanbanStatus = statusValue;
      updateAllCounts();
    };
    const saveStatus = async (card, statusValue) => {
      const prospectId = card.dataset.prospectId || cardId(card);
      if (!prospectId || !csrfToken) throw new Error("Missing prospect update token. Refresh and try again.");
      const form = new FormData();
      form.append("csrf_token", csrfToken);
      form.append("prospect_id", prospectId);
      form.append("status", statusValue);
      const response = await fetch("/prospect-status.php", {
        method: "POST",
        body: form,
        credentials: "same-origin",
        headers: { "Accept": "application/json" },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload.ok !== true) throw new Error(payload.message || "Could not update prospect status.");
      return payload;
    };

    qsa(".kanban-column").forEach((column) => {
      column.dataset.kanbanStatus = columnStatus(column);
      column.setAttribute("aria-label", `${column.dataset.kanbanStatus} prospects`);
      column.addEventListener("dragover", (event) => {
        if (!draggedCard) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
      });
      column.addEventListener("dragenter", () => {
        if (draggedCard) column.classList.add("is-drop-target");
      });
      column.addEventListener("dragleave", (event) => {
        if (!column.contains(event.relatedTarget)) column.classList.remove("is-drop-target");
      });
      column.addEventListener("drop", async (event) => {
        event.preventDefault();
        clearDropTargets();
        if (!draggedCard) return;
        const newStatus = column.dataset.kanbanStatus || columnStatus(column);
        if (!newStatus || newStatus === originalStatus) return;

        const activeCard = draggedCard;
        const rollbackColumn = originalColumn;
        const rollbackNextSibling = originalNextSibling;
        const rollbackStatus = originalStatus;
        column.appendChild(activeCard);
        activeCard.dataset.kanbanStatus = newStatus;
        activeCard.classList.add("is-saving");
        updateAllCounts();

        try {
          await saveStatus(activeCard, newStatus);
          setMessage(`Status updated to ${newStatus}.`);
        } catch (error) {
          moveCardBack(activeCard, rollbackColumn, rollbackNextSibling, rollbackStatus);
          setMessage(error.message || "Could not update prospect status.", true);
        } finally {
          activeCard.classList.remove("is-saving");
          resetDragState();
        }
      });
    });

    qsa(".kanban-card").forEach((card) => {
      const id = cardId(card);
      if (!id) return;
      card.dataset.prospectId = id;
      card.dataset.kanbanStatus = columnStatus(card.closest(".kanban-column"));
      card.setAttribute("draggable", "true");
      card.setAttribute("tabindex", "0");
      card.setAttribute("aria-grabbed", "false");
      card.title = "Drag to another status column";
      card.addEventListener("dragstart", (event) => {
        draggedCard = card;
        originalColumn = card.closest(".kanban-column");
        originalNextSibling = card.nextElementSibling;
        originalStatus = card.dataset.kanbanStatus || columnStatus(originalColumn);
        card.classList.add("is-dragging");
        card.setAttribute("aria-grabbed", "true");
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", id);
      });
      card.addEventListener("dragend", () => {
        card.classList.remove("is-dragging");
        card.setAttribute("aria-grabbed", "false");
        clearDropTargets();
        if (!card.classList.contains("is-saving")) resetDragState();
      });
    });
  }

  const customizeButton = qs("[data-customize-dashboard]");
  const dashboardView = qs("[data-prospect-view='dashboard']");
  const widgetGrid = qs(".prospect-widget-grid");
  const widgets = qsa("[data-prospect-widget]");
  let isCustomizing = false;
  const setCustomizing = (enabled) => {
    isCustomizing = enabled;
    dashboardView?.classList.toggle("is-customizing", enabled);
    customizeButton?.classList.toggle("is-active", enabled);
    if (customizeButton) {
      customizeButton.textContent = enabled ? "Done customizing" : "Customize dashboard";
      customizeButton.setAttribute("aria-pressed", String(enabled));
    }
    if (!enabled) widgets.forEach((widget) => widget.classList.remove("is-configuring"));
  };
  customizeButton?.setAttribute("aria-pressed", "false");
  customizeButton?.addEventListener("click", () => setCustomizing(!isCustomizing));
  qsa("[data-prospect-widget] .widget-actions button").forEach((button) => {
    button.addEventListener("click", () => {
      if (!isCustomizing) return;
      const widget = button.closest("[data-prospect-widget]");
      if (!widget || !widgetGrid) return;
      const action = button.textContent.trim().toLowerCase();
      if (action === "remove") {
        widget.classList.toggle("is-muted");
        button.textContent = widget.classList.contains("is-muted") ? "Restore" : "Remove";
        return;
      }
      if (action === "move") {
        widgetGrid.insertBefore(widget, widgetGrid.firstElementChild);
        widget.classList.add("is-configuring");
        return;
      }
      widget.classList.toggle("is-configuring");
    });
  });
})();