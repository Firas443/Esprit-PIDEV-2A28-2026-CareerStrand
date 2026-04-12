document.addEventListener("DOMContentLoaded", () => {
  const currentPage = window.location.pathname.split("/").pop();

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
});

