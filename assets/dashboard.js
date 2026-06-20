(function () {
  const shell = document.querySelector("[data-dashboard-shell]");
  if (!shell) return;

  const collapseButton = document.querySelector("[data-sidebar-collapse]");
  const mobileButton = document.querySelector("[data-mobile-menu]");
  const backdrop = document.querySelector("[data-sidebar-backdrop]");
  const storageKey = "oligarchy_sidebar_collapsed";

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

  if (mobileButton) {
    mobileButton.addEventListener("click", () => {
      setMobileOpen(!shell.classList.contains("is-sidebar-open"));
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", () => setMobileOpen(false));
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setMobileOpen(false);
  });
})();
