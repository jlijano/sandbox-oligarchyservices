(function () {
  const desiredOrder = [
    ["AI & Automation", "/ai-automation.html"],
    ["Help Desk", "/help-desk.html"],
    ["Business Systems", "/business-systems.html"],
    ["ITAD", "/itad.html"],
    ["ITAM", "/itam.html"]
  ];

  const aboutStyles = `
    .about-preview {
      max-width: none;
      margin: 0;
      padding: clamp(70px, 8vw, 112px) clamp(18px, 4vw, 44px);
      background: #18181a;
    }

    .about-preview__inner {
      max-width: 1240px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(260px, 0.82fr) minmax(0, 1.18fr);
      gap: clamp(34px, 6vw, 78px);
      align-items: center;
    }

    .about-preview__mark {
      width: min(520px, 100%);
      aspect-ratio: 1;
      justify-self: center;
      position: relative;
      border-radius: 50%;
      background: #a90d02;
      box-shadow: 0 34px 90px rgba(0, 0, 0, 0.28);
    }

    .about-preview__mark::after {
      content: "";
      position: absolute;
      top: 16%;
      bottom: 16%;
      left: 50%;
      width: 13%;
      min-width: 46px;
      border-radius: 999px;
      background: #18181a;
      transform: translateX(-50%);
    }

    .about-preview__copy {
      min-width: 0;
    }

    .about-preview__copy h2 {
      margin: 0 0 clamp(28px, 4vw, 46px);
      color: #ffffff;
      font-size: clamp(2.7rem, 5.4vw, 4.85rem);
      line-height: 0.98;
    }

    .about-preview__copy p {
      max-width: 760px;
      margin: 0;
      color: #eeeeef;
      font-size: clamp(1rem, 1.45vw, 1.35rem);
      font-weight: 400;
      line-height: 1.48;
    }

    .about-preview__copy p + p {
      margin-top: 24px;
    }

    .about-preview__actions {
      margin-top: clamp(34px, 5vw, 64px);
      display: flex;
      justify-content: center;
    }

    .about-preview__actions .button {
      min-width: 230px;
      border-radius: 999px;
      background: #a90707;
    }

    .about-preview__actions .button:hover {
      background: #c10d0d;
    }

    @media (max-width: 940px) {
      .about-preview__inner {
        grid-template-columns: 1fr;
      }

      .about-preview__mark {
        width: min(390px, 82vw);
      }

      .about-preview__copy {
        text-align: left;
      }

      .about-preview__actions {
        justify-content: flex-start;
      }
    }

    @media (max-width: 620px) {
      .about-preview {
        padding-inline: 16px;
      }

      .about-preview__copy h2 {
        font-size: clamp(2.4rem, 14vw, 3.4rem);
      }

      .about-preview__copy p {
        font-size: 1rem;
        font-weight: 400;
      }

      .about-preview__actions .button {
        width: 100%;
      }
    }
  `;

  const servicesCarouselStyles = `
    .services-carousel {
      max-width: none;
      margin: 0;
      padding: clamp(66px, 8vw, 106px) 0;
      overflow: hidden;
      background: radial-gradient(circle at 16% 0%, rgba(176, 7, 20, 0.26), transparent 28rem), linear-gradient(180deg, #09090a 0%, #171719 100%);
    }

    .services-carousel__inner {
      max-width: 1240px;
      margin: 0 auto;
      padding: 0 clamp(18px, 4vw, 44px);
    }

    .services-carousel__header {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 24px;
      margin-bottom: 28px;
    }

    .services-carousel__header h2 {
      max-width: 780px;
      margin: 0;
      color: #ffffff;
      font-size: clamp(2rem, 4.2vw, 3.55rem);
      line-height: 1.04;
    }

    .services-carousel__controls {
      display: flex;
      gap: 10px;
      flex: 0 0 auto;
    }

    .services-carousel__control {
      width: 46px;
      height: 46px;
      min-height: 46px;
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.08);
      color: #ffffff;
      font-size: 1.5rem;
      line-height: 1;
      cursor: pointer;
      transition: background 160ms ease, border-color 160ms ease, transform 160ms ease;
    }

    .services-carousel__control:hover,
    .services-carousel__control:focus-visible {
      border-color: rgba(176, 7, 20, 0.78);
      background: rgba(176, 7, 20, 0.5);
    }

    .services-carousel__viewport {
      overflow: hidden;
      border-radius: 8px;
    }

    .services-carousel__track {
      display: grid;
      grid-auto-flow: column;
      grid-auto-columns: minmax(300px, 38%);
      gap: 18px;
      overflow-x: auto;
      overscroll-behavior-x: contain;
      scroll-snap-type: x mandatory;
      scroll-padding-inline: 2px;
      padding: 2px 2px 16px;
      scrollbar-width: none;
    }

    .services-carousel__track::-webkit-scrollbar {
      display: none;
    }

    .services-carousel__card {
      position: relative;
      min-height: 430px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      overflow: hidden;
      scroll-snap-align: start;
      border: 1px solid rgba(255, 255, 255, 0.13);
      border-radius: 8px;
      padding: 24px;
      isolation: isolate;
      text-decoration: none;
      background: #121214;
      box-shadow: 0 24px 58px rgba(0, 0, 0, 0.34);
      transform: translateZ(0);
      transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
    }

    .services-carousel__card::before {
      content: "";
      position: absolute;
      inset: -4%;
      z-index: -2;
      background-image: var(--service-card-bg);
      background-size: cover;
      background-position: center;
      filter: saturate(0.82) contrast(1.04) brightness(0.7);
      transform: scale(1.06);
      transition: transform 500ms ease, filter 500ms ease;
    }

    .services-carousel__card::after {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      background: linear-gradient(180deg, rgba(0, 0, 0, 0.16), rgba(0, 0, 0, 0.56) 44%, rgba(7, 7, 8, 0.9));
    }

    .services-carousel__card:hover,
    .services-carousel__card:focus-visible {
      border-color: rgba(176, 7, 20, 0.9);
      box-shadow: 0 26px 68px rgba(0, 0, 0, 0.46), 0 0 0 1px rgba(176, 7, 20, 0.18) inset;
      transform: translateY(-4px);
      outline: none;
    }

    .services-carousel__card:hover::before,
    .services-carousel__card:focus-visible::before {
      filter: saturate(0.95) contrast(1.08) brightness(0.82);
      transform: scale(1.1);
    }

    .services-carousel__number {
      position: absolute;
      top: 22px;
      left: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 34px;
      border-radius: 999px;
      background: #a90707;
      color: #ffffff;
      font-size: 0.84rem;
      font-weight: 850;
    }

    .services-carousel__card h3 {
      max-width: 12ch;
      margin: 0 0 12px;
      color: #ffffff;
      font-size: clamp(1.55rem, 2.5vw, 2.35rem);
      line-height: 1.02;
    }

    .services-carousel__card p {
      max-width: 46ch;
      margin: 0;
      color: #e4e4e7;
      font-size: 1rem;
      line-height: 1.5;
    }

    .services-carousel__cta {
      width: max-content;
      margin-top: 22px;
      border-bottom: 2px solid #b00714;
      color: #ffffff;
      font-size: 0.92rem;
      font-weight: 850;
    }

    .services-carousel__pagination {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 18px;
    }

    .services-carousel__dot {
      width: 10px;
      height: 10px;
      border: 0;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.28);
      cursor: pointer;
      transition: width 160ms ease, background 160ms ease;
    }

    .services-carousel__dot[aria-selected="true"] {
      width: 28px;
      background: #b00714;
    }

    @media (max-width: 940px) {
      .services-carousel__header {
        align-items: start;
        flex-direction: column;
      }

      .services-carousel__track {
        grid-auto-columns: minmax(280px, 72%);
      }
    }

    @media (max-width: 620px) {
      .services-carousel__inner {
        padding-inline: 16px;
      }

      .services-carousel__track {
        grid-auto-columns: minmax(260px, 88%);
        gap: 14px;
      }

      .services-carousel__card {
        min-height: 390px;
        padding: 20px;
      }

      .services-carousel__card h3 {
        max-width: 14ch;
      }
    }
  `;

  const serviceCards = [
    {
      number: "01",
      title: "AI & Automation",
      body: "Agents, workflows, reporting, and process automation that remove repetitive work while keeping accountability visible.",
      href: "/ai-automation.html",
      cta: "Explore automation",
      image: "https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&fm=jpg&q=70&w=1600"
    },
    {
      number: "02",
      title: "Help Desk",
      body: "User support, onboarding, offboarding, escalation paths, and practical service routines for teams that need dependable follow-through.",
      href: "/help-desk.html",
      cta: "Build support coverage",
      image: "https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&fm=jpg&q=70&w=1600"
    },
    {
      number: "03",
      title: "Business Systems",
      body: "ERP, CRM, Odoo, workflow tools, migration planning, documentation, and adoption support for cleaner operations.",
      href: "/business-systems.html",
      cta: "Improve systems",
      image: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&fm=jpg&q=70&w=1600"
    },
    {
      number: "04",
      title: "ITAD",
      body: "Secure retirement, data destruction support, chain-of-custody workflows, disposition planning, and audit-ready asset records.",
      href: "/itad.html",
      cta: "Control disposition",
      image: "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&fm=jpg&q=70&w=1600"
    },
    {
      number: "05",
      title: "ITAM",
      body: "Asset visibility, lifecycle planning, inventory hygiene, ownership tracking, and reporting that keeps technology estates understandable.",
      href: "/itam.html",
      cta: "Track assets",
      image: "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&fm=jpg&q=70&w=1600"
    }
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

  const installAboutStyles = () => {
    if (document.getElementById("about-preview-styles")) return;
    const style = document.createElement("style");
    style.id = "about-preview-styles";
    style.textContent = aboutStyles;
    document.head.appendChild(style);
  };

  const installServicesCarouselStyles = () => {
    if (document.getElementById("services-carousel-styles")) return;
    const style = document.createElement("style");
    style.id = "services-carousel-styles";
    style.textContent = servicesCarouselStyles;
    document.head.appendChild(style);
  };

  const installAboutSection = () => {
    if (document.querySelector(".about-preview")) return true;
    const positioningSection = document.querySelector("main .section.intro");
    if (!positioningSection) return false;

    installAboutStyles();

    const section = document.createElement("section");
    section.className = "about-preview";
    section.id = "about";
    section.setAttribute("aria-labelledby", "about-preview-title");
    section.innerHTML = `
      <div class="about-preview__inner">
        <div class="about-preview__mark" aria-hidden="true"></div>
        <div class="about-preview__copy">
          <h2 id="about-preview-title">About Us</h2>
          <p>Oligarchy Services helps organizations bring structure to technology operations, from asset lifecycle management and secure handling to user support, business systems, automation, and reporting.</p>
          <p>We work with teams that need practical execution, clearer visibility, and reliable follow-through across the tools and processes that keep the business moving.</p>
          <div class="about-preview__actions">
            <a class="button primary" href="/about.html" data-track="about_preview_learn_more">Learn More</a>
          </div>
        </div>
      </div>
    `;

    positioningSection.insertAdjacentElement("afterend", section);
    return true;
  };

  const updateCarouselDots = (section) => {
    const track = section.querySelector(".services-carousel__track");
    const cards = Array.from(section.querySelectorAll(".services-carousel__card"));
    const dots = Array.from(section.querySelectorAll(".services-carousel__dot"));
    if (!track || !cards.length || !dots.length) return;

    const activeIndex = cards.reduce((closestIndex, card, index) => {
      const currentDistance = Math.abs(card.offsetLeft - track.scrollLeft);
      const closestDistance = Math.abs(cards[closestIndex].offsetLeft - track.scrollLeft);
      return currentDistance < closestDistance ? index : closestIndex;
    }, 0);

    dots.forEach((dot, index) => {
      dot.setAttribute("aria-selected", index === activeIndex ? "true" : "false");
    });
  };

  const initializeServicesCarousel = (section) => {
    if (section.dataset.carouselReady === "true") return;
    section.dataset.carouselReady = "true";

    const track = section.querySelector(".services-carousel__track");
    const cards = Array.from(section.querySelectorAll(".services-carousel__card"));
    const prev = section.querySelector("[data-services-prev]");
    const next = section.querySelector("[data-services-next]");
    const dots = Array.from(section.querySelectorAll(".services-carousel__dot"));
    if (!track || !cards.length) return;

    const scrollToCard = (index) => {
      const target = cards[((index % cards.length) + cards.length) % cards.length];
      track.scrollTo({ left: target.offsetLeft, behavior: "smooth" });
    };

    const activeIndex = () => cards.reduce((closestIndex, card, index) => {
      const currentDistance = Math.abs(card.offsetLeft - track.scrollLeft);
      const closestDistance = Math.abs(cards[closestIndex].offsetLeft - track.scrollLeft);
      return currentDistance < closestDistance ? index : closestIndex;
    }, 0);

    prev?.addEventListener("click", () => scrollToCard(activeIndex() - 1));
    next?.addEventListener("click", () => scrollToCard(activeIndex() + 1));
    dots.forEach((dot, index) => dot.addEventListener("click", () => scrollToCard(index)));
    track.addEventListener("scroll", () => window.requestAnimationFrame(() => updateCarouselDots(section)), { passive: true });
    updateCarouselDots(section);
  };

  const installServicesCarousel = () => {
    const existing = document.querySelector(".services-carousel");
    if (existing) {
      initializeServicesCarousel(existing);
      return true;
    }

    const solutionsSection = document.querySelector("main #solutions");
    if (!solutionsSection) return false;

    installServicesCarouselStyles();

    const section = document.createElement("section");
    section.className = "services-carousel";
    section.setAttribute("aria-labelledby", "services-carousel-title");
    section.innerHTML = `
      <div class="services-carousel__inner">
        <div class="services-carousel__header">
          <div>
            <p class="eyebrow">Our services</p>
            <h2 id="services-carousel-title">Five ways we bring order to technology operations.</h2>
          </div>
          <div class="services-carousel__controls" aria-label="Service carousel controls">
            <button class="services-carousel__control" type="button" aria-label="Previous service" data-services-prev>&lsaquo;</button>
            <button class="services-carousel__control" type="button" aria-label="Next service" data-services-next>&rsaquo;</button>
          </div>
        </div>
        <div class="services-carousel__viewport">
          <div class="services-carousel__track" tabindex="0" aria-roledescription="carousel" aria-label="Oligarchy Services service cards">
            ${serviceCards.map((card, index) => `
              <a class="services-carousel__card" href="${card.href}" role="group" aria-roledescription="slide" aria-label="${index + 1} of ${serviceCards.length}: ${card.title}" style="--service-card-bg: url('${card.image}')">
                <span class="services-carousel__number">${card.number}</span>
                <h3>${card.title}</h3>
                <p>${card.body}</p>
                <span class="services-carousel__cta">${card.cta}</span>
              </a>
            `).join("")}
          </div>
        </div>
        <div class="services-carousel__pagination" role="tablist" aria-label="Choose a service card">
          ${serviceCards.map((card, index) => `<button class="services-carousel__dot" type="button" role="tab" aria-label="Go to ${card.title}" aria-selected="${index === 0 ? "true" : "false"}"></button>`).join("")}
        </div>
      </div>
    `;

    solutionsSection.insertAdjacentElement("beforebegin", section);
    initializeServicesCarousel(section);
    return true;
  };

  const syncPage = () => {
    const menuReady = syncServiceMenu();
    const aboutReady = installAboutSection();
    const carouselReady = installServicesCarousel();
    return menuReady && aboutReady && carouselReady;
  };

  if (syncPage()) return;

  const observer = new MutationObserver(() => {
    if (syncPage()) observer.disconnect();
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
