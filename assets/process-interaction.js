(function () {
  const process = document.querySelector(".process-workflow");

  if (process) {
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
  }

  const initializeServicesAutoplay = () => {
    const carousel = document.querySelector(".services-carousel");
    if (!carousel || carousel.dataset.autoplayReady === "true") return false;

    const track = carousel.querySelector(".services-carousel__track");
    const cards = Array.from(carousel.querySelectorAll(".services-carousel__card"));
    if (!track || cards.length < 2) return false;

    carousel.dataset.autoplayReady = "true";

    let autoplayTimer = null;
    let restartTimer = null;

    const activeIndex = () => cards.reduce((closestIndex, card, index) => {
      const currentDistance = Math.abs(card.offsetLeft - track.scrollLeft);
      const closestDistance = Math.abs(cards[closestIndex].offsetLeft - track.scrollLeft);
      return currentDistance < closestDistance ? index : closestIndex;
    }, 0);

    const scrollToCard = (index, behavior = "smooth") => {
      const target = cards[((index % cards.length) + cards.length) % cards.length];
      track.scrollTo({ left: target.offsetLeft, behavior });
    };

    const stopAutoplay = () => {
      if (autoplayTimer) window.clearInterval(autoplayTimer);
      if (restartTimer) window.clearTimeout(restartTimer);
      autoplayTimer = null;
      restartTimer = null;
    };

    const startAutoplay = () => {
      if (autoplayTimer || document.hidden) return;
      autoplayTimer = window.setInterval(() => scrollToCard(activeIndex() + 1), 3600);
    };

    const restartAutoplay = () => {
      stopAutoplay();
      restartTimer = window.setTimeout(startAutoplay, 3600);
    };

    carousel.addEventListener("click", (event) => {
      if (!event.target.closest(".services-carousel__control, .services-carousel__dot")) return;
      restartAutoplay();
    });

    carousel.addEventListener("focusin", stopAutoplay);
    carousel.addEventListener("focusout", restartAutoplay);

    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        stopAutoplay();
        return;
      }
      startAutoplay();
    });

    window.setTimeout(() => {
      scrollToCard(activeIndex() + 1);
      startAutoplay();
    }, 700);

    return true;
  };

  if (initializeServicesAutoplay()) return;

  const carouselObserver = new MutationObserver(() => {
    if (initializeServicesAutoplay()) carouselObserver.disconnect();
  });

  carouselObserver.observe(document.documentElement, { childList: true, subtree: true });
})();
