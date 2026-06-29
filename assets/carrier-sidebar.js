(function () {
  const addCarrierNavigation = () => {
    const playgroundSubnav = document.querySelector('[data-playground-subnav]');
    if (!playgroundSubnav) return;

    let prospectsLink = playgroundSubnav.querySelector("a[href='/prospects.php']");
    if (!prospectsLink) {
      prospectsLink = document.createElement('a');
      prospectsLink.href = '/prospects.php';
      prospectsLink.innerHTML = '<span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Prospects</span>';
      const navigationLink = playgroundSubnav.querySelector("a[href='/dashboard.php#navigation']");
      if (navigationLink) playgroundSubnav.insertBefore(prospectsLink, navigationLink);
      else playgroundSubnav.appendChild(prospectsLink);
    }

    let carrierLink = playgroundSubnav.querySelector("a[href='/carrier']");
    if (!carrierLink) {
      carrierLink = document.createElement('a');
      carrierLink.href = '/carrier';
      carrierLink.innerHTML = '<span class="nav-icon" aria-hidden="true"><i class="fa fa-envelope-o" aria-hidden="true"></i></span><span class="nav-label">Carrier</span>';
      prospectsLink.insertAdjacentElement('afterend', carrierLink);
    }

    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    const isCarrier = currentPath === '/carrier' || currentPath === '/carrier.php';
    carrierLink.classList.toggle('is-active', isCarrier);
    if (isCarrier) carrierLink.setAttribute('aria-current', 'page');

    const group = playgroundSubnav.closest('[data-playground-group]');
    if (group && isCarrier) {
      group.classList.add('is-open', 'is-active');
      const toggle = group.querySelector('[data-playground-toggle]');
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addCarrierNavigation, { once: true });
  else addCarrierNavigation();
})();
