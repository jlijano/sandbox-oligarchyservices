(function () {
  const script = document.createElement("script");
  script.defer = true;
  script.src = "/assets/process-interaction.js";
  (document.currentScript || document.head).after(script);
})();
