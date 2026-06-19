(function () {
  const script = document.createElement("script");
  script.defer = true;
  script.src = "/assets/analytics.js";
  (document.currentScript || document.head).after(script);
})();
