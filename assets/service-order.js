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
      font-weight: 700;
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
        font-weight: 650;
      }

      .about-preview__actions .button {
        width: 100%;
      }
    }
  `;

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

  const syncPage = () => {
    const menuReady = syncServiceMenu();
    const aboutReady = installAboutSection();
    return menuReady && aboutReady;
  };

  if (syncPage()) return;

  const observer = new MutationObserver(() => {
    if (syncPage()) observer.disconnect();
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
