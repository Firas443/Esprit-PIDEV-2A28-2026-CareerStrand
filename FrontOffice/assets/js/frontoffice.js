    /* =========================
       FULLSCREEN BACKGROUND DNA
       FIXED: rotates in place
    ========================= */
    const dnaCanvas = document.querySelector(".webgl-dna");
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const lowMotionMode = prefersReducedMotion;

    if (lowMotionMode) {
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
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, lowMotionMode ? 1 : 1.35));

    const DNA = new THREE.Group();
    const dnaCore = new THREE.Group();
    DNA.add(dnaCore);
    scene.add(DNA);

    const dnaHeight = lowMotionMode ? 112 : 180;
    const radiusEdges = 3.2;
    const variationAngle = 0.18;
    const unionGap = 6;
    const edgeSize = 0.24;
    const verticalSeparation = 0.22;
    const dnaFragments = [];
    const edgeGeometry = new THREE.BoxGeometry(edgeSize, edgeSize, edgeSize);
    const bladeGeometry = new THREE.BoxGeometry(edgeSize * 0.8, edgeSize * 0.8, radiusEdges);
    const materialA = new THREE.MeshBasicMaterial({
      color: new THREE.Color("rgb(111, 143, 216)"),
      transparent: true,
      opacity: 0.95
    });
    const materialB = new THREE.MeshBasicMaterial({
      color: new THREE.Color("rgb(255, 110, 69)"),
      transparent: true,
      opacity: 0.95
    });

    function createDNAFragment(hasBlade, index) {
      const fragment = new THREE.Group();

      const firstEdge = new THREE.Mesh(
        edgeGeometry,
        materialA
      );

      const secondEdge = new THREE.Mesh(
        edgeGeometry,
        materialB
      );

      firstEdge.position.x = radiusEdges * Math.sin(index * variationAngle);
      firstEdge.position.z = radiusEdges * Math.cos(index * variationAngle);
      firstEdge.position.y = verticalSeparation * index;

      secondEdge.position.x = -radiusEdges * Math.sin(index * variationAngle);
      secondEdge.position.z = -radiusEdges * Math.cos(index * variationAngle);
      secondEdge.position.y = verticalSeparation * index;

      fragment.add(firstEdge);
      fragment.add(secondEdge);

      let blade = null;

      if (hasBlade) {
        blade = new THREE.Group();

        const firstBlade = new THREE.Mesh(
          bladeGeometry,
          materialA
        );
        firstBlade.position.y = verticalSeparation * index;
        firstBlade.position.z = radiusEdges / 2;

        const secondBlade = new THREE.Mesh(
          bladeGeometry,
          materialB
        );
        secondBlade.position.y = verticalSeparation * index;
        secondBlade.position.z = -radiusEdges / 2;

        blade.add(firstBlade);
        blade.add(secondBlade);
        blade.rotation.y = index * variationAngle;

        fragment.add(blade);
      }

      dnaCore.add(fragment);
      dnaFragments.push({
        index,
        firstEdge,
        secondEdge,
        blade
      });
    }

    for (let i = 0; i < dnaHeight; i++) {
      createDNAFragment(i % unionGap === 0, i);
    }

    const dnaCenterOffset = ((dnaHeight - 1) * verticalSeparation) / 2;
    dnaCore.position.y = -dnaCenterOffset;

    DNA.position.set(14.5, -14 + dnaCenterOffset, -2.3);
    DNA.scale.setScalar(lowMotionMode ? 1.7 : 1.82);
    const baseRotationX = -0.66;
    const baseRotationZ = 1.05;
    DNA.rotation.set(baseRotationX, 0, baseRotationZ);

    let lastBackgroundFrame = 0;
    const backgroundFrameStep = lowMotionMode ? 1000 / 24 : 1000 / 36;

    function animateBackgroundDNA(time) {
      requestAnimationFrame(animateBackgroundDNA);

      if (document.hidden || time - lastBackgroundFrame < backgroundFrameStep) {
        return;
      }

      lastBackgroundFrame = time;
      const t = time * 0.001;
      const spinAngle = t * (lowMotionMode ? 0.48 : 0.55);

      DNA.rotation.x = baseRotationX;
      DNA.rotation.y = 0;
      DNA.rotation.z = baseRotationZ;

      dnaFragments.forEach(({ index, firstEdge, secondEdge, blade }) => {
        const angle = index * variationAngle + spinAngle;
        const y = verticalSeparation * index;

        firstEdge.position.x = radiusEdges * Math.sin(angle);
        firstEdge.position.z = radiusEdges * Math.cos(angle);
        firstEdge.position.y = y;

        secondEdge.position.x = -radiusEdges * Math.sin(angle);
        secondEdge.position.z = -radiusEdges * Math.cos(angle);
        secondEdge.position.y = y;

        if (blade) {
          blade.rotation.y = angle;
        }
      });

      renderer.render(scene, camera);
    }

    requestAnimationFrame(animateBackgroundDNA);

    window.addEventListener("resize", () => {
      sizes.width = window.innerWidth;
      sizes.height = window.innerHeight;

      camera.aspect = sizes.width / sizes.height;
      camera.updateProjectionMatrix();

      renderer.setSize(sizes.width, sizes.height);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, lowMotionMode ? 1 : 1.35));
    });

    /* =========================
       HERO CARD DNA
       left as its own animation
    ========================= */
    const helix = document.getElementById("helix");
    const helixRows = [];
    const helixCount = lowMotionMode ? 16 : 18;

    for (let row = 0; row < helixCount; row++) {
      const wrapper = document.createElement("div");
      wrapper.className = "helix-row";
      wrapper.style.top = (44 + row * 28) + "px";

      const leftNode = document.createElement("div");
      leftNode.className = "node left";

      const rightNode = document.createElement("div");
      rightNode.className = "node right";

      const bar = document.createElement("div");
      bar.className = "bar";

      wrapper.appendChild(leftNode);
      wrapper.appendChild(rightNode);
      wrapper.appendChild(bar);
      helix.appendChild(wrapper);

      helixRows.push({ row, leftNode, rightNode, bar });
    }

    let lastHelixFrame = 0;
    const helixFrameStep = lowMotionMode ? 1000 / 28 : 1000 / 42;

    function animateHelix(time = performance.now()) {
      requestAnimationFrame(animateHelix);

      if (document.hidden || time - lastHelixFrame < helixFrameStep) {
        return;
      }

      lastHelixFrame = time;
      const t = performance.now() * 0.001;

      helixRows.forEach(({ row, leftNode, rightNode, bar }) => {
        const centerX = 220;
        const amplitude = 100;
        const phase = row * 0.56 + t * 1.7;

        const x = Math.sin(phase) * amplitude;
        const depth = (Math.cos(phase) + 1) / 2;

        const left = centerX + x - 92;
        const right = centerX - x + 92;

        const minX = Math.min(left, right);
        const maxX = Math.max(left, right);

        leftNode.style.left = left + "px";
        rightNode.style.left = right + "px";

        bar.style.left = (minX + 8) + "px";
        bar.style.width = Math.max(24, maxX - minX - 8) + "px";

        const scale = 0.72 + depth * 0.55;
        const opacity = 0.34 + depth * 0.76;
        const blur = lowMotionMode ? (1 - depth) * 2 : (1 - depth) * 4;

        leftNode.style.transform = `scale(${scale})`;
        rightNode.style.transform = `scale(${scale})`;

        leftNode.style.opacity = opacity;
        rightNode.style.opacity = opacity;
        bar.style.opacity = 0.24 + depth * 0.76;

        leftNode.style.filter = `blur(${blur}px)`;
        rightNode.style.filter = `blur(${blur}px)`;
        bar.style.filter = `blur(${blur * 0.55}px)`;

        if (Math.sin(phase) > 0) {
          leftNode.style.zIndex = "3";
          rightNode.style.zIndex = "2";
        } else {
          leftNode.style.zIndex = "2";
          rightNode.style.zIndex = "3";
        }
      });

    }

    requestAnimationFrame(animateHelix);

    /* =========================
       MATCH PREVIEW
    ========================= */
    const matchItems = document.querySelectorAll(".match-item");
    const previewCompany = document.getElementById("preview-company");
    const previewRole = document.getElementById("preview-role");
    const previewVibe = document.getElementById("preview-vibe");
    const previewScoreLabel = document.getElementById("preview-score-label");
    const previewBar = document.getElementById("preview-bar");
    const previewPoints = document.getElementById("preview-points");

    function updatePreview(button) {
      matchItems.forEach(item => item.classList.remove("active"));
      button.classList.add("active");

      const company = button.dataset.company;
      const role = button.dataset.role;
      const match = button.dataset.match;
      const vibe = button.dataset.vibe;
      const points = JSON.parse(button.dataset.points);

      previewCompany.textContent = company;
      previewRole.textContent = role;
      previewVibe.textContent = vibe;
      previewScoreLabel.textContent = match + "%";
      previewBar.style.width = match + "%";

      previewPoints.innerHTML = "";
      points.forEach(text => {
        const div = document.createElement("div");
        div.className = "point";
        div.textContent = text;
        previewPoints.appendChild(div);
      });
    }

    matchItems.forEach(item => {
      item.addEventListener("click", () => updatePreview(item));
    });

    previewBar.style.width = "98%";

    /* =========================
       FADE IN ON SCROLL
    ========================= */
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("show");
        }
      });
    }, { threshold: 0.15 });

    document.querySelectorAll(".fade-up").forEach(el => observer.observe(el));
