(function () {
  const form = document.getElementById("login-form");
  if (!form) return;

  const identifier = document.getElementById("login-identifier");
  const password = document.getElementById("login-password");
  const submitButton = form.querySelector(".login-submit");
  const submitLabel = submitButton.querySelector(".button-label");
  const errorAlert = document.getElementById("login-error");
  const passwordToggle = form.querySelector(".password-toggle");

  const fieldErrors = {
    identifier: document.getElementById("identifier-error"),
    password: document.getElementById("password-error")
  };

  const showFormError = (message) => {
    errorAlert.textContent = message;
    errorAlert.hidden = false;
  };

  const clearFormError = () => {
    errorAlert.textContent = "";
    errorAlert.hidden = true;
  };

  const setFieldError = (field, message) => {
    const error = fieldErrors[field.name];
    if (!error) return;
    error.textContent = message;
    field.setAttribute("aria-invalid", message ? "true" : "false");
    field.setAttribute("aria-describedby", message ? error.id : "");
  };

  const validate = () => {
    let isValid = true;
    const identifierValue = identifier.value.trim();
    const passwordValue = password.value;

    setFieldError(identifier, "");
    setFieldError(password, "");
    clearFormError();

    if (!identifierValue) {
      setFieldError(identifier, "Enter your email or username.");
      isValid = false;
    } else if (identifierValue.includes("@") && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(identifierValue)) {
      setFieldError(identifier, "Enter a valid email address, or use your username.");
      isValid = false;
    }

    if (!passwordValue) {
      setFieldError(password, "Enter your password.");
      isValid = false;
    } else if (passwordValue.length < 8) {
      setFieldError(password, "Password must be at least 8 characters.");
      isValid = false;
    }

    return isValid;
  };

  const setLoading = (isLoading) => {
    submitButton.disabled = isLoading;
    submitButton.setAttribute("aria-busy", isLoading ? "true" : "false");
    submitLabel.textContent = isLoading ? "Checking access..." : submitButton.dataset.defaultLabel;
    identifier.disabled = isLoading;
    password.disabled = isLoading;
    passwordToggle.disabled = isLoading;
  };

  passwordToggle.addEventListener("click", () => {
    const isPasswordVisible = password.type === "text";
    password.type = isPasswordVisible ? "password" : "text";
    passwordToggle.textContent = isPasswordVisible ? "Show" : "Hide";
    passwordToggle.setAttribute("aria-label", isPasswordVisible ? "Show password" : "Hide password");
    password.focus();
  });

  [identifier, password].forEach((field) => {
    field.addEventListener("input", () => {
      if (field.getAttribute("aria-invalid") === "true") validate();
      if (!errorAlert.hidden) clearFormError();
    });
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!validate()) return;

    setLoading(true);

    window.setTimeout(() => {
      setLoading(false);
      showFormError("Login is ready for your authentication service. Connect this form to the approved sign-in endpoint before accepting real credentials.");
    }, 700);
  });
})();
