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
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, prefersReducedMotion ? 1 : 1.35));

  const dnaGroup = new THREE.Group();
  const dnaCore = new THREE.Group();
  dnaGroup.add(dnaCore);
  scene.add(dnaGroup);

  const dnaHeight = prefersReducedMotion ? 112 : 180;
  const radius = 3.2;
  const angleStep = 0.18;
  const barGap = 6;
  const edgeSize = 0.24;
  const yStep = 0.22;
  const fragments = [];
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

  for (let index = 0; index < dnaHeight; index += 1) {
    const fragment = new THREE.Group();
    const left = new THREE.Mesh(edgeGeometry, blueMaterial);
    const right = new THREE.Mesh(edgeGeometry, redMaterial);

    left.position.y = yStep * index;
    right.position.y = yStep * index;
    fragment.add(left);
    fragment.add(right);

    let connector = null;
    if (index % barGap === 0) {
      connector = new THREE.Group();

      const front = new THREE.Mesh(barGeometry, blueMaterial);
      front.position.y = yStep * index;
      front.position.z = radius / 2;

      const back = new THREE.Mesh(barGeometry, redMaterial);
      back.position.y = yStep * index;
      back.position.z = -radius / 2;

      connector.add(front);
      connector.add(back);
      fragment.add(connector);
    }

    dnaCore.add(fragment);
    fragments.push({ index, left, right, connector });
  }

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

  let scrollProgress = 0;
  let targetScrollProgress = 0;

  function updateScrollProgress() {
    const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    targetScrollProgress = Math.max(0, Math.min(1, window.scrollY / scrollable));
  }

  updateScrollProgress();
  window.addEventListener("scroll", updateScrollProgress, { passive: true });

  let lastFrame = 0;
  const frameStep = prefersReducedMotion ? 1000 / 24 : 1000 / 36;

  function animate(time) {
    requestAnimationFrame(animate);

    if (document.hidden || time - lastFrame < frameStep) {
      return;
    }

    lastFrame = time;
    const spin = time * 0.001 * (prefersReducedMotion ? 0.48 : 0.55);
    scrollProgress += (targetScrollProgress - scrollProgress) * 0.08;

    dnaGroup.position.x = basePosition.x - scrollProgress * 4.8;
    dnaGroup.position.y = basePosition.y + scrollProgress * 9.5;
    dnaGroup.position.z = basePosition.z - scrollProgress * 1.4;
    dnaGroup.rotation.x = baseRotationX + scrollProgress * 0.16;
    dnaGroup.rotation.z = baseRotationZ - scrollProgress * 0.34;

    fragments.forEach(({ index, left, right, connector }) => {
      const angle = index * angleStep + spin;
      const y = yStep * index;

      left.position.x = radius * Math.sin(angle);
      left.position.z = radius * Math.cos(angle);
      left.position.y = y;

      right.position.x = -radius * Math.sin(angle);
      right.position.z = -radius * Math.cos(angle);
      right.position.y = y;

      if (connector) {
        connector.rotation.y = angle;
      }
    });

    renderer.render(scene, camera);
  }

  requestAnimationFrame(animate);

  window.addEventListener("resize", () => {
    sizes.width = window.innerWidth;
    sizes.height = window.innerHeight;
    camera.aspect = sizes.width / sizes.height;
    camera.updateProjectionMatrix();
    renderer.setSize(sizes.width, sizes.height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, prefersReducedMotion ? 1 : 1.35));
  });
})();
