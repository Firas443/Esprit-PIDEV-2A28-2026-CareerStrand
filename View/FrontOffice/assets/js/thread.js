document.addEventListener("DOMContentLoaded", () => {
  const viewChips = Array.from(document.querySelectorAll(".view-chip"));
  const overviewView = document.getElementById("overviewView");
  const discussionView = document.getElementById("discussionView");
  const joinDiscussionButton = document.getElementById("joinDiscussionBtn");
  const openHubButton = Array.from(document.querySelectorAll(".primary-btn"))
    .find((button) => button.textContent.trim() === "Open hub");

  const composerInput = document.getElementById("discussionComposer");
  const postReplyButton = document.getElementById("postReplyBtn");
  const discussionList = document.getElementById("discussionList");

  const threadOverlay = document.getElementById("threadOverlay");
  const threadDetail = document.getElementById("threadDetail");
  const closeThreadDetail = document.getElementById("closeThreadDetail");
  const threadDetailKind = document.getElementById("threadDetailKind");
  const threadDetailTitle = document.getElementById("threadDetailTitle");
  const threadDetailAvatar = document.getElementById("threadDetailAvatar");
  const threadDetailAuthor = document.getElementById("threadDetailAuthor");
  const threadDetailMeta = document.getElementById("threadDetailMeta");
  const threadDetailBadge = document.getElementById("threadDetailBadge");
  const threadDetailBody = document.getElementById("threadDetailBody");
  const threadCommentsList = document.getElementById("threadCommentsList");
  const threadCommentCount = document.getElementById("threadCommentCount");
  const threadCommentInput = document.getElementById("threadCommentInput");
  const postThreadCommentButton = document.getElementById("postThreadCommentBtn");

  const threads = {
    "maya-guidance": {
      kind: "Pinned guidance",
      title: "Focus on hierarchy before decoration.",
      author: "Maya Nwosu",
      avatar: "MN",
      avatarClass: "manager",
      meta: "Manager / 28 min ago",
      body: "The strongest submissions usually solve clarity first: what users see first, where the eye moves second, and why the call-to-action feels earned. Start there before polishing effects. Look for structure, then contrast, then restraint.",
      comments: [
        {
          author: "Fatima Kone",
          avatar: "FK",
          avatarClass: "user",
          meta: "12 min ago",
          body: "This helped me a lot. I was overworking texture before deciding what the eye should land on."
        },
        {
          author: "Samuel Bassey",
          avatar: "SB",
          avatarClass: "user",
          meta: "5 min ago",
          body: "The reminder about earning the CTA was the clearest part for me. I had mine too loud too early."
        }
      ]
    },
    "fatima-question": {
      kind: "Question",
      title: "How far can we stylize the hero before it stops feeling professional?",
      author: "Fatima Kone",
      avatar: "FK",
      avatarClass: "user",
      meta: "1 hour ago",
      body: "I'm trying to make it premium without drifting into concept-shot territory. Curious how others decide where to stop, especially when adding glows, layered gradients, or dramatic typography.",
      comments: [
        {
          author: "Maya Nwosu",
          avatar: "MN",
          avatarClass: "manager",
          meta: "48 min ago",
          body: "My rule is simple: if the styling starts competing with clarity, it has gone too far. Good stylization should sharpen the message, not interrupt it."
        },
        {
          author: "Ola Dairo",
          avatar: "OD",
          avatarClass: "user",
          meta: "32 min ago",
          body: "I usually test by muting half the effects. If the design still feels strong, I keep the reduced version."
        }
      ]
    },
    "samuel-resource": {
      kind: "Resource",
      title: "Useful reference for cleaner CTA spacing",
      author: "Samuel Bassey",
      avatar: "SB",
      avatarClass: "user",
      meta: "2 hours ago",
      body: "I found a strong breakdown on button prominence and spacing rhythm. It helped me simplify my first attempt a lot, especially around CTA stacking and negative space.",
      comments: [
        {
          author: "Amina Yusuf",
          avatar: "AY",
          avatarClass: "user",
          meta: "1 hour ago",
          body: "This was helpful. The examples showing when a secondary button should back off were especially good."
        }
      ]
    }
  };

  let activeThreadId = null;
  const initialView = new URLSearchParams(window.location.search).get("view");

  const setView = (view) => {
    const showDiscussion = view === "discussion";

    viewChips.forEach((chip) => {
      chip.classList.toggle("active", chip.dataset.view === view);
    });

    overviewView.classList.toggle("is-hidden", showDiscussion);
    discussionView.classList.toggle("is-hidden", !showDiscussion);
  };

  const renderThreadComments = (threadId) => {
    const thread = threads[threadId];

    threadCommentsList.innerHTML = "";
    threadCommentCount.textContent = `${thread.comments.length} comment${thread.comments.length === 1 ? "" : "s"}`;

    thread.comments.forEach((comment) => {
      const commentElement = document.createElement("article");
      commentElement.className = "thread-comment";
      commentElement.innerHTML = `
        <div class="thread-comment-head">
          <div class="avatar ${comment.avatarClass}">${comment.avatar}</div>
          <div>
            <div class="author-name">${comment.author}</div>
            <div class="thread-comment-meta">${comment.meta}</div>
          </div>
        </div>
        <div class="thread-comment-body">${comment.body}</div>
      `;
      threadCommentsList.appendChild(commentElement);
    });
  };

  const openThreadDetailPanel = (threadId) => {
    const thread = threads[threadId];
    if (!thread) return;

    activeThreadId = threadId;
    threadDetailKind.textContent = thread.kind;
    threadDetailTitle.textContent = thread.title;
    threadDetailAvatar.textContent = thread.avatar;
    threadDetailAvatar.className = `avatar ${thread.avatarClass}`;
    threadDetailAuthor.textContent = thread.author;
    threadDetailMeta.textContent = thread.meta;
    threadDetailBadge.textContent = thread.kind;
    threadDetailBody.textContent = thread.body;
    renderThreadComments(threadId);

    threadOverlay.classList.remove("is-hidden");
    threadDetail.classList.remove("is-hidden");
  };

  const closeThreadDetailPanel = () => {
    threadOverlay.classList.add("is-hidden");
    threadDetail.classList.add("is-hidden");
    activeThreadId = null;
    threadCommentInput.value = "";
  };

  const appendDiscussionCard = (copy) => {
    const card = document.createElement("article");
    const threadId = `user-thread-${Date.now()}`;

    threads[threadId] = {
      kind: "Discussion",
      title: copy.length > 72 ? `${copy.slice(0, 72)}...` : copy,
      author: "Amina Yusuf",
      avatar: "AY",
      avatarClass: "user",
      meta: "Just now",
      body: copy,
      comments: []
    };

    card.className = "discussion-card thread-item";
    card.dataset.threadId = threadId;
    card.dataset.threadKind = "Discussion";
    card.innerHTML = `
      <div class="discussion-head">
        <div class="author-line">
          <div class="avatar user">AY</div>
          <div>
            <div class="author-name">Amina Yusuf</div>
            <div class="author-meta">Just now</div>
          </div>
        </div>
      </div>
      <h3>${threads[threadId].title}</h3>
      <p>${copy}</p>
      <div class="reply-meta">
        <span>0 replies</span>
        <span>New thread</span>
      </div>
      <div class="reply-actions">
        <button class="link-btn open-thread-btn" type="button">Open thread</button>
        <button class="link-btn" type="button">Reply</button>
        <button class="link-btn" type="button">Save</button>
      </div>
    `;

    discussionList.prepend(card);
  };

  viewChips.forEach((chip) => {
    chip.addEventListener("click", () => {
      setView(chip.dataset.view);
    });
  });

  if (joinDiscussionButton) {
    joinDiscussionButton.addEventListener("click", () => {
      setView("discussion");
      discussionView.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  if (openHubButton) {
    openHubButton.addEventListener("click", () => {
      window.location.href = "hub.php";
    });
  }

  if (postReplyButton) {
    postReplyButton.addEventListener("click", () => {
      const value = composerInput.value.trim();
      if (!value) {
        composerInput.focus();
        return;
      }

      appendDiscussionCard(value);
      composerInput.value = "";
      setView("discussion");
    });
  }

  discussionList.addEventListener("click", (event) => {
    const threadCard = event.target.closest(".thread-item");
    if (!threadCard) return;

    openThreadDetailPanel(threadCard.dataset.threadId);
  });

  closeThreadDetail.addEventListener("click", closeThreadDetailPanel);
  threadOverlay.addEventListener("click", closeThreadDetailPanel);

  postThreadCommentButton.addEventListener("click", () => {
    if (!activeThreadId) return;

    const value = threadCommentInput.value.trim();
    if (!value) {
      threadCommentInput.focus();
      return;
    }

    threads[activeThreadId].comments.push({
      author: "Amina Yusuf",
      avatar: "AY",
      avatarClass: "user",
      meta: "Just now",
      body: value
    });

    renderThreadComments(activeThreadId);
    threadCommentInput.value = "";
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !threadDetail.classList.contains("is-hidden")) {
      closeThreadDetailPanel();
    }
  });

  if (initialView === "discussion") {
    setView("discussion");
  } else {
    setView("overview");
  }
});
