document.addEventListener("DOMContentLoaded", () => {
  const tabs = document.querySelectorAll(".feed-tab");
  const cards = document.querySelectorAll(".feed-card");
  const hubPills = document.querySelectorAll(".hub-pill");
  const modeChips = document.querySelectorAll(".mode-chip");
  const drawerOpen = document.getElementById("drawerOpen");
  const drawerClose = document.getElementById("drawerClose");
  const drawerBackdrop = document.getElementById("drawerBackdrop");
  const saveButtons = document.querySelectorAll("[data-save-btn]");
  const feed = document.getElementById("feed");
  const hubComposer = document.getElementById("hubComposer");
  const savedFeed = document.getElementById("savedFeed");
  const calendarFeed = document.getElementById("calendarFeed");
  const savedFeedList = document.getElementById("savedFeedList");
  const savedFeedEmpty = document.getElementById("savedFeedEmpty");
  const pinnedBoardList = document.getElementById("pinnedBoardList");
  const pinnedBoardEmpty = document.getElementById("pinnedBoardEmpty");
  const openCalendarView = document.getElementById("openCalendarView");
  const openPostComposer = document.getElementById("openPostComposer");
  const postTypeTriggers = document.querySelectorAll("[data-post-type-trigger], [data-open-post-modal]");
  const postModal = document.getElementById("postModal");
  const postModalBackdrop = document.getElementById("postModalBackdrop");
  const closePostModal = document.getElementById("closePostModal");
  const cancelPostModal = document.getElementById("cancelPostModal");
  const postTypeCards = document.querySelectorAll("[data-post-type]");
  const postTitleInput = document.getElementById("postTitleInput");

  let currentView = "all";
  let currentHub = "all";
  const savedCards = new Map();
  const pinnedCards = new Map();

  function closeDrawer() {
    document.body.classList.remove("drawer-open");
  }

  function setPostType(type) {
    const normalized = type === "link" ? "resource" : type === "image" ? "discussion" : type;
    const finalType = normalized || "discussion";

    postTypeCards.forEach((card) => {
      card.classList.toggle("active", card.dataset.postType === finalType);
    });

    if (postTitleInput) {
      const placeholderMap = {
        discussion: "Write a clear title for the thread you want to open",
        question: "Ask the question you want the forum to answer",
        resource: "Name the resource and make its value clear",
      };
      postTitleInput.placeholder = placeholderMap[finalType] || "Write a clear title for your post";
    }
  }

  function openPostModalWith(type) {
    setPostType(type);
    document.body.classList.add("post-modal-open");
    postModal?.setAttribute("aria-hidden", "false");
    setTimeout(() => {
      postTitleInput?.focus();
    }, 20);
  }

  function closePostComposerModal() {
    document.body.classList.remove("post-modal-open");
    postModal?.setAttribute("aria-hidden", "true");
  }

  function openThreadPage(view = "overview") {
    window.location.href = `thread.php?view=${view}`;
  }

  function applyFilters() {
    cards.forEach((card) => {
      const typeMatch = currentView === "all" || card.dataset.type === currentView;
      const hubMatch = currentHub === "all" || card.dataset.hub === currentHub;
      const isSavedWork = savedCards.has(card.dataset.savedId || "");
      const shouldShowInHub = typeMatch && hubMatch && !isSavedWork;
      card.classList.toggle("is-hidden", !shouldShowInHub);
    });
  }

  function setMode(mode) {
    modeChips.forEach((chip) => chip.classList.toggle("active", chip.dataset.mode === mode));

    const showSaved = mode === "saved";
    const showCalendar = mode === "calendar";
    const showHub = !showSaved && !showCalendar;

    feed?.classList.toggle("is-hidden", !showHub);
    hubComposer?.classList.toggle("is-hidden", !showHub);
    savedFeed?.classList.toggle("is-hidden", !showSaved);
    calendarFeed?.classList.toggle("is-hidden", !showCalendar);
  }

  function setCardSavedState(card, isSaved) {
    card.querySelectorAll("[data-save-btn]").forEach((button) => {
      button.classList.toggle("is-saved", isSaved);
      button.textContent = isSaved ? "Saved" : "Save";
      button.setAttribute("aria-pressed", String(isSaved));
    });
  }

  function collectCardData(card) {
    const contentTag = card.querySelector(".content-tag");
    const avatar = card.querySelector(".avatar");
    const infoContainer = card.querySelector(".content-meta, .engagement-row");

    return {
      kind: card.dataset.type === "projects" ? "project" : "task",
      badge: contentTag?.textContent?.trim() || "Saved",
      title: card.querySelector("h2, h3")?.textContent?.trim() || "Saved item",
      description: card.querySelector("p")?.textContent?.trim() || "",
      author: card.querySelector(".author-name")?.textContent?.trim() || "CareerStrand",
      metaLine: card.querySelector(".author-meta")?.textContent?.trim() || "",
      avatar: avatar?.textContent?.trim() || "CS",
      avatarClass: Array.from(avatar?.classList || []).filter((name) => name !== "avatar").join(" "),
      infoClass: infoContainer?.classList.contains("engagement-row") ? "engagement-row" : "content-meta",
      info: Array.from(infoContainer?.querySelectorAll("span") || []).map((node) => node.textContent.trim()),
    };
  }

  function renderPinnedBoard() {
    if (!pinnedBoardList || !pinnedBoardEmpty) {
      return;
    }

    pinnedBoardList.innerHTML = "";

    if (pinnedCards.size === 0) {
      pinnedBoardEmpty.hidden = false;
      return;
    }

    pinnedBoardEmpty.hidden = true;

    pinnedCards.forEach((item, id) => {
      const row = document.createElement("div");
      row.className = "queue-item";
      row.innerHTML = `
        <span class="queue-status ${item.kind === "project" ? "progress" : "open"}"></span>
        <div>
          <strong>${item.title}</strong>
          <p>${item.badge} · ${item.metaLine}</p>
        </div>
        <button class="link-btn board-remove" type="button" data-unpin-work="${id}">Unpin</button>
      `;
      pinnedBoardList.appendChild(row);
    });
  }

  function renderSavedFeed() {
    if (!savedFeedList || !savedFeedEmpty) {
      return;
    }

    savedFeedList.innerHTML = "";

    if (savedCards.size === 0) {
      savedFeedEmpty.hidden = false;
      return;
    }

    savedFeedEmpty.hidden = true;

    savedCards.forEach((item, id) => {
      const article = document.createElement("article");
      article.className = "feed-card saved-work-card";
      const isPinned = pinnedCards.has(id);

      article.innerHTML = `
        <div class="card-head">
          <div class="author-line">
            <div class="avatar ${item.avatarClass}">${item.avatar}</div>
            <div>
              <div class="author-name">${item.author}</div>
              <div class="author-meta">${item.metaLine}</div>
            </div>
          </div>
          <div class="saved-card-tools">
            <button class="pin-btn ${isPinned ? "is-pinned" : ""}" type="button" data-pin-work="${id}" aria-pressed="${String(isPinned)}">${isPinned ? "Pinned" : "Pin"}</button>
            <span class="content-tag ${item.kind}">${item.badge}</span>
          </div>
        </div>
        <h3>${item.title}</h3>
        <p>${item.description}</p>
        <div class="${item.infoClass}">
          ${item.info.map((line) => `<span>${line}</span>`).join("")}
        </div>
        <div class="card-actions">
          <button class="primary-btn" data-open-thread-view="overview">${item.kind === "project" ? "Open project" : "Start task"}</button>
          <button class="ghost-btn" type="button" data-remove-saved="${id}">Remove</button>
          <button class="ghost-btn" data-open-thread-view="discussion">${item.kind === "project" ? "Open discussion" : "Open thread"}</button>
        </div>
      `;

      savedFeedList.appendChild(article);
    });
  }

  function toggleSavedCard(card) {
    if (!card || !savedFeedList || !savedFeedEmpty) {
      return;
    }

    const id =
      card.dataset.savedId ||
      `${card.dataset.type}-${card.querySelector(".author-name")?.textContent?.trim() || "item"}-${card.querySelector("h2, h3")?.textContent?.trim() || "card"}`
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "");

    card.dataset.savedId = id;

    if (savedCards.has(id)) {
      savedCards.delete(id);
      pinnedCards.delete(id);
      setCardSavedState(card, false);
      applyFilters();
      renderSavedFeed();
      renderPinnedBoard();
      return;
    }

    savedCards.set(id, collectCardData(card));
    setCardSavedState(card, true);
    applyFilters();
    renderSavedFeed();
    renderPinnedBoard();
  }

  function togglePinnedWork(id) {
    if (!savedCards.has(id)) {
      return;
    }

    if (pinnedCards.has(id)) {
      pinnedCards.delete(id);
    } else {
      pinnedCards.set(id, savedCards.get(id));
    }

    renderSavedFeed();
    renderPinnedBoard();
  }

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      tabs.forEach((item) => item.classList.remove("active"));
      tab.classList.add("active");
      currentView = tab.dataset.view;
      applyFilters();
    });
  });

  modeChips.forEach((chip) => {
    chip.addEventListener("click", () => {
      setMode(chip.dataset.mode);
    });
  });

  hubPills.forEach((pill) => {
    pill.addEventListener("click", () => {
      hubPills.forEach((item) => item.classList.remove("is-live"));
      pill.classList.add("is-live");
      currentHub = pill.dataset.hub;
      applyFilters();
      closeDrawer();
      setMode("hub");
    });
  });

  drawerOpen?.addEventListener("click", () => {
    document.body.classList.add("drawer-open");
  });

  openCalendarView?.addEventListener("click", () => {
    setMode("calendar");
  });

  drawerClose?.addEventListener("click", closeDrawer);
  drawerBackdrop?.addEventListener("click", closeDrawer);

  saveButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const card = button.closest(".feed-card");
      if (!card) {
        return;
      }

      const type = card.dataset.type;
      if (type !== "tasks" && type !== "projects") {
        return;
      }

      toggleSavedCard(card);
    });
  });

  openPostComposer?.addEventListener("click", () => {
    openPostModalWith("discussion");
  });

  openPostComposer?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      openPostModalWith("discussion");
    }
  });

  postTypeTriggers.forEach((button) => {
    button.addEventListener("click", () => {
      openPostModalWith(button.dataset.postTypeTrigger || button.dataset.openPostModal || "discussion");
    });
  });

  postTypeCards.forEach((button) => {
    button.addEventListener("click", () => {
      setPostType(button.dataset.postType);
    });
  });

  closePostModal?.addEventListener("click", closePostComposerModal);
  cancelPostModal?.addEventListener("click", closePostComposerModal);
  postModalBackdrop?.addEventListener("click", closePostComposerModal);

  savedFeedList?.addEventListener("click", (event) => {
    const threadButton = event.target.closest("[data-open-thread-view]");
    if (threadButton) {
      openThreadPage(threadButton.dataset.openThreadView);
      return;
    }

    const removeButton = event.target.closest("[data-remove-saved]");
    if (removeButton) {
      const id = removeButton.dataset.removeSaved;
      const card = document.querySelector(`.feed-card[data-saved-id="${id}"]`);

      if (card) {
        savedCards.delete(id);
        pinnedCards.delete(id);
        setCardSavedState(card, false);
        applyFilters();
        renderSavedFeed();
        renderPinnedBoard();
      }
      return;
    }

    const pinButton = event.target.closest("[data-pin-work]");
    if (pinButton) {
      togglePinnedWork(pinButton.dataset.pinWork);
    }
  });

  pinnedBoardList?.addEventListener("click", (event) => {
    const removeButton = event.target.closest("[data-unpin-work]");
    if (!removeButton) {
      return;
    }

    pinnedCards.delete(removeButton.dataset.unpinWork);
    renderSavedFeed();
    renderPinnedBoard();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeDrawer();
      closePostComposerModal();
    }
  });

  document.addEventListener("click", (event) => {
    const threadButton = event.target.closest("[data-open-thread-view]");
    if (!threadButton || savedFeedList?.contains(threadButton)) {
      return;
    }

    openThreadPage(threadButton.dataset.openThreadView);
  });

  applyFilters();
  setMode("hub");
  renderSavedFeed();
  renderPinnedBoard();
});
