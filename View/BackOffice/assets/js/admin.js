document.addEventListener("DOMContentLoaded", () => {
  const currentPage = window.location.pathname.split("/").pop();
  const validationEndpoint = "assets/js/validate-skillhub.php";

  document.querySelectorAll(".nav-item").forEach((link) => {
    link.classList.toggle("active", link.getAttribute("href") === currentPage);
  });

  document.querySelectorAll(".filter, .status-chip, .link-btn").forEach((item) => {
    item.addEventListener("click", () => {
      const className = item.classList[0];
      item.parentElement?.querySelectorAll(`.${className}`).forEach((node) => {
        node.classList.remove("is-selected");
      });
      item.classList.add("is-selected");
    });
  });

  document.querySelectorAll(".searchbar input").forEach((input) => {
    input.addEventListener("focus", () => input.closest(".searchbar")?.classList.add("is-focused"));
    input.addEventListener("blur", () => input.closest(".searchbar")?.classList.remove("is-focused"));
  });


  
  const setFieldError = (input, errorElement, message) => {
    if (errorElement) {
      errorElement.textContent = message;
    }

    input.classList.toggle("is-invalid", Boolean(message));
    input.dataset.invalid = message ? "true" : "false";
  };

  const debounce = (callback, delay = 350) => {
    let timeoutId;
    return (...args) => {
      window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(() => callback(...args), delay);
    };
  };

  const getErrorElement = (input) => {
    if (!input.id) {
      return null;
    }

    const errorIdMap = {
      hubNameInput: "hubNameError",
      hubCategorySelect: "hubCategoryError",
      hubStatusSelect: "hubStatusError",
      hubDescriptionInput: "hubDescriptionError",
      challengeTitleInput: "challengeTitleError",
      challengeGroupSelect: "challengeGroupError",
      challengeManagerSelect: "challengeManagerError",
      challengeTypeSelect: "challengeTypeError",
      challengeDifficultySelect: "challengeDifficultyError",
      challengeStatusSelect: "challengeStatusError",
      challengeDeadlineInput: "challengeDeadlineError",
      challengeDescriptionInput: "challengeDescriptionError",
    };

    return document.getElementById(errorIdMap[input.id] || "");
  };

  const validateRequiredField = (input) => {
    const requiredMessage = input.dataset.requiredMessage;
    if (!requiredMessage) {
      return true;
    }

    const value = input.value.trim();
    const errorElement = getErrorElement(input);
    const message = value ? "" : requiredMessage;
    setFieldError(input, errorElement, message);
    return !message;
  };

  const hubNameInput = document.getElementById("hubNameInput");
  const hubNameError = document.getElementById("hubNameError");
  if (hubNameInput && hubNameError) {
    const validateHubName = debounce(async () => {
      if (!validateRequiredField(hubNameInput)) {
        return;
      }

      const name = hubNameInput.value.trim();

      const params = new URLSearchParams({
        type: "hub_name",
        name,
      });

      const excludeId = hubNameInput.dataset.excludeId || "";
      if (excludeId && excludeId !== "0") {
        params.set("excludeGroupId", excludeId);
      }

      const response = await fetch(`${validationEndpoint}?${params.toString()}`);
      const result = await response.json();
      setFieldError(hubNameInput, hubNameError, result.message || "");
    });

    hubNameInput.addEventListener("input", validateHubName);
    hubNameInput.addEventListener("blur", validateHubName);
  }

  const challengeTitleInput = document.getElementById("challengeTitleInput");
  const challengeGroupSelect = document.getElementById("challengeGroupSelect");
  const challengeTitleError = document.getElementById("challengeTitleError");
  if (challengeTitleInput && challengeGroupSelect && challengeTitleError) {
    const validateChallengeTitle = debounce(async () => {
      const titleIsValid = validateRequiredField(challengeTitleInput);
      const groupIsValid = validateRequiredField(challengeGroupSelect);
      if (!titleIsValid || !groupIsValid) {
        return;
      }

      const title = challengeTitleInput.value.trim();
      const groupId = challengeGroupSelect.value;

      const params = new URLSearchParams({
        type: "challenge_title",
        title,
        groupId,
      });

      const excludeId = challengeTitleInput.dataset.excludeId || "";
      if (excludeId && excludeId !== "0") {
        params.set("excludeChallengeId", excludeId);
      }

      const response = await fetch(`${validationEndpoint}?${params.toString()}`);
      const result = await response.json();
      setFieldError(challengeTitleInput, challengeTitleError, result.message || "");
    });

    challengeTitleInput.addEventListener("input", validateChallengeTitle);
    challengeTitleInput.addEventListener("blur", validateChallengeTitle);
    challengeGroupSelect.addEventListener("change", validateChallengeTitle);
  }

  const challengeDeadlineInput = document.getElementById("challengeDeadlineInput");
  const challengeDeadlineError = document.getElementById("challengeDeadlineError");
  if (challengeDeadlineInput && challengeDeadlineError) {
    const validateDeadline = () => {
      const value = challengeDeadlineInput.value;
      if (!value) {
        setFieldError(challengeDeadlineInput, challengeDeadlineError, "");
        return;
      }

      const selectedDate = new Date(value);
      const now = new Date();
      const message = selectedDate < now ? "Deadline cannot be in the past." : "";
      setFieldError(challengeDeadlineInput, challengeDeadlineError, message);
    };

    challengeDeadlineInput.addEventListener("input", validateDeadline);
    challengeDeadlineInput.addEventListener("change", validateDeadline);
    challengeDeadlineInput.addEventListener("blur", validateDeadline);
  }

  document.querySelectorAll("[data-required-message]").forEach((field) => {
    const eventName = field.tagName === "SELECT" ? "change" : "input";
    field.addEventListener(eventName, () => validateRequiredField(field));
    field.addEventListener("blur", () => validateRequiredField(field));
  });

  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", (event) => {
      form.querySelectorAll("[data-required-message]").forEach((field) => {
        validateRequiredField(field);
      });

      if (form.id === "workForm" && challengeDeadlineInput && challengeDeadlineError) {
        const value = challengeDeadlineInput.value;
        if (value) {
          const selectedDate = new Date(value);
          const now = new Date();
          setFieldError(
            challengeDeadlineInput,
            challengeDeadlineError,
            selectedDate < now ? "Deadline cannot be in the past." : ""
          );
        }
      }

      const invalidField = form.querySelector("[data-invalid='true']");
      if (invalidField) {
        event.preventDefault();
        invalidField.focus();
      }
    });
  });
});
