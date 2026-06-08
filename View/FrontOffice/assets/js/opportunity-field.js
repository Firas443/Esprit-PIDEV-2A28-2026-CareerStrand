(() => {
  const canvas = document.querySelector(".opportunity-canvas");
  if (!canvas || typeof THREE === "undefined") return;

  // Tune these values later to shape the scene without touching the animation logic.
  const OPPORTUNITY_BG = {
    cameraZ: 44,
    cameraY: 8.4,
    worldScale: 1.08,
    skylineOffsetX: -20,
    skylineOffsetY: -8.0,
    skylineOffsetZ: -24,
    scrollLift: 3.6,
    scrollDepth: 2.4
  };

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (prefersReducedMotion) document.body.classList.add("low-motion");

  const scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x030713, 0.015);

  const sizes = { width: window.innerWidth, height: window.innerHeight };
  const camera = new THREE.PerspectiveCamera(44, sizes.width / sizes.height, 0.1, 220);
  camera.position.set(0, OPPORTUNITY_BG.cameraY, OPPORTUNITY_BG.cameraZ);
  camera.lookAt(0, 2, 0);
  scene.add(camera);

  const renderer = new THREE.WebGLRenderer({
    canvas,
    alpha: true,
    antialias: true,
    powerPreference: "high-performance"
  });
  renderer.setSize(sizes.width, sizes.height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, prefersReducedMotion ? 1 : 1.6));

  const ambient = new THREE.AmbientLight(0xd4ddff, 1.15);
  scene.add(ambient);

  const fill = new THREE.DirectionalLight(0xffd9a8, 1.45);
  fill.position.set(-10, 20, 22);
  scene.add(fill);

  const rim = new THREE.DirectionalLight(0x95abeb, 1.1);
  rim.position.set(16, 12, -18);
  scene.add(rim);

  const glow = new THREE.Mesh(
    new THREE.SphereGeometry(26, 42, 30),
    new THREE.MeshBasicMaterial({
      color: 0xffb66d,
      transparent: true,
      opacity: 0.1,
      blending: THREE.AdditiveBlending
    })
  );
  glow.position.set(11, 8, -30);
  scene.add(glow);

  const glowBlue = new THREE.Mesh(
    new THREE.SphereGeometry(34, 42, 30),
    new THREE.MeshBasicMaterial({
      color: 0x6f8fd8,
      transparent: true,
      opacity: 0.12,
      blending: THREE.AdditiveBlending
    })
  );
  glowBlue.position.set(-14, 10, -36);
  scene.add(glowBlue);

  const palette = {
    blue: new THREE.Color("rgb(111, 143, 216)"),
    blueSoft: new THREE.Color("rgb(149, 171, 235)"),
    flame: new THREE.Color("rgb(255, 110, 69)"),
    gold: new THREE.Color("rgb(245, 191, 101)"),
    green: new THREE.Color("rgb(89, 211, 155)"),
    ink: new THREE.Color("rgb(19, 25, 43)")
  };

  const world = new THREE.Group();
  scene.add(world);
  world.scale.setScalar(OPPORTUNITY_BG.worldScale);
  world.rotation.x = -0.04;
  world.rotation.y = 0.02;
  world.rotation.z = -0.02;

  const skyline = new THREE.Group();
  skyline.position.set(
    OPPORTUNITY_BG.skylineOffsetX,
    OPPORTUNITY_BG.skylineOffsetY,
    OPPORTUNITY_BG.skylineOffsetZ
  );
  world.add(skyline);

  const buildingGeometry = new THREE.BoxGeometry(1, 1, 1);
  const buildingColors = [palette.ink, new THREE.Color("rgb(18, 28, 56)"), new THREE.Color("rgb(22, 36, 71)")];
  const windowColors = [palette.blueSoft, palette.gold, palette.flame, palette.green];
  const buildings = [];

  for (let i = 0; i < 18; i += 1) {
    const height = 2.5 + Math.random() * 8.5;
    const width = 1.2 + Math.random() * 1.8;
    const depth = 1.5 + Math.random() * 1.5;
    const x = i * 2.55 + (i % 2 === 0 ? 0.2 : -0.25);
    const material = new THREE.MeshStandardMaterial({
      color: buildingColors[i % buildingColors.length],
      roughness: 0.9,
      metalness: 0.05,
      emissive: i % 3 === 0 ? palette.blue : new THREE.Color(0x000000),
      emissiveIntensity: i % 3 === 0 ? 0.08 : 0
    });

    const building = new THREE.Mesh(buildingGeometry, material);
    building.scale.set(width * 1.08, height * 1.16, depth * 1.08);
    building.position.set(x, height / 2, (i % 3) * 0.15);
    skyline.add(building);
    buildings.push({
      mesh: building,
      baseScale: { x: width, y: height, z: depth },
      phase: Math.random() * Math.PI * 2,
      speed: 0.28 + Math.random() * 0.18
    });

    const crown = new THREE.Mesh(
      new THREE.BoxGeometry(width * 0.52, 0.26, depth * 1.12),
      new THREE.MeshStandardMaterial({
        color: palette.flame,
        emissive: palette.flame,
        emissiveIntensity: 0.22,
        roughness: 0.4,
        metalness: 0.1,
        transparent: true,
        opacity: 0.55
      })
    );
    crown.position.set(x, height * 1.16 + 0.14, (i % 3) * 0.15);
    skyline.add(crown);
  }

  const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(100, 100),
    new THREE.MeshBasicMaterial({
      color: 0x050a16,
      transparent: true,
      opacity: 0.9,
      side: THREE.DoubleSide
    })
  );
  floor.rotation.x = -Math.PI / 2;
  floor.position.y = -10.2;
  world.add(floor);

  const laneGroup = new THREE.Group();
  world.add(laneGroup);
  const laneCount = 9;
  const laneSegments = 30;
  const lanePositions = [];
  const laneColors = [];
  for (let lane = 0; lane < laneCount; lane += 1) {
    const y = -6.2 + lane * 2.0;
    const baseColor = lane % 3 === 0 ? palette.blueSoft : lane % 3 === 1 ? palette.gold : palette.flame;
    for (let segment = 0; segment < laneSegments; segment += 1) {
      const x1 = -34 + segment * 2.45;
      const x2 = x1 + 1.8;
      const z1 = Math.sin(segment * 0.24 + lane * 0.55) * 1.9;
      const z2 = Math.sin((segment + 1) * 0.24 + lane * 0.55) * 1.9;
      lanePositions.push(x1, y + Math.sin(segment * 0.14 + lane) * 0.22, z1);
      lanePositions.push(x2, y + Math.sin((segment + 1) * 0.14 + lane) * 0.22, z2);
      laneColors.push(baseColor.r, baseColor.g, baseColor.b, baseColor.r, baseColor.g, baseColor.b);
    }
  }
  const laneGeometry = new THREE.BufferGeometry();
  laneGeometry.setAttribute("position", new THREE.Float32BufferAttribute(lanePositions, 3));
  laneGeometry.setAttribute("color", new THREE.Float32BufferAttribute(laneColors, 3));
  const laneMaterial = new THREE.LineBasicMaterial({
    vertexColors: true,
    transparent: true,
    opacity: 0.5,
    blending: THREE.AdditiveBlending
  });
  laneGroup.add(new THREE.LineSegments(laneGeometry, laneMaterial));

  const pathGeometry = new THREE.BufferGeometry();
  const pathPositions = new Float32Array(240);
  const pathColors = new Float32Array(240);
  const pathPairs = 40;
  for (let i = 0; i < pathPairs; i += 1) {
    const x = -28 + (i % 10) * 5.2;
    const y = -4.8 + Math.floor(i / 10) * 3.5;
    const z = -3 + ((i % 5) - 2) * 0.9;
    const idx = i * 6;
    pathPositions[idx] = x;
    pathPositions[idx + 1] = y;
    pathPositions[idx + 2] = z;
    pathPositions[idx + 3] = x + 3.35;
    pathPositions[idx + 4] = y + 1.9;
    pathPositions[idx + 5] = z + Math.sin(i * 0.7) * 1.05;
    const c = i % 3 === 0 ? palette.green : i % 3 === 1 ? palette.gold : palette.blue;
    pathColors[idx] = c.r;
    pathColors[idx + 1] = c.g;
    pathColors[idx + 2] = c.b;
    pathColors[idx + 3] = c.r;
    pathColors[idx + 4] = c.g;
    pathColors[idx + 5] = c.b;
  }
  pathGeometry.setAttribute("position", new THREE.BufferAttribute(pathPositions, 3));
  pathGeometry.setAttribute("color", new THREE.BufferAttribute(pathColors, 3));
  const network = new THREE.LineSegments(
    pathGeometry,
    new THREE.LineBasicMaterial({
      vertexColors: true,
      transparent: true,
      opacity: 0.56,
      blending: THREE.AdditiveBlending
    })
  );
  world.add(network);

  const jobs = new THREE.Group();
  jobs.position.set(5.5, 1.5, 6);
  world.add(jobs);

  const cardMaterial = new THREE.MeshStandardMaterial({
    color: 0x10182d,
    roughness: 0.55,
    metalness: 0.06,
    transparent: true,
    opacity: 0.93
  });
  const cardEdgeMaterial = new THREE.MeshStandardMaterial({
    color: 0xfff4db,
    emissive: palette.gold,
    emissiveIntensity: 0.16,
    roughness: 0.35,
    metalness: 0.2
  });
  const lineMaterial = new THREE.MeshBasicMaterial({
    color: palette.blueSoft,
    transparent: true,
    opacity: 0.55
  });

  const jobCards = [];
  for (let i = 0; i < 4; i += 1) {
    const card = new THREE.Group();
    const board = new THREE.Mesh(new THREE.BoxGeometry(5.6, 3.3, 0.18), cardMaterial);
    const edge = new THREE.Mesh(new THREE.BoxGeometry(5.75, 0.1, 0.22), cardEdgeMaterial);
    edge.position.y = 1.39;
    const bar = new THREE.Mesh(new THREE.BoxGeometry(3.9, 0.13, 0.2), lineMaterial);
    bar.position.set(-0.2, 0.82, 0.12);
    const bar2 = new THREE.Mesh(new THREE.BoxGeometry(2.9, 0.1, 0.2), lineMaterial);
    bar2.position.set(-0.35, 0.32, 0.12);
    const dotA = new THREE.Mesh(new THREE.SphereGeometry(0.17, 12, 10), new THREE.MeshBasicMaterial({ color: palette.flame }));
    dotA.position.set(2.02, 0.88, 0.12);
    const dotB = new THREE.Mesh(new THREE.SphereGeometry(0.17, 12, 10), new THREE.MeshBasicMaterial({ color: palette.green }));
    dotB.position.set(1.65, 0.88, 0.12);

    card.add(board, edge, bar, bar2, dotA, dotB);
    card.position.set(-10.8 + i * 4.3, 1.4 + (i % 2) * 0.95, i % 2 === 0 ? 3.3 : -2.4);
    card.rotation.y = i % 2 === 0 ? -0.42 : 0.34;
    card.rotation.z = i % 2 === 0 ? 0.06 : -0.05;
    jobs.add(card);
    jobCards.push({ card, speed: 0.4 + Math.random() * 0.2, wave: Math.random() * Math.PI * 2 });
  }

  const briefcase = new THREE.Group();
  briefcase.position.set(18, -1.2, 8);
  world.add(briefcase);

  const caseBody = new THREE.Mesh(
    new THREE.BoxGeometry(6.4, 3.8, 2.6),
    new THREE.MeshStandardMaterial({
      color: 0x10182b,
      roughness: 0.5,
      metalness: 0.18,
      emissive: new THREE.Color(0x071126),
      emissiveIntensity: 0.2
    })
  );
  briefcase.add(caseBody);

  const caseLatch = new THREE.Mesh(
    new THREE.BoxGeometry(0.82, 0.3, 0.2),
    new THREE.MeshStandardMaterial({ color: palette.gold, roughness: 0.3, metalness: 0.9 })
  );
  caseLatch.position.set(0, 0.02, 1.12);
  briefcase.add(caseLatch);

  const handle = new THREE.Mesh(
    new THREE.TorusGeometry(1.18, 0.19, 12, 24, Math.PI),
    new THREE.MeshStandardMaterial({
      color: palette.flame,
      roughness: 0.45,
      metalness: 0.25,
      emissive: palette.flame,
      emissiveIntensity: 0.1
    })
  );
  handle.rotation.x = Math.PI / 2;
  handle.position.set(0, 2.48, 0.2);
  briefcase.add(handle);

  const pulseGeometry = new THREE.SphereGeometry(0.22, 14, 12);
  const pulses = [];
  const pulseColors = [palette.blueSoft, palette.gold, palette.green, palette.flame];
  for (let i = 0; i < 9; i += 1) {
    const pulse = new THREE.Mesh(
      pulseGeometry,
      new THREE.MeshBasicMaterial({
        color: pulseColors[i % pulseColors.length],
        transparent: true,
        opacity: 0.72
      })
    );
    pulse.position.set(-20 + i * 5.0, -1.0 + (i % 3) * 1.0, 2.5 + Math.sin(i * 0.8) * 2.0);
    briefcase.add(pulse);
    pulses.push({ pulse, radius: 1.2 + (i % 3) * 0.42, speed: 0.6 + i * 0.08, lift: 0.3 + i * 0.06 });
  }

  const resumeStacks = new THREE.Group();
  resumeStacks.position.set(-22, 4.2, 7);
  world.add(resumeStacks);

  const sheetGeometry = new THREE.PlaneGeometry(3.4, 4.6);
  const sheetMaterials = [
    new THREE.MeshBasicMaterial({ color: 0xf5f3ee, transparent: true, opacity: 0.9, side: THREE.DoubleSide }),
    new THREE.MeshBasicMaterial({ color: 0xfff1d3, transparent: true, opacity: 0.82, side: THREE.DoubleSide }),
    new THREE.MeshBasicMaterial({ color: 0xdfe8ff, transparent: true, opacity: 0.78, side: THREE.DoubleSide })
  ];

  const sheets = [];
  for (let i = 0; i < 3; i += 1) {
    const sheet = new THREE.Mesh(sheetGeometry, sheetMaterials[i]);
    sheet.position.set(i * 0.42, -i * 0.24, i * 0.3);
    sheet.rotation.z = -0.08 + i * 0.02;
    sheet.rotation.y = 0.1 - i * 0.03;
    resumeStacks.add(sheet);
    sheets.push({ sheet, bob: 0.3 + i * 0.08, sway: 0.4 + i * 0.09 });
  }

  const sheetLines = new THREE.Group();
  resumeStacks.add(sheetLines);
  for (let i = 0; i < 5; i += 1) {
    const line = new THREE.Mesh(
      new THREE.BoxGeometry(2.4 - i * 0.16, 0.08, 0.04),
      new THREE.MeshBasicMaterial({ color: i === 0 ? palette.flame : palette.blue })
    );
    line.position.set(-0.18, 1.12 - i * 0.46, 0.05);
    sheetLines.add(line);
  }

  const matchingDots = new THREE.Group();
  matchingDots.position.set(0, 0, 0);
  world.add(matchingDots);
  const dotGeometry = new THREE.SphereGeometry(0.14, 12, 10);
  const dotMaterial = new THREE.MeshBasicMaterial({ color: palette.gold, transparent: true, opacity: 0.9 });
  const dots = [];
  for (let i = 0; i < 14; i += 1) {
    const dot = new THREE.Mesh(dotGeometry, dotMaterial.clone());
    const phase = i / 14 * Math.PI * 2;
    dot.position.set(Math.cos(phase) * 15, 1.2 + Math.sin(phase * 1.3) * 4.8, Math.sin(phase) * 9);
    matchingDots.add(dot);
    dots.push({ dot, phase, radiusX: 12 + (i % 3) * 0.8, radiusZ: 7 + (i % 2) * 0.6, speed: 0.5 + i * 0.04 });
  }

  let targetScroll = 0;
  let scroll = 0;
  const updateScroll = () => {
    const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    targetScroll = Math.max(0, Math.min(1, window.scrollY / max));
  };
  updateScroll();
  window.addEventListener("scroll", updateScroll, { passive: true });

  let lastFrame = 0;
  const frameStep = prefersReducedMotion ? 1000 / 24 : 1000 / 38;

  function animate(time) {
    requestAnimationFrame(animate);
    if (document.hidden || time - lastFrame < frameStep) return;
    lastFrame = time;

    const t = time * 0.001;
    scroll += (targetScroll - scroll) * 0.08;

    const scrollLift = scroll * OPPORTUNITY_BG.scrollLift;
    const scrollDepth = scroll * OPPORTUNITY_BG.scrollDepth;
    camera.position.z = OPPORTUNITY_BG.cameraZ - scroll * 4.6;
    camera.position.y = OPPORTUNITY_BG.cameraY + scroll * 1.2;
    camera.lookAt(0, 2 + scrollLift * 0.12, -scrollDepth * 0.1);
    world.position.y = scrollLift;
    world.position.z = scrollDepth;
    world.rotation.y = Math.sin(t * 0.12) * 0.08;
    world.rotation.z = -0.02 + scroll * 0.03;

    buildings.forEach((item, index) => {
      const lift = Math.sin(t * item.speed + item.phase) * (prefersReducedMotion ? 0.05 : 0.18);
      item.mesh.scale.set(
        item.baseScale.x,
        item.baseScale.y * (1 + lift * 0.12),
        item.baseScale.z
      );
      item.mesh.rotation.z = Math.sin(t * 0.4 + index) * 0.006;
    });

    jobCards.forEach(({ card, speed, wave }, index) => {
      card.position.y = 1.2 + (index % 2) * 0.8 + Math.sin(t * speed + wave) * 0.28;
      card.rotation.z = (index % 2 === 0 ? 0.06 : -0.05) + Math.sin(t * 0.8 + wave) * 0.015;
      card.rotation.y = (index % 2 === 0 ? -0.36 : 0.28) + Math.sin(t * 0.7 + wave) * 0.08;
    });

    pulses.forEach(({ pulse, radius, speed, lift }, index) => {
      const angle = t * speed + index * 0.65;
      pulse.position.x = Math.cos(angle) * radius;
      pulse.position.y = 2.05 + Math.sin(angle * 1.2) * lift;
      pulse.position.z = Math.sin(angle) * radius * 0.45;
      pulse.scale.setScalar(0.85 + Math.sin(t * 1.6 + index) * 0.2);
    });

    sheets.forEach(({ sheet, bob, sway }, index) => {
      sheet.position.y = -index * 0.2 + Math.sin(t * bob + index) * 0.18;
      sheet.rotation.z = -0.08 + index * 0.02 + Math.sin(t * sway) * 0.03;
      sheet.rotation.y = 0.1 - index * 0.03 + Math.sin(t * (sway * 0.8)) * 0.04;
    });

    dots.forEach(({ dot, phase, radiusX, radiusZ, speed }) => {
      const angle = t * speed + phase;
      dot.position.x = Math.cos(angle) * radiusX;
      dot.position.y = 1.6 + Math.sin(angle * 1.4) * 5.1;
      dot.position.z = Math.sin(angle) * radiusZ;
      dot.material.opacity = 0.58 + Math.sin(angle * 2.2) * 0.26;
    });

    glow.position.x = 11 + Math.sin(t * 0.25) * 1.6;
    glow.position.y = 8 + Math.cos(t * 0.2) * 1.2;
    glowBlue.position.x = -14 + Math.cos(t * 0.22) * 1.8;
    glowBlue.position.y = 10 + Math.sin(t * 0.18) * 1.4;

    renderer.render(scene, camera);
  }

  requestAnimationFrame(animate);

  window.addEventListener("resize", () => {
    sizes.width = window.innerWidth;
    sizes.height = window.innerHeight;
    camera.aspect = sizes.width / sizes.height;
    camera.updateProjectionMatrix();
    renderer.setSize(sizes.width, sizes.height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, prefersReducedMotion ? 1 : 1.4));
  });
})();
