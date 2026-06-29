(function () {
  const importTemplateHref = "/assets/prospects-import-template.csv";
  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const storageKey = "oligarchy.prospects.dashboard.v1";

  const injectDashboardStyles = () => {
    if (document.getElementById("prospect-dashboard-customizer-styles")) return;
    const style = document.createElement("style");
    style.id = "prospect-dashboard-customizer-styles";
    style.textContent = `
      .dashboard-customizer-bar{display:none;grid-template-columns:minmax(220px,1fr) auto auto;gap:10px;align-items:center;border:1px solid rgba(158,167,184,.3);border-radius:8px;padding:10px;background:rgba(10,12,16,.88)}
      .prospect-view.is-customizing .dashboard-customizer-bar{display:grid}
      .dashboard-customizer-bar select,.dashboard-customizer-bar input{min-height:38px;border:1px solid rgba(158,167,184,.34);border-radius:6px;background:rgba(7,8,11,.72);color:var(--prospect-text);padding:8px 10px}
      .dashboard-customizer-bar button,.widget-actions button{min-height:32px;border:1px solid rgba(158,167,184,.32);border-radius:6px;background:rgba(255,255,255,.035);color:#fff;font-weight:850;cursor:pointer}
      .dashboard-customizer-bar button:hover,.widget-actions button:hover{border-color:rgba(49,196,189,.55);background:rgba(49,196,189,.1)}
      .prospect-widget-grid{grid-auto-flow:dense;grid-auto-rows:minmax(150px,auto)}
      .prospect-widget{grid-column:span var(--widget-cols,1);grid-row:span var(--widget-rows,1);min-height:calc(150px * var(--widget-rows,1));touch-action:none}
      .prospect-view.is-customizing .prospect-widget{outline:1px dashed rgba(49,196,189,.35);outline-offset:3px;cursor:grab}
      .prospect-widget.is-dashboard-dragging{opacity:.55;transform:scale(.985);cursor:grabbing}
      .prospect-widget.is-drop-before{box-shadow:-5px 0 0 rgba(49,196,189,.85),var(--prospect-shadow-soft)}
      .prospect-widget.is-drop-after{box-shadow:5px 0 0 rgba(49,196,189,.85),var(--prospect-shadow-soft)}
      .widget-actions{z-index:2;flex-wrap:wrap}
      .widget-actions button[data-dashboard-size]{min-width:34px;padding:0 8px}
      .dashboard-resize-handle{display:none;position:absolute;right:8px;bottom:8px;width:18px;height:18px;border-right:2px solid rgba(49,196,189,.8);border-bottom:2px solid rgba(49,196,189,.8);cursor:nwse-resize;z-index:3}
      .prospect-view.is-customizing .dashboard-resize-handle{display:block}
      .dashboard-chart{display:grid;gap:8px;margin-top:12px}
      .dashboard-bar{display:grid;grid-template-columns:minmax(82px,.8fr) minmax(80px,1.5fr) 42px;gap:8px;align-items:center;color:var(--prospect-muted);font-size:.78rem;font-weight:850}
      .dashboard-bar-track{height:10px;border-radius:999px;background:rgba(255,255,255,.07);overflow:hidden}
      .dashboard-bar-fill{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--prospect-red),var(--prospect-teal))}
      .dashboard-report-list{display:grid;gap:8px;margin-top:12px;color:var(--prospect-muted);font-size:.86rem}
      .dashboard-report-list strong{color:#fff;font-size:1rem}
      .dashboard-editor{position:fixed;inset:0;z-index:140;display:grid;place-items:center;background:rgba(0,0,0,.68);padding:16px}
      .dashboard-editor[hidden]{display:none}
      .dashboard-editor form{display:grid;gap:12px;width:min(520px,100%);border:1px solid rgba(158,167,184,.45);border-radius:8px;padding:16px;background:#11141a;box-shadow:0 24px 80px rgba(0,0,0,.52)}
      .dashboard-editor label{display:grid;gap:7px;color:var(--prospect-text);font-weight:850;font-size:.84rem}
      .dashboard-editor input,.dashboard-editor textarea{border:1px solid rgba(158,167,184,.34);border-radius:6px;background:rgba(7,8,11,.8);color:var(--prospect-text);padding:10px}
      .dashboard-editor-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
      @media(max-width:1180px){.prospect-widget{grid-column:span min(var(--widget-cols,1),2)}}
      @media(max-width:720px){.dashboard-customizer-bar{grid-template-columns:1fr}.prospect-widget{grid-column:span 1!important;grid-row:span 1!important;min-height:150px}}
    `;
    document.head.appendChild(style);
  };

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
      try { return new URL(href, window.location.origin).searchParams.get("open") || ""; } catch (error) { return ""; }
    };
    const columnStatus = (column) => qs(".kanban-column-header h3", column)?.textContent.trim() || "";
    const updateColumnCount = (column) => {
      const badge = qs(".kanban-column-header span", column);
      if (badge) badge.textContent = String(qsa(".kanban-card", column).length);
    };
    const updateAllCounts = () => qsa(".kanban-column").forEach(updateColumnCount);
    const clearDropTargets = () => qsa(".kanban-column.is-drop-target").forEach((column) => column.classList.remove("is-drop-target"));
    const resetDragState = () => { draggedCard = null; originalColumn = null; originalNextSibling = null; originalStatus = ""; };
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
      const response = await fetch("/prospect-status.php", { method: "POST", body: form, credentials: "same-origin", headers: { "Accept": "application/json" } });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload.ok !== true) throw new Error(payload.message || "Could not update prospect status.");
      return payload;
    };
    qsa(".kanban-column").forEach((column) => {
      column.dataset.kanbanStatus = columnStatus(column);
      column.setAttribute("aria-label", `${column.dataset.kanbanStatus} prospects`);
      column.addEventListener("dragover", (event) => { if (!draggedCard) return; event.preventDefault(); event.dataTransfer.dropEffect = "move"; });
      column.addEventListener("dragenter", () => { if (draggedCard) column.classList.add("is-drop-target"); });
      column.addEventListener("dragleave", (event) => { if (!column.contains(event.relatedTarget)) column.classList.remove("is-drop-target"); });
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

  injectDashboardStyles();
  const customizeButton = qs("[data-customize-dashboard]");
  const dashboardView = qs("[data-prospect-view='dashboard']");
  const widgetGrid = qs(".prospect-widget-grid");
  let isCustomizing = false;
  let draggingWidget = null;
  let dropSide = "after";
  const dashboardState = { order: [], cards: {}, hidden: {}, custom: [] };

  const readSavedState = () => {
    try { Object.assign(dashboardState, JSON.parse(localStorage.getItem(storageKey) || "{}")); } catch (error) {}
    dashboardState.order ||= [];
    dashboardState.cards ||= {};
    dashboardState.hidden ||= {};
    dashboardState.custom ||= [];
  };
  const saveDashboardState = () => {
    if (!widgetGrid) return;
    dashboardState.order = qsa("[data-prospect-widget]", widgetGrid).map((widget) => widget.dataset.widgetId);
    dashboardState.cards = {};
    dashboardState.hidden = {};
    qsa("[data-prospect-widget]", widgetGrid).forEach((widget) => {
      const id = widget.dataset.widgetId;
      dashboardState.cards[id] = {
        cols: Number(widget.dataset.cols || 1),
        rows: Number(widget.dataset.rows || 1),
        title: qs("[data-widget-title]", widget)?.textContent || "",
        value: qs("[data-widget-value]", widget)?.textContent || "",
        note: qs("[data-widget-note]", widget)?.textContent || "",
      };
      dashboardState.hidden[id] = widget.classList.contains("is-muted");
    });
    dashboardState.custom = qsa("[data-prospect-widget][data-custom-widget='true']", widgetGrid).map((widget) => ({
      id: widget.dataset.widgetId,
      type: widget.dataset.widgetType,
      title: qs("[data-widget-title]", widget)?.textContent || "",
      value: qs("[data-widget-value]", widget)?.textContent || "",
      note: qs("[data-widget-note]", widget)?.textContent || "",
      cols: Number(widget.dataset.cols || 1),
      rows: Number(widget.dataset.rows || 1),
    }));
    localStorage.setItem(storageKey, JSON.stringify(dashboardState));
  };
  const slug = (value) => String(value || "widget").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") || "widget";
  const metricValue = (label) => {
    const card = qsa("[data-prospect-widget]", widgetGrid).find((widget) => (qs("[data-widget-title]", widget)?.textContent || "").trim().toLowerCase() === label.toLowerCase());
    const raw = qs("[data-widget-value]", card)?.textContent || "0";
    const number = Number(String(raw).replace(/[^0-9.-]/g, ""));
    return Number.isFinite(number) ? number : 0;
  };
  const statusData = () => [
    ["New", metricValue("New leads")],
    ["In progress", metricValue("In progress")],
    ["Warm", metricValue("Warm")],
    ["Converted", metricValue("Converted")],
    ["Clients", metricValue("Clients")],
    ["Closed", metricValue("Closed / Lost")],
  ];
  const chartMarkup = (data) => {
    const max = Math.max(1, ...data.map((item) => item[1]));
    return `<div class="dashboard-chart">${data.map(([label, value]) => `<div class="dashboard-bar"><span>${label}</span><div class="dashboard-bar-track"><span class="dashboard-bar-fill" style="width:${Math.max(4, Math.round((value / max) * 100))}%"></span></div><strong>${value}</strong></div>`).join("")}</div>`;
  };
  const reportMarkup = () => {
    const total = metricValue("Total prospects");
    const active = metricValue("In progress") + metricValue("Warm");
    const won = metricValue("Converted") + metricValue("Clients");
    const avg = metricValue("Average percentage");
    return `<div class="dashboard-report-list"><span><strong>${active}</strong> active opportunities need follow-up attention.</span><span><strong>${won}</strong> records are converted or active clients.</span><span><strong>${avg}%</strong> average conversion score across ${total} prospects.</span></div>`;
  };
  const normalizeWidget = (widget, index) => {
    if (!widget.dataset.widgetId) widget.dataset.widgetId = `core-${slug(qs("span", widget)?.textContent || "card")}-${index}`;
    const oldTitle = qs("span", widget);
    const oldValue = qs("strong", widget);
    const oldNote = qs("p", widget);
    oldTitle?.setAttribute("data-widget-title", "");
    oldValue?.setAttribute("data-widget-value", "");
    oldNote?.setAttribute("data-widget-note", "");
    widget.dataset.cols ||= "1";
    widget.dataset.rows ||= "1";
    widget.style.setProperty("--widget-cols", widget.dataset.cols);
    widget.style.setProperty("--widget-rows", widget.dataset.rows);
    widget.setAttribute("draggable", "false");
    if (!qs(".dashboard-resize-handle", widget)) {
      const handle = document.createElement("button");
      handle.type = "button";
      handle.className = "dashboard-resize-handle";
      handle.setAttribute("aria-label", "Resize dashboard card");
      widget.appendChild(handle);
    }
    const actions = qs(".widget-actions", widget);
    if (actions && !qs("[data-dashboard-size]", actions)) {
      actions.insertAdjacentHTML("beforeend", '<button type="button" data-dashboard-size="1x1">S</button><button type="button" data-dashboard-size="2x1">W</button><button type="button" data-dashboard-size="2x2">L</button>');
    }
  };
  const createCustomWidget = ({ id, type, title, value, note, cols = 2, rows = 1 }) => {
    const widget = document.createElement("article");
    widget.className = "admin-panel prospect-widget";
    widget.dataset.prospectWidget = "";
    widget.dataset.customWidget = "true";
    widget.dataset.widgetId = id || `custom-${Date.now()}`;
    widget.dataset.widgetType = type || "custom-kpi";
    widget.dataset.cols = String(cols);
    widget.dataset.rows = String(rows);
    const body = widget.dataset.widgetType === "status-chart" ? chartMarkup(statusData()) : widget.dataset.widgetType === "pipeline-report" ? reportMarkup() : "";
    widget.innerHTML = `<div class="widget-actions"><button type="button" title="Move widget">Move</button><button type="button" title="Edit widget">Edit</button><button type="button" title="Remove widget">Remove</button><button type="button" data-dashboard-size="1x1">S</button><button type="button" data-dashboard-size="2x1">W</button><button type="button" data-dashboard-size="2x2">L</button></div><span data-widget-title>${title || "Custom card"}</span><strong data-widget-value>${value || ""}</strong><p data-widget-note>${note || ""}</p>${body}<button type="button" class="dashboard-resize-handle" aria-label="Resize dashboard card"></button>`;
    normalizeWidget(widget, 0);
    return widget;
  };
  const refreshGeneratedCards = () => {
    qsa("[data-custom-widget='true']", widgetGrid).forEach((widget) => {
      const type = widget.dataset.widgetType;
      const oldChart = qs(".dashboard-chart", widget);
      const oldReport = qs(".dashboard-report-list", widget);
      if (type === "status-chart") {
        oldChart?.remove();
        widget.insertAdjacentHTML("beforeend", chartMarkup(statusData()));
      }
      if (type === "pipeline-report") {
        oldReport?.remove();
        widget.insertAdjacentHTML("beforeend", reportMarkup());
      }
    });
  };
  const applyWidgetSize = (widget, cols, rows) => {
    widget.dataset.cols = String(Math.min(3, Math.max(1, cols)));
    widget.dataset.rows = String(Math.min(3, Math.max(1, rows)));
    widget.style.setProperty("--widget-cols", widget.dataset.cols);
    widget.style.setProperty("--widget-rows", widget.dataset.rows);
  };
  const buildEditor = () => {
    if (qs("[data-dashboard-editor]")) return qs("[data-dashboard-editor]");
    const editor = document.createElement("div");
    editor.className = "dashboard-editor";
    editor.dataset.dashboardEditor = "";
    editor.hidden = true;
    editor.innerHTML = '<form><h3>Edit dashboard card</h3><label>Title<input name="title" maxlength="80"></label><label>Value<input name="value" maxlength="80"></label><label>Description<textarea name="note" rows="4" maxlength="260"></textarea></label><div class="dashboard-editor-actions"><button type="button" data-editor-cancel>Cancel</button><button type="submit">Save card</button></div></form>';
    document.body.appendChild(editor);
    return editor;
  };
  const editWidget = (widget) => {
    const editor = buildEditor();
    const form = qs("form", editor);
    form.elements.title.value = qs("[data-widget-title]", widget)?.textContent || "";
    form.elements.value.value = qs("[data-widget-value]", widget)?.textContent || "";
    form.elements.note.value = qs("[data-widget-note]", widget)?.textContent || "";
    editor.hidden = false;
    form.elements.title.focus();
    qs("[data-editor-cancel]", editor).onclick = () => { editor.hidden = true; };
    form.onsubmit = (event) => {
      event.preventDefault();
      qs("[data-widget-title]", widget).textContent = form.elements.title.value.trim() || "Untitled card";
      qs("[data-widget-value]", widget).textContent = form.elements.value.value.trim();
      qs("[data-widget-note]", widget).textContent = form.elements.note.value.trim();
      editor.hidden = true;
      saveDashboardState();
    };
  };
  const addCustomizerBar = () => {
    if (!dashboardView || qs(".dashboard-customizer-bar", dashboardView)) return;
    const bar = document.createElement("div");
    bar.className = "dashboard-customizer-bar";
    bar.innerHTML = '<select data-dashboard-add-type><option value="custom-kpi">Custom KPI card</option><option value="status-chart">Status chart</option><option value="pipeline-report">Pipeline report</option></select><button type="button" data-dashboard-add-card>Add card</button><button type="button" data-dashboard-reset>Reset layout</button>';
    qs(".section-heading-row", dashboardView)?.insertAdjacentElement("afterend", bar);
    qs("[data-dashboard-add-card]", bar).addEventListener("click", () => {
      const type = qs("[data-dashboard-add-type]", bar).value;
      const labels = { "custom-kpi": ["Custom KPI", "0", "Track a custom prospecting metric."], "status-chart": ["Status chart", "", "Pipeline distribution from current database records."], "pipeline-report": ["Pipeline report", "", "Auto-generated summary from current dashboard metrics."] };
      const [title, value, note] = labels[type];
      const widget = createCustomWidget({ type, title, value, note, cols: type === "custom-kpi" ? 1 : 2, rows: type === "pipeline-report" ? 2 : 1 });
      widgetGrid.appendChild(widget);
      bindWidget(widget);
      saveDashboardState();
    });
    qs("[data-dashboard-reset]", bar).addEventListener("click", () => {
      localStorage.removeItem(storageKey);
      window.location.reload();
    });
  };
  const bindWidget = (widget) => {
    normalizeWidget(widget, qsa("[data-prospect-widget]", widgetGrid).indexOf(widget));
    widget.addEventListener("dragstart", (event) => {
      if (!isCustomizing || event.target.closest("button")) { event.preventDefault(); return; }
      draggingWidget = widget;
      widget.classList.add("is-dashboard-dragging");
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", widget.dataset.widgetId);
    });
    widget.addEventListener("dragend", () => {
      widget.classList.remove("is-dashboard-dragging");
      qsa(".is-drop-before,.is-drop-after", widgetGrid).forEach((item) => item.classList.remove("is-drop-before", "is-drop-after"));
      draggingWidget = null;
      saveDashboardState();
    });
    widget.addEventListener("dragover", (event) => {
      if (!isCustomizing || !draggingWidget || draggingWidget === widget) return;
      event.preventDefault();
      const rect = widget.getBoundingClientRect();
      dropSide = event.clientX < rect.left + rect.width / 2 ? "before" : "after";
      widget.classList.toggle("is-drop-before", dropSide === "before");
      widget.classList.toggle("is-drop-after", dropSide === "after");
    });
    widget.addEventListener("dragleave", () => widget.classList.remove("is-drop-before", "is-drop-after"));
    widget.addEventListener("drop", (event) => {
      if (!isCustomizing || !draggingWidget || draggingWidget === widget) return;
      event.preventDefault();
      widget.classList.remove("is-drop-before", "is-drop-after");
      if (dropSide === "before") widgetGrid.insertBefore(draggingWidget, widget);
      else widgetGrid.insertBefore(draggingWidget, widget.nextElementSibling);
      saveDashboardState();
    });
  };
  const setCustomizing = (enabled) => {
    isCustomizing = enabled;
    dashboardView?.classList.toggle("is-customizing", enabled);
    customizeButton?.classList.toggle("is-active", enabled);
    if (customizeButton) {
      customizeButton.textContent = enabled ? "Done customizing" : "Customize dashboard";
      customizeButton.setAttribute("aria-pressed", String(enabled));
    }
    qsa("[data-prospect-widget]", widgetGrid).forEach((widget) => widget.setAttribute("draggable", enabled ? "true" : "false"));
    if (!enabled) saveDashboardState();
  };
  const startResize = (event, widget) => {
    if (!isCustomizing) return;
    event.preventDefault();
    const startX = event.clientX;
    const startY = event.clientY;
    const startCols = Number(widget.dataset.cols || 1);
    const startRows = Number(widget.dataset.rows || 1);
    const onMove = (moveEvent) => {
      applyWidgetSize(widget, startCols + Math.round((moveEvent.clientX - startX) / 220), startRows + Math.round((moveEvent.clientY - startY) / 150));
    };
    const onEnd = () => {
      document.removeEventListener("pointermove", onMove);
      document.removeEventListener("pointerup", onEnd);
      saveDashboardState();
    };
    document.addEventListener("pointermove", onMove);
    document.addEventListener("pointerup", onEnd);
  };

  if (customizeButton && dashboardView && widgetGrid) {
    readSavedState();
    addCustomizerBar();
    qsa("[data-prospect-widget]", widgetGrid).forEach(normalizeWidget);
    dashboardState.custom.forEach((customCard) => {
      if (!qs(`[data-widget-id='${customCard.id}']`, widgetGrid)) widgetGrid.appendChild(createCustomWidget(customCard));
    });
    qsa("[data-prospect-widget]", widgetGrid).forEach((widget) => {
      const saved = dashboardState.cards[widget.dataset.widgetId];
      if (saved) {
        if (saved.title && qs("[data-widget-title]", widget)) qs("[data-widget-title]", widget).textContent = saved.title;
        if (saved.value !== undefined && qs("[data-widget-value]", widget)) qs("[data-widget-value]", widget).textContent = saved.value;
        if (saved.note !== undefined && qs("[data-widget-note]", widget)) qs("[data-widget-note]", widget).textContent = saved.note;
        applyWidgetSize(widget, saved.cols || 1, saved.rows || 1);
      }
      if (dashboardState.hidden[widget.dataset.widgetId]) {
        widget.classList.add("is-muted");
        const removeButton = qsa("button", qs(".widget-actions", widget)).find((button) => button.textContent.trim().toLowerCase() === "remove");
        if (removeButton) removeButton.textContent = "Restore";
      }
    });
    dashboardState.order.forEach((id) => {
      const widget = qs(`[data-widget-id='${id}']`, widgetGrid);
      if (widget) widgetGrid.appendChild(widget);
    });
    refreshGeneratedCards();
    qsa("[data-prospect-widget]", widgetGrid).forEach(bindWidget);
    customizeButton.setAttribute("aria-pressed", "false");
    customizeButton.addEventListener("click", () => setCustomizing(!isCustomizing));
    widgetGrid.addEventListener("click", (event) => {
      const button = event.target.closest("button");
      const widget = event.target.closest("[data-prospect-widget]");
      if (!button || !widget || !isCustomizing) return;
      const action = button.textContent.trim().toLowerCase();
      if (button.dataset.dashboardSize) {
        const [cols, rows] = button.dataset.dashboardSize.split("x").map(Number);
        applyWidgetSize(widget, cols, rows);
        saveDashboardState();
        return;
      }
      if (action === "remove" || action === "restore") {
        widget.classList.toggle("is-muted");
        button.textContent = widget.classList.contains("is-muted") ? "Restore" : "Remove";
        saveDashboardState();
        return;
      }
      if (action === "move") {
        widgetGrid.insertBefore(widget, widgetGrid.firstElementChild);
        widget.classList.add("is-configuring");
        window.setTimeout(() => widget.classList.remove("is-configuring"), 700);
        saveDashboardState();
        return;
      }
      if (action === "edit") editWidget(widget);
    });
    widgetGrid.addEventListener("pointerdown", (event) => {
      const handle = event.target.closest(".dashboard-resize-handle");
      const widget = event.target.closest("[data-prospect-widget]");
      if (handle && widget) startResize(event, widget);
    });
  }
})();