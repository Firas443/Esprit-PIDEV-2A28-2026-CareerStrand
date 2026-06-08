document.addEventListener("DOMContentLoaded", () => {
  const filterChips = Array.from(document.querySelectorAll(".filter-chip"));
  const searchInput = document.getElementById("hubSearch");
  const canvas = document.getElementById("directoryNetworkCanvas");
  const savedPanel = document.getElementById("savedHubPanel");
  const savedGrid = document.getElementById("savedHubGrid");
  const savedCount = document.querySelector("[data-saved-count]");
  const savedToggleCount = document.querySelector("[data-saved-toggle-count]");
  const savedToggleButtons = Array.from(document.querySelectorAll("[data-saved-toggle]"));
  const savedBackdrop = document.querySelector("[data-saved-toggle-backdrop]");
  const savedUserId = document.body?.dataset?.userId || "guest";
  const storageKey = `careerstrand.skillhub.savedHubs.${savedUserId}`;

  let activeFilter = "all";
  let fallbackSavedIds = [];

  const escapeHtml = (value) =>
    String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");

  const saveIcon = (isSaved) => (
    isSaved
      ? `<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M3 2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>`
      : `<svg viewBox="0 0 16 16" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>`
  );

  const getSourceHubCards = () => Array.from(document.querySelectorAll(".hub-card[data-group-id]"));

  const getHubCatalog = () => {
    const catalog = new Map();

    getSourceHubCards().forEach((card) => {
      const groupId = Number(card.dataset.groupId);
      if (!Number.isFinite(groupId) || groupId <= 0) {
        return;
      }

      catalog.set(groupId, {
        groupId,
        name: card.dataset.hubName || "Skill Hub",
        description: card.dataset.hubDescription || "",
        category: card.dataset.hubCategory || "Community",
        state: card.dataset.hubState || "suggested",
        status: card.dataset.hubStatus || "",
        members: card.dataset.hubMembers || "0",
        work: card.dataset.hubWork || "0",
        threads: card.dataset.hubThreads || "0",
        openUrl: card.dataset.openUrl || `hub.php?groupId=${groupId}`,
        search: card.dataset.search || "",
      });
    });

    return catalog;
  };

  const readSavedIds = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) {
        return fallbackSavedIds.slice();
      }

      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return [];
      }

      return parsed
        .map((value) => Number(value))
        .filter((value) => Number.isFinite(value) && value > 0);
    } catch (error) {
      return fallbackSavedIds.slice();
    }
  };

  const writeSavedIds = (ids) => {
    const uniqueIds = Array.from(new Set(ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)));
    fallbackSavedIds = uniqueIds.slice();

    try {
      window.localStorage.setItem(storageKey, JSON.stringify(uniqueIds));
    } catch (error) {
      // Fall back to in-memory storage if persistence is unavailable.
    }

    return uniqueIds;
  };

  const getSavedIds = () => {
    const catalog = getHubCatalog();
    const rawSavedIds = readSavedIds();
    const savedIds = Array.from(new Set(rawSavedIds)).filter((groupId) => catalog.has(groupId));
    if (savedIds.length !== rawSavedIds.length) {
      writeSavedIds(savedIds);
    }
    return savedIds;
  };

  const renderSavedLane = () => {
    if (!savedGrid || !savedPanel) {
      return;
    }

    const catalog = getHubCatalog();
    const savedIds = getSavedIds();
    const cards = savedIds
      .map((groupId) => catalog.get(groupId))
      .filter(Boolean)
      .map((hub) => {
        const savedLabel = hub.state === "joined" ? "Joined and saved" : "Saved";
        const primaryAction = hub.state === "joined"
          ? `<a class="primary-btn" href="${escapeHtml(hub.openUrl)}">Open</a>`
          : `<a class="primary-btn" href="${escapeHtml(hub.openUrl)}">Preview</a>`;

        return `
          <article class="saved-hub-card remodel-card ${escapeHtml(hub.state)}" data-group-id="${hub.groupId}" data-search="${escapeHtml(hub.search)}">
            <div class="remodel-card-top">
              <button
                class="btn-save saved"
                type="button"
                data-save-toggle
                data-group-id="${hub.groupId}"
                aria-pressed="true"
                aria-label="Remove saved hub"
                title="Remove"
              >
                ${saveIcon(true)}
              </button>
              <span class="hub-kicker">Saved</span>
              <span class="hub-badge blue">${escapeHtml(savedLabel)}</span>
            </div>
            <h3>${escapeHtml(hub.name)}</h3>
            <p>${escapeHtml(hub.description)}</p>
            <div class="hub-meta">
              <span>${escapeHtml(hub.members)} members</span>
              <span>${escapeHtml(hub.work)} work items</span>
              <span>${escapeHtml(hub.category)}</span>
            </div>
            <div class="hub-card-actions">
              ${primaryAction}
            </div>
          </article>
        `;
      })
      .join("");

    savedCount.textContent = String(savedIds.length);
    if (savedToggleCount) {
      savedToggleCount.textContent = String(savedIds.length);
    }
    savedGrid.innerHTML = cards || `
      <article class="empty-lane-card" data-saved-empty>
        Save hubs you want to revisit later. Your shortlist will appear here when you open the panel.
      </article>
    `;
  };

  const setSavedPanelOpen = (open) => {
    if (!savedPanel) {
      return;
    }

    savedPanel.classList.toggle("is-open", open);
    savedPanel.setAttribute("aria-hidden", open ? "false" : "true");
    document.body.classList.toggle("saved-panel-open", open);

    savedToggleButtons.forEach((button) => {
      button.setAttribute("aria-expanded", open ? "true" : "false");
    });
  };

  const syncSaveButtons = () => {
    const catalog = getHubCatalog();
    const savedSet = new Set(getSavedIds());

    getSourceHubCards().forEach((card) => {
      const groupId = Number(card.dataset.groupId);
      const button = card.querySelector("[data-save-toggle]");
      if (!button || !Number.isFinite(groupId)) {
        return;
      }

      const hub = catalog.get(groupId);
      const isSaved = savedSet.has(groupId);
      button.classList.toggle("saved", isSaved);
      button.setAttribute("aria-pressed", isSaved ? "true" : "false");
      button.setAttribute("aria-label", isSaved ? "Remove saved hub" : "Save hub");
      button.title = isSaved ? "Remove" : "Save";
      button.innerHTML = saveIcon(isSaved);

      if (hub?.state === "joined") {
        button.title = isSaved ? "Remove this hub from your saved list" : "Save this joined hub";
      } else {
        button.title = isSaved ? "Remove this hub from your saved list" : "Save this hub for later";
      }
    });
  };

  const toggleSavedHub = (groupId) => {
    const catalog = getHubCatalog();
    if (!catalog.has(groupId)) {
      return;
    }

    const savedSet = new Set(getSavedIds());
    if (savedSet.has(groupId)) {
      savedSet.delete(groupId);
    } else {
      savedSet.add(groupId);
    }

    writeSavedIds(Array.from(savedSet));
    renderSavedLane();
    syncSaveButtons();
  };

  const applyFilters = () => {
    const query = (searchInput?.value || "").trim().toLowerCase();

    getSourceHubCards().forEach((card) => {
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
  document.addEventListener("click", (event) => {
    const panelToggle = event.target.closest("[data-saved-toggle]");
    if (panelToggle) {
      event.preventDefault();
      const willOpen = !savedPanel?.classList.contains("is-open");
      setSavedPanelOpen(willOpen);
      return;
    }

    if (savedBackdrop && event.target.closest("[data-saved-toggle-backdrop]")) {
      event.preventDefault();
      setSavedPanelOpen(false);
      return;
    }

    const button = event.target.closest("[data-save-toggle]");
    if (!button) {
      return;
    }

    const groupId = Number(button.dataset.groupId);
    if (!Number.isFinite(groupId) || groupId <= 0) {
      return;
    }

    event.preventDefault();
    toggleSavedHub(groupId);
  });

  const initCommunityBloom = () => {
    if (!canvas || typeof THREE === "undefined") {
      return;
    }

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const sizes = {
      width: window.innerWidth,
      height: window.innerHeight,
    };

    const scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x040713, 0.009);

    const camera = new THREE.PerspectiveCamera(40, sizes.width / sizes.height, 0.1, 120);
    camera.position.set(0, 6.1, 20);
    camera.lookAt(0, 0.5, -2);

    const renderer = new THREE.WebGLRenderer({
      canvas,
      alpha: true,
      antialias: true,
      powerPreference: "high-performance",
    });
    renderer.setSize(sizes.width, sizes.height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, prefersReducedMotion ? 1 : 1.45));

    const palette = {
      blue: new THREE.Color("rgb(111, 143, 216)"),
      blueSoft: new THREE.Color("rgb(149, 171, 235)"),
      red: new THREE.Color("rgb(255, 110, 69)"),
      redSoft: new THREE.Color("rgb(255, 142, 99)"),
      white: new THREE.Color("rgb(226, 232, 244)"),
      slate: new THREE.Color("rgb(16, 22, 38)"),
      navy: new THREE.Color("rgb(11, 18, 36)"),
    };

    scene.add(new THREE.AmbientLight(0xdbe5ff, 1.0));

    const keyLight = new THREE.DirectionalLight(0x9fb6ff, 1.2);
    keyLight.position.set(-8, 14, 16);
    scene.add(keyLight);

    const warmLight = new THREE.DirectionalLight(0xffa177, 1.05);
    warmLight.position.set(12, 10, 10);
    scene.add(warmLight);

    const backLight = new THREE.DirectionalLight(0x9fb2cf, 0.32);
    backLight.position.set(0, 18, -18);
    scene.add(backLight);

    const world = new THREE.Group();
    scene.add(world);

    const basic = (color, opacity = 1) => new THREE.MeshBasicMaterial({
      color,
      transparent: opacity < 1,
      opacity,
      blending: opacity < 1 ? THREE.AdditiveBlending : THREE.NormalBlending,
      depthWrite: opacity >= 0.9,
    });

    const standard = (color, opacity = 1, emissive = color) => new THREE.MeshStandardMaterial({
      color,
      transparent: opacity < 1,
      opacity,
      roughness: 0.32,
      metalness: 0.2,
      emissive,
      emissiveIntensity: 0.22,
    });

    const ringMaterial = (color, opacity) => new THREE.MeshBasicMaterial({
      color,
      transparent: true,
      opacity,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      side: THREE.DoubleSide,
    });

    const bloom = new THREE.Group();
    world.add(bloom);

    const centralCore = new THREE.Group();
    const coreGem = new THREE.Mesh(
      new THREE.DodecahedronGeometry(1.55, 1),
      standard(palette.slate, 0.96, palette.red)
    );
    centralCore.add(coreGem);

    const innerGem = new THREE.Mesh(
      new THREE.OctahedronGeometry(0.86, 1),
      standard(palette.blue, 0.92, palette.blue)
    );
    innerGem.rotation.set(0.35, 0.7, 0.15);
    centralCore.add(innerGem);

    const halo = new THREE.Mesh(
      new THREE.SphereGeometry(3.6, 40, 26),
      ringMaterial(palette.blueSoft, 0.14)
    );
    centralCore.add(halo);

    const ringA = new THREE.Mesh(
      new THREE.TorusGeometry(2.7, 0.055, 10, 128),
      ringMaterial(palette.red, 0.48)
    );
    ringA.rotation.set(Math.PI / 2.2, 0.2, 0.18);
    centralCore.add(ringA);

    const ringB = new THREE.Mesh(
      new THREE.TorusGeometry(3.4, 0.04, 10, 128),
      ringMaterial(palette.blueSoft, 0.24)
    );
    ringB.rotation.set(Math.PI / 2.05, 1.1, -0.32);
    centralCore.add(ringB);

    centralCore.position.set(0, 0.9, -1);
    bloom.add(centralCore);

    const memberNodes = [];
    const memberGroup = new THREE.Group();
    bloom.add(memberGroup);

    const avatarHeadGeometry = new THREE.SphereGeometry(0.22, 18, 14);
    const avatarBodyGeometry = new THREE.CapsuleGeometry(0.17, 0.24, 8, 14);
    const pedestalGeometry = new THREE.CylinderGeometry(0.45, 0.55, 0.16, 28);
    const pedestalRingGeometry = new THREE.TorusGeometry(0.43, 0.04, 8, 48);

    const memberPalette = [palette.blue, palette.red, palette.blueSoft, palette.redSoft, palette.slate, palette.navy];

    for (let index = 0; index < 12; index += 1) {
      const angle = (Math.PI * 2 * index) / 12 - Math.PI / 2;
      const radius = index % 3 === 0 ? 5.2 : index % 3 === 1 ? 6.4 : 7.6;
      const tint = memberPalette[index % memberPalette.length];
      const node = new THREE.Group();
      node.position.set(Math.cos(angle) * radius, -0.55 + (index % 2) * 0.3, Math.sin(angle) * radius - 0.8);

      const pedestal = new THREE.Mesh(pedestalGeometry, standard(palette.navy, 0.8, tint));
      pedestal.position.y = -0.36;
      node.add(pedestal);

      const pedestalRing = new THREE.Mesh(pedestalRingGeometry, ringMaterial(tint, 0.48));
      pedestalRing.rotation.x = Math.PI / 2;
      pedestalRing.position.y = -0.3;
      node.add(pedestalRing);

      const body = new THREE.Mesh(avatarBodyGeometry, standard(tint, 0.94, tint));
      body.position.y = -0.02;
      node.add(body);

      const head = new THREE.Mesh(avatarHeadGeometry, standard(palette.blueSoft, 0.9, tint));
      head.position.y = 0.3;
      node.add(head);

      const visor = new THREE.Mesh(
        new THREE.BoxGeometry(0.28, 0.04, 0.04),
        basic(index % 2 ? palette.red : palette.blue, 0.95)
      );
      visor.position.set(0, 0.3, 0.17);
      node.add(visor);

      const badge = new THREE.Mesh(
        new THREE.BoxGeometry(0.16, 0.08, 0.04),
        basic(index % 2 ? palette.blueSoft : palette.redSoft, 0.95)
      );
      badge.position.set(0.2, 0.02, 0.18);
      node.add(badge);

      const aura = new THREE.Mesh(
        new THREE.SphereGeometry(0.84, 20, 14),
        ringMaterial(tint, 0.12)
      );
      node.add(aura);

      memberGroup.add(node);
      memberNodes.push({ node, head, body, aura, angle, radius, speed: 0.12 + index * 0.01, tint });
    }

    const projectPanels = [];
    const panelGroup = new THREE.Group();
    panelGroup.position.set(0, 1.25, 1.5);
    bloom.add(panelGroup);

    const panelFrameGeometry = new THREE.BoxGeometry(1.65, 0.98, 0.08);
    const panelBodyGeometry = new THREE.BoxGeometry(1.45, 0.78, 0.12);
    const panelStripeGeometry = new THREE.BoxGeometry(1.15, 0.06, 0.12);
    const panelBarGeometry = new THREE.BoxGeometry(0.18, 0.52, 0.08);

    for (let index = 0; index < 4; index += 1) {
      const angle = (Math.PI * 2 * index) / 4 + Math.PI / 4;
      const radius = 9.2;
      const tint = index % 2 === 0 ? palette.blue : palette.red;
      const panel = new THREE.Group();
      panel.position.set(Math.cos(angle) * radius, 1.2 + (index % 2) * 0.8, Math.sin(angle) * radius - 1.2);
      panel.lookAt(0, 1.0, -1.5);

      const frame = new THREE.Mesh(panelFrameGeometry, standard(tint, 0.78, tint));
      frame.position.z = -0.02;
      panel.add(frame);

      const body = new THREE.Mesh(panelBodyGeometry, standard(palette.slate, 0.88, tint));
      panel.add(body);

      const frameLine = new THREE.Mesh(
        new THREE.BoxGeometry(1.45, 0.02, 0.08),
        basic(palette.blueSoft, 0.35)
      );
      frameLine.position.set(0, 0.18, 0.08);
      panel.add(frameLine);

      const header = new THREE.Mesh(panelStripeGeometry, basic(index % 2 ? palette.red : palette.blue, 0.96));
      header.position.set(0, 0.26, 0.08);
      panel.add(header);

      const lineOne = new THREE.Mesh(
        new THREE.BoxGeometry(1.0, 0.04, 0.08),
        basic(palette.navy, 0.72)
      );
      lineOne.position.set(-0.08, 0.02, 0.08);
      panel.add(lineOne);

      const lineTwo = new THREE.Mesh(
        new THREE.BoxGeometry(0.72, 0.04, 0.08),
        basic(index % 2 ? palette.blueSoft : palette.redSoft, 0.72)
      );
      lineTwo.position.set(-0.2, -0.12, 0.08);
      panel.add(lineTwo);

      const badgeA = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.12, 0.08), basic(palette.blue, 0.78));
      badgeA.position.set(0.42, -0.16, 0.08);
      panel.add(badgeA);

      const badgeB = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.12, 0.08), basic(palette.red, 0.78));
      badgeB.position.set(0.67, -0.16, 0.08);
      panel.add(badgeB);

      const sideBar = new THREE.Mesh(panelBarGeometry, basic(index % 2 ? palette.blueSoft : palette.redSoft, 0.72));
      sideBar.position.set(-0.64, -0.02, 0.08);
      panel.add(sideBar);

      panelGroup.add(panel);
      projectPanels.push({ panel, angle, radius, speed: 0.08 + index * 0.01, tint });
    }

    const crystals = [];
    const crystalGroup = new THREE.Group();
    crystalGroup.position.set(0, -0.1, -0.6);
    bloom.add(crystalGroup);

    const crystalGeometry = new THREE.OctahedronGeometry(0.34, 1);
    for (let index = 0; index < 6; index += 1) {
      const angle = (Math.PI * 2 * index) / 6 + Math.PI / 6;
      const radius = 4.6 + (index % 2) * 1.2;
      const tint = memberPalette[(index + 1) % memberPalette.length];
      const crystal = new THREE.Mesh(crystalGeometry, standard(tint, 0.9, tint));
      crystal.position.set(Math.cos(angle) * radius, 0.1 + (index % 2) * 0.6, Math.sin(angle) * radius);
      crystal.rotation.set(0.3, 0.7, 0.15);
      crystalGroup.add(crystal);
      crystals.push({ crystal, angle, radius, speed: 0.16 + index * 0.015 });
    }

    const ribbons = [];
    const ribbonFrom = memberNodes[0].node.position.clone();
    const ribbonTo = memberNodes[6].node.position.clone();
    const ribbonCurve = new THREE.CatmullRomCurve3([
      ribbonFrom,
      new THREE.Vector3(-1.4, 2.0, -0.2),
      new THREE.Vector3(1.2, 2.25, -0.8),
      ribbonTo,
    ]);
    const ribbon = new THREE.Mesh(
      new THREE.TubeGeometry(ribbonCurve, 56, 0.03, 10, false),
      ringMaterial(palette.redSoft, 0.34)
    );
    bloom.add(ribbon);
    ribbons.push({ mesh: ribbon, curve: ribbonCurve });

    const secondaryRibbonCurve = new THREE.CatmullRomCurve3([
      memberNodes[3].node.position.clone(),
      new THREE.Vector3(0.4, 3.0, 0.4),
      memberNodes[9].node.position.clone(),
    ]);
    const secondaryRibbon = new THREE.Mesh(
      new THREE.TubeGeometry(secondaryRibbonCurve, 44, 0.022, 8, false),
      ringMaterial(palette.blueSoft, 0.22)
    );
    bloom.add(secondaryRibbon);
    ribbons.push({ mesh: secondaryRibbon, curve: secondaryRibbonCurve });

    const sparkGeometry = new THREE.SphereGeometry(0.11, 10, 8);
    const sparks = [];
    for (let index = 0; index < 8; index += 1) {
      const tint = memberPalette[index % memberPalette.length];
      const spark = new THREE.Mesh(sparkGeometry, basic(tint, 0.92));
      bloom.add(spark);
      sparks.push({
        spark,
        curve: index % 2 === 0 ? ribbonCurve : secondaryRibbonCurve,
        offset: index / 8,
        speed: 0.08 + index * 0.01,
      });
    }

    const backdropPoints = [];
    const backdropColors = [];
    const starCount = prefersReducedMotion ? 70 : 120;
    for (let index = 0; index < starCount; index += 1) {
      const x = (Math.random() - 0.5) * 34;
      const y = (Math.random() - 0.5) * 20 + 2;
      const z = -12 - Math.random() * 16;
      backdropPoints.push(x, y, z);
      const c = memberPalette[index % memberPalette.length];
      backdropColors.push(c.r, c.g, c.b);
    }
    const backdropGeometry = new THREE.BufferGeometry();
    backdropGeometry.setAttribute("position", new THREE.Float32BufferAttribute(backdropPoints, 3));
    backdropGeometry.setAttribute("color", new THREE.Float32BufferAttribute(backdropColors, 3));
    const backdrop = new THREE.Points(
      backdropGeometry,
      new THREE.PointsMaterial({
        size: 0.065,
        transparent: true,
        opacity: 0.55,
        vertexColors: true,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
      })
    );
    scene.add(backdrop);

    world.position.set(0.8, 0.4, 0);
    world.rotation.x = -0.06;
    world.rotation.z = -0.01;
    world.scale.setScalar(sizes.width < 900 ? 0.9 : 1.08);

    let targetPointerX = 0;
    let targetPointerY = 0;
    let pointerX = 0;
    let pointerY = 0;
    let targetScroll = 0;
    let scroll = 0;

    const updateScroll = () => {
      const maxScroll = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      targetScroll = Math.max(0, Math.min(1, window.scrollY / maxScroll));
    };

    updateScroll();
    window.addEventListener("scroll", updateScroll, { passive: true });
    window.addEventListener("pointermove", (event) => {
      targetPointerX = (event.clientX / sizes.width - 0.5) * 2;
      targetPointerY = (event.clientY / sizes.height - 0.5) * 2;
    }, { passive: true });

    let lastFrame = 0;
    const frameStep = prefersReducedMotion ? 1000 / 24 : 1000 / 36;
    let renderedFrames = 0;

    const animate = (time) => {
      window.requestAnimationFrame(animate);

      if (document.hidden || time - lastFrame < frameStep) {
        return;
      }

      lastFrame = time;
      const seconds = time * 0.001;
      pointerX += (targetPointerX - pointerX) * 0.045;
      pointerY += (targetPointerY - pointerY) * 0.045;
      scroll += (targetScroll - scroll) * 0.08;

      world.rotation.y = pointerX * 0.08 - scroll * 0.08;
      world.rotation.x = -0.06 - pointerY * 0.03 + scroll * 0.02;
      world.position.y = 0.4 + scroll * 1.1;

      centralCore.rotation.x = seconds * 0.25;
      centralCore.rotation.y = seconds * 0.36;
      halo.scale.setScalar(1 + Math.sin(seconds * 1.1) * 0.04);
      ringA.rotation.z = seconds * 0.16;
      ringB.rotation.z = -seconds * 0.12;

      memberNodes.forEach((item, index) => {
        const angle = item.angle + seconds * item.speed;
        item.node.position.x = Math.cos(angle) * item.radius;
        item.node.position.z = Math.sin(angle) * item.radius - 0.8;
        item.node.position.y = -0.55 + (index % 2) * 0.3 + Math.sin(seconds * 0.8 + index) * 0.08;
        item.head.rotation.y = seconds * 0.28 + index * 0.12;
        item.body.rotation.y = seconds * 0.2 + index * 0.08;
        item.aura.scale.setScalar(1 + Math.sin(seconds * 1.4 + index) * 0.05);
      });

      projectPanels.forEach((item, index) => {
        const angle = item.angle + seconds * item.speed;
        item.panel.position.x = Math.cos(angle) * item.radius;
        item.panel.position.z = Math.sin(angle) * item.radius - 1.2;
        item.panel.position.y = 1.2 + (index % 2) * 0.8 + Math.sin(seconds * 0.6 + index) * 0.1;
        item.panel.rotation.y = Math.atan2(-item.panel.position.x, -item.panel.position.z);
      });

      crystals.forEach((item, index) => {
        const angle = item.angle + seconds * item.speed;
        item.crystal.position.x = Math.cos(angle) * item.radius;
        item.crystal.position.z = Math.sin(angle) * item.radius;
        item.crystal.rotation.x = 0.3 + Math.sin(seconds * 0.8 + index) * 0.2;
        item.crystal.rotation.y = seconds * 0.5 + index * 0.1;
      });

      ribbons.forEach(({ mesh }) => {
        mesh.material.opacity = 0.22 + Math.sin(seconds * 0.7) * 0.05;
      });

      sparks.forEach(({ spark, curve, offset, speed }, index) => {
        const progress = (seconds * speed + offset) % 1;
        spark.position.copy(curve.getPointAt(progress));
        spark.scale.setScalar(0.8 + Math.sin(seconds * 5 + index) * 0.18);
      });

      renderer.render(scene, camera);
      renderedFrames += 1;
      if (renderedFrames === 3) {
        canvas.dataset.scene = "ready";
      }
    };

    canvas.dataset.scene = "booting";
    requestAnimationFrame(animate);

    window.addEventListener("resize", () => {
      sizes.width = window.innerWidth;
      sizes.height = window.innerHeight;
      camera.aspect = sizes.width / sizes.height;
      camera.updateProjectionMatrix();
      renderer.setSize(sizes.width, sizes.height);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, prefersReducedMotion ? 1 : 1.45));
      world.scale.setScalar(sizes.width < 900 ? 0.9 : 1.08);
    });
  };

  initCommunityBloom();
  renderSavedLane();
  syncSaveButtons();
  setSavedPanelOpen(false);
  applyFilters();
});
