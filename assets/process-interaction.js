(function () {
  const process = document.querySelector(".process-workflow");
  if (!process) return;

  const steps = Array.from(process.querySelectorAll(".process-step"));
  const number = process.querySelector("[data-process-number]");
  const title = process.querySelector("[data-process-title]");
  const body = process.querySelector("[data-process-body]");
  const tags = process.querySelector("[data-process-tags]");

  const activate = (button) => {
    steps.forEach((step) => {
      const isActive = step === button;
      step.classList.toggle("is-active", isActive);
      step.setAttribute("aria-selected", String(isActive));
      step.tabIndex = isActive ? 0 : -1;
    });

    if (number) number.textContent = button.dataset.number || "";
    if (title) title.textContent = button.dataset.title || "";
    if (body) body.textContent = button.dataset.body || "";
    if (tags) {
      tags.replaceChildren(...(button.dataset.tags || "").split("|").filter(Boolean).map((tag) => {
        const item = document.createElement("li");
        item.textContent = tag;
        return item;
      }));
    }
  };

  steps.forEach((button, index) => {
    button.addEventListener("click", () => activate(button));
    button.addEventListener("keydown", (event) => {
      if (!["ArrowRight", "ArrowDown", "ArrowLeft", "ArrowUp", "Home", "End"].includes(event.key)) return;
      event.preventDefault();
      let nextIndex = index;
      if (event.key === "ArrowRight" || event.key === "ArrowDown") nextIndex = (index + 1) % steps.length;
      if (event.key === "ArrowLeft" || event.key === "ArrowUp") nextIndex = (index - 1 + steps.length) % steps.length;
      if (event.key === "Home") nextIndex = 0;
      if (event.key === "End") nextIndex = steps.length - 1;
      steps[nextIndex].focus();
      activate(steps[nextIndex]);
    });
  });
})();
