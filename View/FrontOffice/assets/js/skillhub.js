document.addEventListener("DOMContentLoaded", () => {
  const filterChips = Array.from(document.querySelectorAll(".filter-chip"));
  const searchInput = document.getElementById("hubSearch");
  const hubCards = Array.from(document.querySelectorAll(".hub-card"));
  const networkCanvas = document.getElementById("directoryNetworkCanvas");

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

  const initDirectoryNetwork = () => {
    if (!networkCanvas) {
      return;
    }

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const context = networkCanvas.getContext("2d");

    if (!context) {
      return;
    }

    const config = {
      nodeCount: reduceMotion ? 28 : 44,
      linkDistance: reduceMotion ? 92 : 118,
      driftStrength: reduceMotion ? 0.18 : 0.34,
    };

    const palette = {
      blue: "111, 143, 216",
      red: "255, 110, 69",
      gold: "255, 215, 119",
      white: "245, 243, 238",
    };

    let width = 0;
    let height = 0;
    let dpi = 1;
    let animationFrame = null;
    let lastTimestamp = 0;
    let pulse = 0;
    const tints = [palette.blue, palette.red, palette.gold, palette.blue];

    const randomBetween = (min, max) => min + Math.random() * (max - min);

    const nodes = Array.from({ length: config.nodeCount }, (_, index) => {
      const angle = Math.random() * Math.PI * 2;
      const orbitSpeed = randomBetween(0.05, 0.14);
      const seed = Math.random() * Math.PI * 2;
      return {
        cluster: {
          x: randomBetween(0.06, 0.94),
          y: randomBetween(0.08, 0.92),
          spread: randomBetween(0.03, 0.09),
          tint: tints[index % tints.length],
        },
        angle,
        radius: randomBetween(0.03, 0.09),
        orbitSpeed,
        size: randomBetween(1.1, index % 11 === 0 ? 3.4 : 2.2),
        alpha: randomBetween(0.24, index % 7 === 0 ? 0.72 : 0.48),
        seed,
        jitter: randomBetween(0.08, 0.24),
        driftX: randomBetween(-0.18, 0.18),
        driftY: randomBetween(-0.28, 0.28),
      };
    });

    const resizeCanvas = () => {
      width = window.innerWidth;
      height = window.innerHeight;
      dpi = Math.min(window.devicePixelRatio || 1, 2);
      networkCanvas.width = Math.round(width * dpi);
      networkCanvas.height = Math.round(height * dpi);
      networkCanvas.style.width = `${width}px`;
      networkCanvas.style.height = `${height}px`;
      context.setTransform(dpi, 0, 0, dpi, 0, 0);
    };

    const getNodePosition = (node, time) => {
      const drift = time * node.orbitSpeed + node.seed;
      const orbitX = Math.cos(drift * 1.45) * node.radius * width * 0.72;
      const orbitY = Math.sin(drift * 1.38) * node.radius * height * 0.56;
      const strandDriftX = Math.sin(drift * 0.86) * node.driftX * 58;
      const strandDriftY = Math.cos(drift * 0.78) * node.driftY * 58;
      const shimmerX = Math.cos(drift * 3.1) * node.jitter * 10;
      const shimmerY = Math.sin(drift * 2.8) * node.jitter * 10;

      return {
        x: node.cluster.x * width + orbitX + strandDriftX + shimmerX,
        y: node.cluster.y * height + orbitY + strandDriftY + shimmerY,
      };
    };

    const drawOrb = (x, y, size, tint, alpha) => {
      const glow = context.createRadialGradient(x, y, 0, x, y, size * 12);
      glow.addColorStop(0, `rgba(${tint}, ${alpha})`);
      glow.addColorStop(0.32, `rgba(${tint}, ${alpha * 0.34})`);
      glow.addColorStop(0.58, `rgba(${tint}, ${alpha * 0.14})`);
      glow.addColorStop(1, `rgba(${tint}, 0)`);
      context.fillStyle = glow;
      context.beginPath();
      context.arc(x, y, size * 9, 0, Math.PI * 2);
      context.fill();

      context.fillStyle = `rgba(${palette.white}, ${Math.min(alpha + 0.18, 1)})`;
      context.beginPath();
      context.arc(x, y, size, 0, Math.PI * 2);
      context.fill();
    };

    const drawConstellation = (positions) => {
      for (let i = 0; i < positions.length; i += 1) {
        for (let j = i + 1; j < positions.length; j += 1) {
          const dx = positions[i].x - positions[j].x;
          const dy = positions[i].y - positions[j].y;
          const distance = Math.hypot(dx, dy);

          if (distance > config.linkDistance) {
            continue;
          }

          const alpha = 1 - distance / config.linkDistance;
          const gradient = context.createLinearGradient(
            positions[i].x,
            positions[i].y,
            positions[j].x,
            positions[j].y
          );
          gradient.addColorStop(0, `rgba(${positions[i].cluster.tint}, ${alpha * 0.14})`);
          gradient.addColorStop(0.5, `rgba(${palette.white}, ${alpha * 0.08})`);
          gradient.addColorStop(1, `rgba(${positions[j].cluster.tint}, ${alpha * 0.14})`);
          context.strokeStyle = gradient;
          context.lineWidth = alpha * 1.15;
          context.beginPath();
          context.moveTo(positions[i].x, positions[i].y);
          context.lineTo(positions[j].x, positions[j].y);
          context.stroke();
        }
      }
    };

    const drawAmbientVeil = () => {
      const veil = context.createLinearGradient(0, 0, width, height);
      veil.addColorStop(0, "rgba(2, 5, 15, 0.16)");
      veil.addColorStop(0.5, "rgba(4, 9, 20, 0.08)");
      veil.addColorStop(1, "rgba(7, 5, 16, 0.22)");
      context.fillStyle = veil;
      context.fillRect(0, 0, width, height);

      const leftHalo = context.createRadialGradient(
        width * 0.16,
        height * 0.24,
        0,
        width * 0.16,
        height * 0.24,
        width * 0.26
      );
      leftHalo.addColorStop(0, `rgba(${palette.blue}, 0.11)`);
      leftHalo.addColorStop(1, `rgba(${palette.blue}, 0)`);
      context.fillStyle = leftHalo;
      context.fillRect(0, 0, width, height);

      const rightHalo = context.createRadialGradient(
        width * 0.82,
        height * 0.18,
        0,
        width * 0.82,
        height * 0.18,
        width * 0.24
      );
      rightHalo.addColorStop(0, `rgba(${palette.red}, 0.11)`);
      rightHalo.addColorStop(1, `rgba(${palette.red}, 0)`);
      context.fillStyle = rightHalo;
      context.fillRect(0, 0, width, height);
    };

    const renderFrame = (timestamp) => {
      const delta = Math.min((timestamp - lastTimestamp) / 1000 || 0.016, 0.05);
      lastTimestamp = timestamp;
      pulse += delta;

      context.clearRect(0, 0, width, height);
      drawAmbientVeil();

      const positions = nodes.map((node) => ({
        ...getNodePosition(node, pulse * config.driftStrength),
        cluster: node.cluster,
        size: node.size,
        alpha: node.alpha,
      }));

      drawConstellation(positions);

      positions.forEach((position, index) => {
        const shimmer = 1 + Math.sin(pulse * 2.9 + nodes[index].seed) * 0.16;
        drawOrb(
          position.x,
          position.y,
          position.size,
          nodes[index].cluster.tint,
          position.alpha * shimmer
        );
      });

      if (!reduceMotion) {
        animationFrame = window.requestAnimationFrame(renderFrame);
      }
    };

    resizeCanvas();

    if (reduceMotion) {
      renderFrame(16);
    } else {
      animationFrame = window.requestAnimationFrame(renderFrame);
    }

    window.addEventListener("resize", resizeCanvas);

    window.addEventListener("blur", () => {
      if (animationFrame) {
        window.cancelAnimationFrame(animationFrame);
        animationFrame = null;
      }
    });

    window.addEventListener("focus", () => {
      if (!reduceMotion && !animationFrame) {
        lastTimestamp = performance.now();
        animationFrame = window.requestAnimationFrame(renderFrame);
      }
    });
  };

  initDirectoryNetwork();
  applyFilters();
});
