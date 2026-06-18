(function () {
  const desiredOrder = [
    ["AI & Automation", "/ai-automation.html"],
    ["Help Desk", "/help-desk.html"],
    ["Business Systems", "/business-systems.html"],
    ["ITAD", "/itad.html"],
    ["ITAM", "/itam.html"],
    ["Projects", "/projects.html"]
  ];

  const syncServiceMenu = () => {
    const menu = document.querySelector("#primary-navigation .nav-dropdown-menu");
    if (!menu) return false;

    menu.textContent = "";
    desiredOrder.forEach(([label, href]) => {
      const item = document.createElement("a");
      item.href = href;
      item.textContent = label;
      item.setAttribute("role", "menuitem");
      menu.appendChild(item);
    });

    return true;
  };

  if (syncServiceMenu()) return;

  const observer = new MutationObserver(() => {
    if (syncServiceMenu()) observer.disconnect();
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
