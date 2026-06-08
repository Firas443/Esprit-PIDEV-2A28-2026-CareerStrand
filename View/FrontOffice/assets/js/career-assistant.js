(() => {
  const toggle = document.getElementById('careerAssistantToggle');
  const panel = document.getElementById('careerAssistantPanel');
  const backdrop = document.getElementById('careerAssistantBackdrop');
  const closeBtn = document.getElementById('careerAssistantClose');
  const form = document.getElementById('careerAssistantForm');
  const input = document.getElementById('careerAssistantInput');
  const messages = document.getElementById('careerAssistantMessages');

  if (!toggle || !panel || !backdrop || !closeBtn || !form || !input || !messages) {
    return;
  }

  const userId = toggle.dataset.userId || 'guest';
  const storageKey = `career-assistant-${userId}`;
  const promptButtons = Array.from(panel.querySelectorAll('[data-assistant-prompt]'));
  const sendButton = form.querySelector('.cs-assistant-send');
  const defaultGreeting = {
    role: 'assistant',
    title: 'Career helper ready',
    content: 'I can help you decide what to do next, explain your dashboard, and turn your profile data into simple next steps.'
  };

  let conversation = loadConversation();
  let isOpen = false;
  let isBusy = false;

  function loadConversation() {
    try {
      const stored = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
      if (Array.isArray(stored)) {
        return stored.filter(item => item && typeof item.role === 'string' && typeof item.content === 'string');
      }
    } catch (error) {
      // Ignore malformed storage.
    }

    return [];
  }

  function saveConversation() {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(conversation.slice(-12)));
    } catch (error) {
      // Ignore storage failures.
    }
  }

  function createMessageElement(entry) {
    const article = document.createElement('article');
    article.className = `cs-assistant-message cs-assistant-message-${entry.role === 'user' ? 'user' : 'bot'}`;

    const avatar = document.createElement('div');
    avatar.className = 'cs-assistant-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = entry.role === 'user' ? 'You' : 'CS';

    const bubble = document.createElement('div');
    bubble.className = 'cs-assistant-bubble';

    const title = document.createElement('strong');
    title.textContent = entry.title || (entry.role === 'user' ? 'You' : 'Career helper');

    const body = document.createElement('p');
    body.textContent = entry.content;

    bubble.appendChild(title);
    bubble.appendChild(body);

    if (entry.role === 'user') {
      article.appendChild(bubble);
      article.appendChild(avatar);
    } else {
      article.appendChild(avatar);
      article.appendChild(bubble);
    }

    return article;
  }

  function renderMessages() {
    messages.innerHTML = '';
    messages.appendChild(createMessageElement(defaultGreeting));

    conversation.forEach(entry => {
      messages.appendChild(createMessageElement(entry));
    });

    scrollMessagesToBottom();
  }

  function scrollMessagesToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function openPanel() {
    isOpen = true;
    panel.hidden = false;
    backdrop.hidden = false;
    requestAnimationFrame(() => {
      panel.classList.add('is-open');
      backdrop.classList.add('is-open');
    });
    panel.setAttribute('aria-hidden', 'false');
    toggle.setAttribute('aria-expanded', 'true');
    if (!conversation.length) {
      renderMessages();
    }
    window.setTimeout(() => input.focus(), 0);
  }

  function closePanel() {
    isOpen = false;
    panel.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    toggle.setAttribute('aria-expanded', 'false');

    window.setTimeout(() => {
      if (!isOpen) {
        panel.hidden = true;
        backdrop.hidden = true;
      }
    }, 220);
  }

  function setBusy(nextBusy) {
    isBusy = nextBusy;
    input.disabled = nextBusy;
    if (sendButton) {
      sendButton.disabled = nextBusy;
      sendButton.textContent = nextBusy ? 'Thinking...' : 'Send';
    }
    promptButtons.forEach(button => {
      button.disabled = nextBusy;
    });
  }

  function pushMessage(role, content, title) {
    conversation.push({ role, content, title: title || (role === 'user' ? 'You' : 'Career helper') });
    renderMessages();
    saveConversation();
  }

  function setTypingIndicator(active) {
    const existing = messages.querySelector('[data-typing="true"]');
    if (existing) {
      existing.remove();
    }

    if (!active) {
      return;
    }

    const article = document.createElement('article');
    article.className = 'cs-assistant-message cs-assistant-message-bot';
    article.dataset.typing = 'true';

    const avatar = document.createElement('div');
    avatar.className = 'cs-assistant-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = 'CS';

    const bubble = document.createElement('div');
    bubble.className = 'cs-assistant-bubble';

    const title = document.createElement('strong');
    title.textContent = 'Thinking';

    const body = document.createElement('p');
    body.textContent = 'Working through your profile, history, and question...';

    bubble.appendChild(title);
    bubble.appendChild(body);
    article.appendChild(avatar);
    article.appendChild(bubble);
    messages.appendChild(article);
    scrollMessagesToBottom();
  }

  async function sendMessage(message) {
    if (isBusy || message.trim() === '') {
      return;
    }

    if (!isOpen) {
      openPanel();
    }

    pushMessage('user', message, 'You');
    input.value = '';
    setBusy(true);
    setTypingIndicator(true);

    try {
      const response = await fetch('../../api/career_assistant.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          message,
          history: conversation
        })
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        throw new Error(data.error || 'The assistant could not respond right now.');
      }

      setTypingIndicator(false);
      pushMessage('assistant', data.reply || 'I do not have an answer yet, but I can still help with your profile, courses, opportunities, and dashboard.');
    } catch (error) {
      setTypingIndicator(false);
      pushMessage('assistant', 'I could not reach the assistant just now. Try again in a moment, or ask a shorter question.');
    } finally {
      setBusy(false);
      window.setTimeout(() => scrollMessagesToBottom(), 0);
    }
  }

  toggle.addEventListener('click', () => {
    if (isOpen) {
      closePanel();
    } else {
      openPanel();
    }
  });

  closeBtn.addEventListener('click', closePanel);
  backdrop.addEventListener('click', closePanel);

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && isOpen) {
      closePanel();
    }
  });

  form.addEventListener('submit', event => {
    event.preventDefault();
    sendMessage(input.value.trim());
  });

  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage(input.value.trim());
    }
  });

  promptButtons.forEach(button => {
    button.addEventListener('click', () => {
      const prompt = button.dataset.assistantPrompt || '';
      if (prompt === '') {
        return;
      }

      input.value = prompt;
      sendMessage(prompt);
    });
  });

  renderMessages();
})();
