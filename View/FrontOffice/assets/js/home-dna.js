(() => {
  const dnaCanvas = document.querySelector(".webgl-dna");

  if (!dnaCanvas || typeof THREE === "undefined") {
    return;
  }

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (prefersReducedMotion) {
    document.body.classList.add("low-motion");
  }

  const scene = new THREE.Scene();
  const sizes = {
    width: window.innerWidth,
    height: window.innerHeight
  };

  const camera = new THREE.PerspectiveCamera(46, sizes.width / sizes.height, 0.1, 100);
  camera.position.set(-2.6, 6.2, 27.5);
  camera.lookAt(3.4, 10, 0);
  camera.rotateZ(Math.PI / 8.5);
  scene.add(camera);

  const renderer = new THREE.WebGLRenderer({
    canvas: dnaCanvas,
    alpha: true,
    antialias: true,
    powerPreference: "high-performance"
  });

  renderer.setSize(sizes.width, sizes.height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, prefersReducedMotion ? 1 : 1.15));

  const dnaGroup = new THREE.Group();
  const dnaCore = new THREE.Group();
  dnaGroup.add(dnaCore);
  scene.add(dnaGroup);

  const dnaHeight = prefersReducedMotion ? 96 : 144;
  const radius = 3.2;
  const angleStep = 0.18;
  const barGap = 6;
  const edgeSize = 0.24;
  const yStep = 0.22;
  const connectorCount = Math.ceil(dnaHeight / barGap);
  const edgeGeometry = new THREE.BoxGeometry(edgeSize, edgeSize, edgeSize);
  const barGeometry = new THREE.BoxGeometry(edgeSize * 0.8, edgeSize * 0.8, radius);
  const blueMaterial = new THREE.MeshBasicMaterial({
    color: new THREE.Color("rgb(111, 143, 216)"),
    transparent: true,
    opacity: 0.9
  });
  const redMaterial = new THREE.MeshBasicMaterial({
    color: new THREE.Color("rgb(255, 110, 69)"),
    transparent: true,
    opacity: 0.9
  });

  const leftEdges = new THREE.InstancedMesh(edgeGeometry, blueMaterial, dnaHeight);
  const rightEdges = new THREE.InstancedMesh(edgeGeometry, redMaterial, dnaHeight);
  const frontBars = new THREE.InstancedMesh(barGeometry, blueMaterial, connectorCount);
  const backBars = new THREE.InstancedMesh(barGeometry, redMaterial, connectorCount);

  leftEdges.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  rightEdges.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  frontBars.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  backBars.instanceMatrix.setUsage(THREE.DynamicDrawUsage);

  dnaCore.add(leftEdges, rightEdges, frontBars, backBars);

  const centerOffset = ((dnaHeight - 1) * yStep) / 2;
  dnaCore.position.y = -centerOffset;

  const basePosition = {
    x: 14.5,
    y: -14 + centerOffset,
    z: -2.3
  };
  dnaGroup.position.set(basePosition.x, basePosition.y, basePosition.z);
  dnaGroup.scale.setScalar(prefersReducedMotion ? 1.7 : 1.82);

  const baseRotationX = -0.66;
  const baseRotationZ = 1.05;
  dnaGroup.rotation.set(baseRotationX, 0, baseRotationZ);

  const temp = new THREE.Object3D();
  const tempPosition = new THREE.Vector3();
  const tempRotation = new THREE.Euler();

  function setMatrix(mesh, index, x, y, z, rotationY = 0) {
    tempPosition.set(x, y, z);
    tempRotation.set(0, rotationY, 0);
    temp.position.copy(tempPosition);
    temp.rotation.copy(tempRotation);
    temp.updateMatrix();
    mesh.setMatrixAt(index, temp.matrix);
  }

  let scrollProgress = 0;
  let targetScrollProgress = 0;
  let lastScrollUpdate = 0;

  function updateScrollProgress() {
    const now = performance.now();
    if (now - lastScrollUpdate < 50) {
      return;
    }

    lastScrollUpdate = now;
    const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    targetScrollProgress = Math.max(0, Math.min(1, window.scrollY / scrollable));
  }

  updateScrollProgress();
  window.addEventListener("scroll", updateScrollProgress, { passive: true });

  function updateDNAFrame(time) {
    const spin = time * 0.001 * (prefersReducedMotion ? 0.42 : 0.5);
    scrollProgress += (targetScrollProgress - scrollProgress) * 0.08;

    dnaGroup.position.x = basePosition.x - scrollProgress * 4.8;
    dnaGroup.position.y = basePosition.y + scrollProgress * 9.5;
    dnaGroup.position.z = basePosition.z - scrollProgress * 1.4;
    dnaGroup.rotation.x = baseRotationX + scrollProgress * 0.16;
    dnaGroup.rotation.z = baseRotationZ - scrollProgress * 0.34;

    for (let index = 0; index < dnaHeight; index += 1) {
      const angle = index * angleStep + spin;
      const y = yStep * index;
      const sin = Math.sin(angle);
      const cos = Math.cos(angle);

      setMatrix(leftEdges, index, radius * sin, y, radius * cos);
      setMatrix(rightEdges, index, -radius * sin, y, -radius * cos);

      const connectorIndex = Math.floor(index / barGap);
      if (index % barGap === 0) {
        const connectorRadius = radius / 2;
        setMatrix(frontBars, connectorIndex, connectorRadius * sin, y, connectorRadius * cos, angle);
        setMatrix(backBars, connectorIndex, -connectorRadius * sin, y, -connectorRadius * cos, angle);
      }
    }

    leftEdges.instanceMatrix.needsUpdate = true;
    rightEdges.instanceMatrix.needsUpdate = true;
    frontBars.instanceMatrix.needsUpdate = true;
    backBars.instanceMatrix.needsUpdate = true;

    renderer.render(scene, camera);
  }

  let animationFrameId = 0;
  let lastFrame = 0;
  const frameStep = prefersReducedMotion ? 1000 / 18 : 1000 / 24;

  function animate(time) {
    animationFrameId = requestAnimationFrame(animate);

    if (document.hidden || time - lastFrame < frameStep) {
      return;
    }

    lastFrame = time;
    updateDNAFrame(time);
  }

  animationFrameId = requestAnimationFrame(animate);

  function handleVisibilityChange() {
    if (document.hidden) {
      return;
    }

    lastFrame = 0;
    updateScrollProgress();
  }

  document.addEventListener("visibilitychange", handleVisibilityChange);

  window.addEventListener("resize", () => {
    sizes.width = window.innerWidth;
    sizes.height = window.innerHeight;
    camera.aspect = sizes.width / sizes.height;
    camera.updateProjectionMatrix();
    renderer.setSize(sizes.width, sizes.height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, prefersReducedMotion ? 1 : 1.15));
  });
})();
