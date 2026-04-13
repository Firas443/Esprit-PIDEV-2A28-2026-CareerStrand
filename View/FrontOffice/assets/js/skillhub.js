document.addEventListener("DOMContentLoaded", () => {
  const filterChips = Array.from(document.querySelectorAll(".filter-chip"));
  const searchInput = document.getElementById("hubSearch");
  const hubCards = Array.from(document.querySelectorAll(".hub-card"));
  const joinButtons = Array.from(document.querySelectorAll(".join-btn"));

  let activeFilter = "all";

  const applyFilters = () => {
    const query = (searchInput?.value || "").trim().toLowerCase();

    hubCards.forEach((card) => {
      const matchesFilter = activeFilter === "all" || card.dataset.category === activeFilter;
      const haystack = card.dataset.search || "";
      const matchesSearch = !query || haystack.includes(query);
      card.classList.toggle("is-hidden", !(matchesFilter && matchesSearch));
    });
  };

  filterChips.forEach((chip) => {
    chip.addEventListener("click", () => {
      filterChips.forEach((item) => item.classList.remove("active"));
      chip.classList.add("active");
      activeFilter = chip.dataset.filter;
      applyFilters();
    });
  });

  searchInput?.addEventListener("input", applyFilters);

  joinButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const joined = button.classList.toggle("is-joined");
      button.textContent = joined ? "Joined" : "Join hub";
    });
  });

  applyFilters();
});
