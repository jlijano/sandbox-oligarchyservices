(function () {
  const script = document.createElement("script");
  script.defer = true;
  script.src = "/assets/service-order.js";
  (document.currentScript || document.head).after(script);
})();
