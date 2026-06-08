(function () {
  const canvas = document.querySelector(".webgl-neuron");

  if (!canvas || typeof THREE === "undefined") {
    return;
  }

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const sizes = {
    width: window.innerWidth,
    height: window.innerHeight
  };
  const motionSettings = {
    // Main knobs: increase for more energy, decrease for calmer movement.
    motionSpeed: 0.68,
    motionAggression: 0.68,
    cursorTwitchStrength: 0.62,
    cursorTwitchRange: 1.0,
    pulseSpeed: 0.78,
    signalDensity: 0.055,
    signalChance: 0.32,
    signalSpeed: 12.0,
    signalLength: 0.9,
    signalOpacity: 0.72
  };

  const scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x030611, 0.018);

  const camera = new THREE.PerspectiveCamera(52, sizes.width / sizes.height, 0.1, 120);
  camera.position.set(0, 0.6, 30);
  scene.add(camera);

  const renderer = new THREE.WebGLRenderer({
    canvas,
    alpha: true,
    antialias: true,
    powerPreference: "high-performance",
    preserveDrawingBuffer: false
  });
  renderer.setSize(sizes.width, sizes.height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, reducedMotion ? 1 : 1.25));

  const network = new THREE.Group();
  scene.add(network);

  const mobile = () => window.innerWidth < 760;
  const colors = {
    blue: new THREE.Color("rgb(111, 143, 216)"),
    blueSoft: new THREE.Color("rgb(157, 183, 255)"),
    blueDeep: new THREE.Color("rgb(56, 79, 145)"),
    orange: new THREE.Color("rgb(255, 110, 69)"),
    pearl: new THREE.Color("rgb(245, 243, 238)")
  };

  const nodeCount = reducedMotion ? 88 : 142;
  const nodes = [];
  const connectionPairs = [];
  const mouseLocal = new THREE.Vector3(999, 999, 999);
  const mouseWorld = new THREE.Vector3();
  const pointerRay = new THREE.Vector3();
  let mouseActive = false;
  let lastPointerMove = 0;

  function placeNetwork() {
    const isMobile = mobile();
    network.position.set(isMobile ? -0.45 : -1.35, isMobile ? -0.12 : -0.08, isMobile ? -8.9 : -9.2);
    network.scale.setScalar(isMobile ? 1.28 : 1.76);
    network.rotation.set(-0.07, isMobile ? -0.12 : -0.14, -0.025);
  }

  function makeNode(index) {
    const layer = index / nodeCount;
    const shell = Math.pow(layer, 0.72);
    const radius = 2.4 + shell * 12.6 + Math.sin(index * 0.47) * 0.7;
    const phi = Math.acos(1 - 2 * ((index + 0.5) / nodeCount));
    const theta = index * Math.PI * (3 - Math.sqrt(5));
    const offset = Math.sin(layer * Math.PI * 5);

    const origin = new THREE.Vector3(
        radius * Math.sin(phi) * Math.cos(theta) * 1.72 + offset * 1.18,
        radius * Math.sin(phi) * Math.sin(theta) * 1.08,
        radius * Math.cos(phi) * 0.42
    );
    const velocity = new THREE.Vector3(
      (Math.random() - 0.5) * 2.2 * motionSettings.motionAggression,
      (Math.random() - 0.5) * 1.55 * motionSettings.motionAggression,
      (Math.random() - 0.5) * 0.36 * motionSettings.motionAggression
    );

    return {
      origin,
      position: origin.clone(),
      target: origin.clone(),
      velocity,
      twitch: new THREE.Vector3(),
      driftLimit: (1.35 + Math.random() * 1.9) * motionSettings.motionAggression,
      aggression: (0.85 + Math.random() * 1.35) * motionSettings.motionAggression,
      baseSize: 0.58 + Math.random() * 0.98,
      phase: Math.random() * Math.PI * 2,
      phaseSpeed: (1.7 + Math.random() * 2.4) * motionSettings.motionSpeed,
      level: Math.floor(shell * 5),
      warmth: Math.random() > 0.87
    };
  }

  for (let i = 0; i < nodeCount; i++) {
    nodes.push(makeNode(i));
  }

  nodes.forEach((node, index) => {
    const nearest = nodes
      .map((candidate, candidateIndex) => ({
        candidateIndex,
        distance: node.position.distanceTo(candidate.position)
      }))
      .filter(item => item.candidateIndex !== index && item.distance < 6.45)
      .sort((a, b) => a.distance - b.distance)
      .slice(0, index % 5 === 0 ? 4 : 3);

    nearest.forEach(({ candidateIndex, distance }) => {
      const key = index < candidateIndex ? `${index}-${candidateIndex}` : `${candidateIndex}-${index}`;

      if (!connectionPairs.some(pair => pair.key === key)) {
        connectionPairs.push({
          key,
          a: index,
          b: candidateIndex,
          strength: Math.max(0.12, 1 - distance / 6.45),
          warmth: nodes[index].warmth || nodes[candidateIndex].warmth
        });
      }
    });
  });

  const nodePositions = new Float32Array(nodeCount * 3);
  const nodeColors = new Float32Array(nodeCount * 3);
  const nodeSizes = new Float32Array(nodeCount);

  nodes.forEach((node, index) => {
    const color = node.warmth ? colors.orange : (index % 7 === 0 ? colors.pearl : colors.blueSoft);

    nodePositions[index * 3] = node.position.x;
    nodePositions[index * 3 + 1] = node.position.y;
    nodePositions[index * 3 + 2] = node.position.z;
    nodeColors[index * 3] = color.r;
    nodeColors[index * 3 + 1] = color.g;
    nodeColors[index * 3 + 2] = color.b;
    nodeSizes[index] = node.baseSize;
  });

  const nodeGeometry = new THREE.BufferGeometry();
  nodeGeometry.setAttribute("position", new THREE.BufferAttribute(nodePositions, 3));
  nodeGeometry.setAttribute("nodeColor", new THREE.BufferAttribute(nodeColors, 3));
  nodeGeometry.setAttribute("nodeSize", new THREE.BufferAttribute(nodeSizes, 1));

  const nodeMaterial = new THREE.ShaderMaterial({
    uniforms: {
      uTime: { value: 0 },
      uBaseSize: { value: reducedMotion ? 4.9 : 6.05 }
    },
    vertexShader: `
      attribute vec3 nodeColor;
      attribute float nodeSize;
      varying vec3 vColor;
      varying float vPulse;
      uniform float uTime;
      uniform float uBaseSize;

      void main() {
        vColor = nodeColor;
        vec3 p = position;
        float wave = sin(uTime * 0.55 + position.x * 0.21 + position.y * 0.12);
        p += normal * wave * 0.035;
        vPulse = 0.72 + wave * 0.18;
        vec4 mvPosition = modelViewMatrix * vec4(p, 1.0);
        gl_PointSize = nodeSize * uBaseSize * (260.0 / -mvPosition.z);
        gl_Position = projectionMatrix * mvPosition;
      }
    `,
    fragmentShader: `
      varying vec3 vColor;
      varying float vPulse;

      void main() {
        vec2 center = gl_PointCoord - 0.5;
        float dist = length(center);
        if (dist > 0.5) discard;
        float core = 1.0 - smoothstep(0.02, 0.2, dist);
        float halo = 1.0 - smoothstep(0.05, 0.5, dist);
        vec3 color = vColor * (0.8 + core * 0.65);
        float alpha = (core * 0.68 + halo * 0.3) * vPulse;
        gl_FragColor = vec4(color, alpha);
      }
    `,
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending
  });

  const nodeCloud = new THREE.Points(nodeGeometry, nodeMaterial);
  network.add(nodeCloud);

  const linePositions = new Float32Array(connectionPairs.length * 6);
  const lineColors = new Float32Array(connectionPairs.length * 6);

  connectionPairs.forEach((pair, index) => {
    const a = nodes[pair.a].position;
    const b = nodes[pair.b].position;
    const color = pair.warmth ? colors.orange.clone().lerp(colors.blue, 0.46) : colors.blueDeep.clone().lerp(colors.blueSoft, pair.strength);
    const intensity = pair.warmth ? 0.34 : 0.18 + pair.strength * 0.28;
    const pi = index * 6;

    linePositions[pi] = a.x;
    linePositions[pi + 1] = a.y;
    linePositions[pi + 2] = a.z;
    linePositions[pi + 3] = b.x;
    linePositions[pi + 4] = b.y;
    linePositions[pi + 5] = b.z;

    lineColors[pi] = color.r * intensity;
    lineColors[pi + 1] = color.g * intensity;
    lineColors[pi + 2] = color.b * intensity;
    lineColors[pi + 3] = color.r * intensity * 0.86;
    lineColors[pi + 4] = color.g * intensity * 0.86;
    lineColors[pi + 5] = color.b * intensity * 0.86;
  });

  const lineGeometry = new THREE.BufferGeometry();
  lineGeometry.setAttribute("position", new THREE.BufferAttribute(linePositions, 3));
  lineGeometry.setAttribute("color", new THREE.BufferAttribute(lineColors, 3));

  const lines = new THREE.LineSegments(
    lineGeometry,
    new THREE.LineBasicMaterial({
      transparent: true,
      opacity: 0.62,
      vertexColors: true,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    })
  );
  network.add(lines);

  const maxSignals = reducedMotion ? 18 : 56;
  const signals = [];
  const signalPositions = new Float32Array(maxSignals * 6);
  const signalColors = new Float32Array(maxSignals * 6);
  const signalGeometry = new THREE.BufferGeometry();
  signalGeometry.setAttribute("position", new THREE.BufferAttribute(signalPositions, 3).setUsage(THREE.DynamicDrawUsage));
  signalGeometry.setAttribute("color", new THREE.BufferAttribute(signalColors, 3).setUsage(THREE.DynamicDrawUsage));
  signalGeometry.setDrawRange(0, 0);

  const signalLines = new THREE.LineSegments(
    signalGeometry,
    new THREE.LineBasicMaterial({
      transparent: true,
      opacity: motionSettings.signalOpacity,
      vertexColors: true,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    })
  );
  network.add(signalLines);

  const pulseGeometry = new THREE.SphereGeometry(0.09, 12, 8);
  const pulses = [];
  const warmConnections = connectionPairs.filter(pair => pair.warmth);
  const pulseSource = warmConnections.length ? warmConnections : connectionPairs;

  for (let i = 0; i < (reducedMotion ? 2 : 5); i++) {
    const pulse = new THREE.Mesh(
      pulseGeometry,
      new THREE.MeshBasicMaterial({
        color: colors.orange,
        transparent: true,
        opacity: 0.0,
        blending: THREE.AdditiveBlending,
        depthWrite: false
      })
    );
    pulse.userData.offset = i / 5;
    pulse.userData.connection = pulseSource[Math.floor((pulseSource.length / 5) * i) % pulseSource.length];
    network.add(pulse);
    pulses.push(pulse);
  }

  const dustGeometry = new THREE.BufferGeometry();
  const dustCount = reducedMotion ? 64 : 108;
  const dustPositions = new Float32Array(dustCount * 3);

  for (let i = 0; i < dustCount; i++) {
    dustPositions[i * 3] = -18 + Math.random() * 36;
    dustPositions[i * 3 + 1] = -10 + Math.random() * 20;
    dustPositions[i * 3 + 2] = -9 - Math.random() * 24;
  }

  dustGeometry.setAttribute("position", new THREE.BufferAttribute(dustPositions, 3));
  scene.add(new THREE.Points(
    dustGeometry,
    new THREE.PointsMaterial({
      color: colors.blueSoft,
      size: 0.035,
      transparent: true,
      opacity: 0.14,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    })
  ));

  placeNetwork();

  function updatePointer(event) {
    const x = (event.clientX / sizes.width) * 2 - 1;
    const y = -(event.clientY / sizes.height) * 2 + 1;

    pointerRay.set(x, y, 0.5).unproject(camera).sub(camera.position).normalize();
    const distance = (network.position.z - camera.position.z) / pointerRay.z;
    mouseWorld.copy(camera.position).add(pointerRay.multiplyScalar(distance));
    network.worldToLocal(mouseLocal.copy(mouseWorld));
    mouseActive = true;
    lastPointerMove = getTime();
  }

  window.addEventListener("pointermove", updatePointer, { passive: true });

  let renderedFrames = 0;
  let lastFrame = 0;
  const frameStep = reducedMotion ? 1000 / 20 : 1000 / 34;
  const getTime = () => (window.performance && typeof window.performance.now === "function")
    ? window.performance.now()
    : Date.now();
  const nextFrame = typeof window.requestAnimationFrame === "function"
    ? window.requestAnimationFrame.bind(window)
    : (typeof window.setTimeout === "function"
      ? (callback) => window.setTimeout(() => callback(getTime()), frameStep)
      : null);

  function samplePixels() {
    const gl = renderer.getContext();
    const sample = new Uint8Array(4);
    let litPixels = 0;

    for (let y = 1; y < 26; y++) {
      for (let x = 1; x < 26; x++) {
        gl.readPixels(
          Math.floor(gl.drawingBufferWidth * (x / 26)),
          Math.floor(gl.drawingBufferHeight * (y / 26)),
          1,
          1,
          gl.RGBA,
          gl.UNSIGNED_BYTE,
          sample
        );

        if (sample[0] + sample[1] + sample[2] + sample[3] > 0) {
          litPixels++;
        }
      }
    }

    return litPixels;
  }

  function animate(time) {
    if (nextFrame) {
      nextFrame(animate);
    }

    if (renderedFrames > 0 && (document.hidden || time - lastFrame < frameStep)) {
      return;
    }

    const deltaSeconds = Math.min(0.055, Math.max(0.016, (time - lastFrame) / 1000 || 0.016));
    lastFrame = time;
    const t = time * 0.001;

    nodeMaterial.uniforms.uTime.value = t;
    network.rotation.y = (mobile() ? -0.12 : -0.14) + Math.sin(t * 0.08) * 0.055;
    network.rotation.x = -0.07 + Math.cos(t * 0.07) * 0.026;

    if (mouseActive && time - lastPointerMove > 1600) {
      mouseActive = false;
      mouseLocal.set(999, 999, 999);
    }

    nodes.forEach((node, index) => {
      node.target.set(
        Math.sin(t * node.phaseSpeed + node.phase) * 0.85,
        Math.cos(t * (node.phaseSpeed * 1.16) + node.phase * 0.7) * 0.72,
        Math.sin(t * (node.phaseSpeed * 0.7) + node.origin.x) * 0.18
      ).multiplyScalar(node.aggression);

      node.velocity.x += Math.sin(t * 3.1 * motionSettings.motionSpeed + index) * 0.03 * motionSettings.motionAggression;
      node.velocity.y += Math.cos(t * 2.8 * motionSettings.motionSpeed + index * 0.61) * 0.025 * motionSettings.motionAggression;
      node.twitch.multiplyScalar(0.86);

      if (mouseActive) {
        const dx = node.position.x - mouseLocal.x;
        const dy = node.position.y - mouseLocal.y;
        const distanceToMouse = Math.sqrt(dx * dx + dy * dy);
        const twitchRange = (mobile() ? 5.8 : 7.4) * motionSettings.cursorTwitchRange;

        if (distanceToMouse < twitchRange) {
          const force = Math.pow(1 - distanceToMouse / twitchRange, 2) * (reducedMotion ? 1.0 : 2.45) * motionSettings.cursorTwitchStrength;
          const angle = Math.atan2(dy, dx) + Math.sin(t * 18 * motionSettings.motionSpeed + index) * 1.7;
          node.twitch.x += Math.cos(angle) * force * 0.5;
          node.twitch.y += Math.sin(angle) * force * 0.5;
          node.twitch.z += Math.sin(t * 16 * motionSettings.motionSpeed + index) * force * 0.12;
        }
      }

      node.target
        .add(node.origin)
        .addScaledVector(node.velocity, reducedMotion ? 0.16 : 0.34)
        .add(node.twitch);
      node.target.sub(node.origin);
      const maxOffset = reducedMotion ? node.driftLimit * 0.42 : node.driftLimit;

      if (node.target.length() > maxOffset) {
        node.target.setLength(maxOffset);
        node.velocity.multiplyScalar(-0.72);
      }

      node.target.add(node.origin);
      node.position.lerp(node.target, reducedMotion ? 0.045 : 0.13);
      nodePositions[index * 3] = node.position.x;
      nodePositions[index * 3 + 1] = node.position.y;
      nodePositions[index * 3 + 2] = node.position.z;
    });

    nodeGeometry.attributes.position.needsUpdate = true;

    connectionPairs.forEach((pair, index) => {
      const a = nodes[pair.a].position;
      const b = nodes[pair.b].position;
      const pi = index * 6;

      linePositions[pi] = a.x;
      linePositions[pi + 1] = a.y;
      linePositions[pi + 2] = a.z;
      linePositions[pi + 3] = b.x;
      linePositions[pi + 4] = b.y;
      linePositions[pi + 5] = b.z;
    });

    lineGeometry.attributes.position.needsUpdate = true;

    const spawnAttempts = reducedMotion
      ? 0
      : Math.max(1, Math.round(connectionPairs.length * motionSettings.signalDensity * deltaSeconds));

    for (let i = 0; i < spawnAttempts && signals.length < maxSignals; i++) {
      if (Math.random() < motionSettings.signalChance) {
        const pairIndex = Math.floor(Math.random() * connectionPairs.length);
        signals.push({
          pairIndex,
          progress: Math.random() * 0.08,
          speed: motionSettings.signalSpeed * (0.72 + Math.random() * 0.68),
          warmth: connectionPairs[pairIndex]?.warmth || Math.random() > 0.8
        });
      }
    }

    let signalCount = 0;

    for (let i = 0; i < signals.length; i++) {
      const signal = signals[i];
      const pair = connectionPairs[signal.pairIndex];

      if (!pair) {
        signals.splice(i, 1);
        i--;
        continue;
      }

      const a = nodes[pair.a].position;
      const b = nodes[pair.b].position;
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      const dz = b.z - a.z;
      const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);

      if (distance < 0.001) {
        signals.splice(i, 1);
        i--;
        continue;
      }

      signal.progress += (signal.speed * deltaSeconds) / distance;

      if (signal.progress >= 1.08) {
        nodes[pair.b].twitch.x += (Math.random() - 0.5) * 0.28 * motionSettings.cursorTwitchStrength;
        nodes[pair.b].twitch.y += (Math.random() - 0.5) * 0.28 * motionSettings.cursorTwitchStrength;
        signals.splice(i, 1);
        i--;
        continue;
      }

      const tailProgress = Math.max(0, signal.progress - motionSettings.signalLength / distance);
      const headProgress = Math.min(1, signal.progress);
      const pi = signalCount * 6;
      const ci = signalCount * 6;
      const warm = signal.warmth;
      const headColor = warm ? colors.orange : colors.pearl;
      const tailColor = warm ? colors.blueSoft : colors.blue;

      signalPositions[pi] = a.x + dx * tailProgress;
      signalPositions[pi + 1] = a.y + dy * tailProgress;
      signalPositions[pi + 2] = a.z + dz * tailProgress;
      signalPositions[pi + 3] = a.x + dx * headProgress;
      signalPositions[pi + 4] = a.y + dy * headProgress;
      signalPositions[pi + 5] = a.z + dz * headProgress;

      signalColors[ci] = tailColor.r * 0.35;
      signalColors[ci + 1] = tailColor.g * 0.35;
      signalColors[ci + 2] = tailColor.b * 0.35;
      signalColors[ci + 3] = headColor.r * 1.35;
      signalColors[ci + 4] = headColor.g * 1.35;
      signalColors[ci + 5] = headColor.b * 1.35;

      signalCount++;

      if (signalCount >= maxSignals) {
        break;
      }
    }

    signalGeometry.setDrawRange(0, signalCount * 2);
    signalGeometry.attributes.position.needsUpdate = true;
    signalGeometry.attributes.color.needsUpdate = true;

    pulses.forEach((pulse, index) => {
      const pair = pulse.userData.connection;
      if (!pair) return;

      const a = nodes[pair.a].position;
      const b = nodes[pair.b].position;
      const progress = (t * 0.26 * motionSettings.pulseSpeed + pulse.userData.offset + index * 0.04) % 1;
      pulse.position.lerpVectors(a, b, progress);
      pulse.scale.setScalar(0.65 + Math.sin(progress * Math.PI) * 1.35);
      pulse.material.opacity = 0.05 + Math.sin(progress * Math.PI) * 0.42;
    });

    try {
      renderer.render(scene, camera);
      renderedFrames++;
      canvas.dataset.scene = "ready";
      canvas.dataset.frames = String(renderedFrames);

      if (renderedFrames === 1) {
        canvas.dataset.litPixels = String(samplePixels());
      }
    } catch (error) {
      canvas.dataset.scene = "error";
      canvas.dataset.error = error && error.message ? error.message : "render failed";
    }
  }

  canvas.dataset.scene = "booting";
  if (nextFrame) {
    nextFrame(animate);
  } else {
    animate(getTime());
  }

  window.addEventListener("resize", () => {
    sizes.width = window.innerWidth;
    sizes.height = window.innerHeight;
    camera.aspect = sizes.width / sizes.height;
    camera.updateProjectionMatrix();
    renderer.setSize(sizes.width, sizes.height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, reducedMotion ? 1 : 1.25));
    placeNetwork();
  });
})();
