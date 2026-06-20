(function () {
  const desiredOrder = [
    ["AI & Automation", "/ai-automation.html"],
    ["Help Desk", "/help-desk.html"],
    ["Business Systems", "/business-systems.html"],
    ["ITAD", "/itad.html"],
    ["ITAM", "/itam.html"]
  ];

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

  const aboutStyles = `
    .about-preview{max-width:none;margin:0;padding:clamp(70px,8vw,112px) clamp(18px,4vw,44px);background:#18181a}.about-preview__inner{max-width:1240px;margin:0 auto;display:grid;grid-template-columns:minmax(260px,.82fr) minmax(0,1.18fr);gap:clamp(34px,6vw,78px);align-items:center}.about-preview__mark{width:min(520px,100%);aspect-ratio:1;justify-self:center;position:relative;border-radius:50%;background:#a90d02;box-shadow:0 34px 90px rgba(0,0,0,.28)}.about-preview__mark::after{content:"";position:absolute;top:16%;bottom:16%;left:50%;width:13%;min-width:46px;border-radius:999px;background:#18181a;transform:translateX(-50%)}.about-preview__copy{min-width:0}.about-preview__copy h2{margin:0 0 clamp(28px,4vw,46px);color:#fff;font-size:clamp(2.7rem,5.4vw,4.85rem);line-height:.98}.about-preview__copy p{max-width:760px;margin:0;color:#eeeeef;font-size:clamp(1rem,1.45vw,1.35rem);font-weight:400;line-height:1.48}.about-preview__copy p+p{margin-top:24px}.about-preview__actions{margin-top:clamp(34px,5vw,64px);display:flex;justify-content:center}.about-preview__actions .button{min-width:230px;border-radius:999px;background:#a90707}.about-preview__actions .button:hover{background:#c10d0d}@media(max-width:940px){.about-preview__inner{grid-template-columns:1fr}.about-preview__mark{width:min(390px,82vw)}.about-preview__actions{justify-content:flex-start}}@media(max-width:620px){.about-preview{padding-inline:16px}.about-preview__copy h2{font-size:clamp(2.4rem,14vw,3.4rem)}.about-preview__copy p{font-size:1rem}.about-preview__actions .button{width:100%}}
  `;

  const carouselStyles = `
    .services-carousel{max-width:none;margin:0;padding:clamp(66px,8vw,106px) 0;overflow:hidden;background:radial-gradient(circle at 16% 0%,rgba(176,7,20,.26),transparent 28rem),linear-gradient(180deg,#09090a 0%,#171719 100%)}.services-carousel__inner{max-width:1240px;margin:0 auto;padding:0 clamp(18px,4vw,44px)}.services-carousel__header{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:28px}.services-carousel__controls{display:flex;gap:10px;margin-left:auto}.services-carousel__control{width:46px;height:46px;min-height:46px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.08);color:#fff;font-size:1.5rem;line-height:1;cursor:pointer;transition:background 160ms ease,border-color 160ms ease,transform 160ms ease}.services-carousel__control:hover,.services-carousel__control:focus-visible{border-color:rgba(176,7,20,.78);background:rgba(176,7,20,.5)}.services-carousel__viewport{overflow:hidden;border-radius:8px}.services-carousel__track{display:flex;gap:18px;transform:translate3d(0,0,0);transition:transform 620ms cubic-bezier(.2,.7,0,1);will-change:transform}.services-carousel__card{position:relative;min-height:430px;display:flex;flex:0 0 calc((100% - 36px)/3);flex-direction:column;justify-content:flex-end;overflow:hidden;border:1px solid rgba(255,255,255,.13);border-radius:8px;padding:24px;isolation:isolate;text-decoration:none;background:#121214;box-shadow:0 24px 58px rgba(0,0,0,.34);transform:scale(.94);opacity:.62;transition:transform 420ms ease,opacity 420ms ease,border-color 180ms ease,box-shadow 180ms ease}.services-carousel__card.is-active{transform:scale(1);opacity:1}.services-carousel__card::before{content:"";position:absolute;inset:-4%;z-index:-2;background-image:var(--service-card-bg);background-size:cover;background-position:center;filter:saturate(.82) contrast(1.04) brightness(.7);transform:scale(1.06);transition:transform 500ms ease,filter 500ms ease}.services-carousel__card::after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(0,0,0,.16),rgba(0,0,0,.56) 44%,rgba(7,7,8,.9))}.services-carousel__card:hover,.services-carousel__card:focus-visible{border-color:rgba(176,7,20,.9);box-shadow:0 26px 68px rgba(0,0,0,.46),0 0 0 1px rgba(176,7,20,.18) inset;outline:none}.services-carousel__card:hover::before,.services-carousel__card:focus-visible::before{filter:saturate(.95) contrast(1.08) brightness(.82);transform:scale(1.1)}.services-carousel__number{position:absolute;top:22px;left:22px;display:inline-flex;align-items:center;justify-content:center;width:48px;height:34px;border-radius:999px;background:#a90707;color:#fff;font-size:.84rem;font-weight:850}.services-carousel__card h3{max-width:12ch;margin:0 0 12px;color:#fff;font-size:clamp(1.55rem,2.5vw,2.35rem);line-height:1.02}.services-carousel__card p{max-width:46ch;margin:0;color:#e4e4e7;font-size:1rem;line-height:1.5}.services-carousel__cta{width:max-content;margin-top:22px;border-bottom:2px solid #b00714;color:#fff;font-size:.92rem;font-weight:850}.services-carousel__pagination{display:flex;justify-content:center;gap:8px;margin-top:18px}.services-carousel__dot{width:10px;height:10px;border:0;border-radius:999px;background:rgba(255,255,255,.28);cursor:pointer;transition:width 160ms ease,background 160ms ease}.services-carousel__dot[aria-selected="true"]{width:28px;background:#b00714}@media(max-width:940px){.services-carousel__header{justify-content:flex-end}.services-carousel__card{flex-basis:calc((100% - 18px)/2)}}@media(max-width:620px){.services-carousel__inner{padding-inline:16px}.services-carousel__track{gap:14px}.services-carousel__card{min-height:390px;flex-basis:100%;padding:20px}.services-carousel__card h3{max-width:14ch}}
  `;

  const addStyle = (id, css) => {
    if (document.getElementById(id)) return;
    const style = document.createElement("style");
    style.id = id;
    style.textContent = css;
    document.head.appendChild(style);
  };

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

  const installAboutSection = () => {
    if (document.querySelector(".about-preview")) return true;
    const positioningSection = document.querySelector("main .section.intro");
    if (!positioningSection) return false;
    addStyle("about-preview-styles", aboutStyles);

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
          <div class="about-preview__actions"><a class="button primary" href="/about.html" data-track="about_preview_learn_more">Learn More</a></div>
        </div>
      </div>
    `;
    positioningSection.insertAdjacentElement("afterend", section);
    return true;
  };

  const initializeServicesCarousel = (section) => {
    if (section.dataset.carouselReady === "true") return;
    section.dataset.carouselReady = "true";
    section.dataset.autoplayReady = "true";

    const track = section.querySelector(".services-carousel__track");
    const cards = Array.from(section.querySelectorAll(".services-carousel__card"));
    const prev = section.querySelector("[data-services-prev]");
    const next = section.querySelector("[data-services-next]");
    const dots = Array.from(section.querySelectorAll(".services-carousel__dot"));
    if (!track || !cards.length) return;

    let index = 0;
    let timer = null;

    const visibleCount = () => {
      if (window.matchMedia("(max-width: 620px)").matches) return 1;
      if (window.matchMedia("(max-width: 940px)").matches) return 2;
      return 3;
    };

    const maxIndex = () => Math.max(0, cards.length - visibleCount());
    const clampIndex = (value) => Math.min(Math.max(value, 0), maxIndex());

    const render = () => {
      index = clampIndex(index);
      const gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap || 0) || 0;
      const cardWidth = cards[0].getBoundingClientRect().width;
      track.style.transform = `translate3d(${-index * (cardWidth + gap)}px,0,0)`;
      cards.forEach((card, cardIndex) => {
        card.classList.toggle("is-active", cardIndex === index);
        card.setAttribute("aria-hidden", cardIndex < index || cardIndex >= index + visibleCount() ? "true" : "false");
      });
      dots.forEach((dot, dotIndex) => dot.setAttribute("aria-selected", dotIndex === index ? "true" : "false"));
    };

    const goTo = (nextIndex) => {
      index = clampIndex(nextIndex);
      render();
    };

    const stop = () => {
      if (timer) window.clearInterval(timer);
      timer = null;
    };

    const start = () => {
      stop();
      timer = window.setInterval(() => goTo(index >= maxIndex() ? 0 : index + 1), 3600);
    };

    prev?.addEventListener("click", () => {
      goTo(index <= 0 ? maxIndex() : index - 1);
      start();
    });
    next?.addEventListener("click", () => {
      goTo(index >= maxIndex() ? 0 : index + 1);
      start();
    });
    dots.forEach((dot, dotIndex) => dot.addEventListener("click", () => {
      goTo(dotIndex);
      start();
    }));
    section.addEventListener("mouseenter", stop);
    section.addEventListener("mouseleave", start);
    section.addEventListener("focusin", stop);
    section.addEventListener("focusout", start);
    window.addEventListener("resize", render);

    render();
    start();
  };

  const installServicesCarousel = () => {
    const existing = document.querySelector(".services-carousel");
    if (existing) {
      initializeServicesCarousel(existing);
      return true;
    }
    const solutionsSection = document.querySelector("main #solutions");
    if (!solutionsSection) return false;
    addStyle("services-carousel-styles", carouselStyles);

    const section = document.createElement("section");
    section.className = "services-carousel";
    section.setAttribute("aria-label", "Our services");
    section.innerHTML = `
      <div class="services-carousel__inner">
        <div class="services-carousel__header">
          <p class="eyebrow">Our services</p>
          <div class="services-carousel__controls" aria-label="Service carousel controls">
            <button class="services-carousel__control" type="button" aria-label="Previous service" data-services-prev>&lsaquo;</button>
            <button class="services-carousel__control" type="button" aria-label="Next service" data-services-next>&rsaquo;</button>
          </div>
        </div>
        <div class="services-carousel__viewport">
          <div class="services-carousel__track" aria-roledescription="carousel" aria-label="Oligarchy Services service cards">
            ${serviceCards.map((card, cardIndex) => `
              <a class="services-carousel__card" href="${card.href}" role="group" aria-roledescription="slide" aria-label="${cardIndex + 1} of ${serviceCards.length}: ${card.title}" style="--service-card-bg: url('${card.image}')">
                <span class="services-carousel__number">${card.number}</span>
                <h3>${card.title}</h3>
                <p>${card.body}</p>
                <span class="services-carousel__cta">${card.cta}</span>
              </a>
            `).join("")}
          </div>
        </div>
        <div class="services-carousel__pagination" role="tablist" aria-label="Choose a service card">
          ${serviceCards.map((card, cardIndex) => `<button class="services-carousel__dot" type="button" role="tab" aria-label="Go to ${card.title}" aria-selected="${cardIndex === 0 ? "true" : "false"}"></button>`).join("")}
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
