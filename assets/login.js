(function () {
  const form = document.getElementById("client-login-form");
  if (!form) return;

  const email = form.querySelector("#email");
  const password = form.querySelector("#password");
  const rememberEmail = form.querySelector("#remember-email");
  const message = form.querySelector("#login-message");
  const emailError = form.querySelector("#email-error");
  const passwordError = form.querySelector("#password-error");
  const passwordToggle = form.querySelector(".password-toggle");
  const storageKey = "oligarchy_client_email";

  const savedEmail = window.localStorage.getItem(storageKey);
  if (savedEmail) {
    email.value = savedEmail;
    rememberEmail.checked = true;
  }

  const setFieldError = (field, errorElement, text) => {
    field.setAttribute("aria-invalid", text ? "true" : "false");
    if (text) field.setAttribute("aria-describedby", errorElement.id);
    else field.removeAttribute("aria-describedby");
    errorElement.textContent = text;
  };

  const setMessage = (text, type) => {
    message.textContent = text;
    message.className = "form-alert";
    if (text) message.classList.add("is-visible", type === "success" ? "is-success" : "is-error");
  };

  const validate = () => {
    const emailValue = email.value.trim();
    const passwordValue = password.value;
    let isValid = true;

    if (!emailValue) {
      setFieldError(email, emailError, "Enter your email address.");
      isValid = false;
    } else if (!email.validity.valid) {
      setFieldError(email, emailError, "Enter a valid email address.");
      isValid = false;
    } else {
      setFieldError(email, emailError, "");
    }

    if (!passwordValue) {
      setFieldError(password, passwordError, "Enter your password.");
      isValid = false;
    } else if (passwordValue.length < 8) {
      setFieldError(password, passwordError, "Password must be at least 8 characters.");
      isValid = false;
    } else {
      setFieldError(password, passwordError, "");
    }

    return isValid;
  };

  passwordToggle.addEventListener("click", () => {
    const shouldShow = password.type === "password";
    password.type = shouldShow ? "text" : "password";
    passwordToggle.textContent = shouldShow ? "Hide" : "Show";
    passwordToggle.setAttribute("aria-pressed", String(shouldShow));
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    setMessage("", "error");

    if (!validate()) {
      setMessage("Check the highlighted fields before continuing.", "error");
      return;
    }

    if (rememberEmail.checked) window.localStorage.setItem(storageKey, email.value.trim());
    else window.localStorage.removeItem(storageKey);

    if (form.dataset.staticLogin === "true") {
      setMessage("Portal authentication is not active on this static preview. Request access and Oligarchy Services will connect your workspace.", "error");
      return;
    }

    const submitButton = form.querySelector("[type='submit']");
    if (submitButton) {
      submitButton.setAttribute("disabled", "disabled");
      submitButton.textContent = "Signing in...";
    }

    try {
      const response = await fetch(form.action, {
        method: form.method || "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      if (response.redirected && response.url) {
        window.location.assign(response.url);
        return;
      }

      const contentType = response.headers.get("content-type") || "";
      const result = contentType.includes("application/json") ? await response.json() : null;

      if (!response.ok || !result?.ok) {
        const fallback = response.status >= 500
          ? "Login service is unavailable. Please try again in a moment."
          : "Invalid email or password.";
        setMessage(result?.message || fallback, "error");
        return;
      }

      window.location.assign(result.redirect || "/dashboard.php");
    } catch (error) {
      setMessage("Could not reach the login service. Please try again.", "error");
    } finally {
      if (submitButton) {
        submitButton.removeAttribute("disabled");
        submitButton.textContent = "Sign in";
      }
    }
  });
})();
