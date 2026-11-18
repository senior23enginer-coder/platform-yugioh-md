/**
 * capture-dice: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function parseJsonValue(value, fallback) {
    try {
      return value ? JSON.parse(value) : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function setResultCaptureText(selector, value) {
    const node = resultCaptureModalNode ? resultCaptureModalNode.querySelector(selector) : null;
    if (node) node.textContent = value || '-';
  }

  function setResultCaptureLogo(selector, logoUrl, fallback, altText) {
    const node = resultCaptureModalNode ? resultCaptureModalNode.querySelector(selector) : null;
    if (!node) return;
    node.innerHTML = '';
    node.classList.toggle('has-image', Boolean(logoUrl));
    if (logoUrl) {
      const image = document.createElement('img');
      image.src = logoUrl;
      image.alt = altText || fallback || 'Equipo';
      node.appendChild(image);
      return;
    }
    node.textContent = fallback || '?';
  }

  let resultDice3d = null;

  function createDiceFaceTexture(value, tint = '#ffffff') {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 256, 256);
    gradient.addColorStop(0, tint);
    gradient.addColorStop(0.48, '#f8fbff');
    gradient.addColorStop(1, '#b8c2d0');
    ctx.fillStyle = gradient;
    ctx.roundRect(10, 10, 236, 236, 34);
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,.8)';
    ctx.lineWidth = 6;
    ctx.stroke();
    const pipMap = {
      1: [[128, 128]],
      2: [[82, 82], [174, 174]],
      3: [[78, 78], [128, 128], [178, 178]],
      4: [[78, 78], [178, 78], [78, 178], [178, 178]],
      5: [[78, 78], [178, 78], [128, 128], [78, 178], [178, 178]],
      6: [[78, 68], [178, 68], [78, 128], [178, 128], [78, 188], [178, 188]]
    };
    ctx.fillStyle = '#071126';
    (pipMap[value] || pipMap[1]).forEach(([x, y]) => {
      ctx.beginPath();
      ctx.arc(x, y, 18, 0, Math.PI * 2);
      ctx.fill();
    });
    const texture = new window.THREE.CanvasTexture(canvas);
    if (window.THREE.SRGBColorSpace) {
      texture.colorSpace = window.THREE.SRGBColorSpace;
    } else if (window.THREE.sRGBEncoding) {
      texture.encoding = window.THREE.sRGBEncoding;
    }
    return texture;
  }

  function createResultDie3d(tint) {
    const THREE = window.THREE;
    const geometry = new THREE.BoxGeometry(1.18, 1.18, 1.18, 8, 8, 8);
    const materials = [3, 4, 2, 5, 1, 6].map((value) => new THREE.MeshStandardMaterial({
      map: createDiceFaceTexture(value, tint),
      roughness: 0.38,
      metalness: 0.08
    }));
    const mesh = new THREE.Mesh(geometry, materials);
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
  }

  function resultDiceRotation(value, side = 'a') {
    const base = side === 'a' ? 0.24 : -0.24;
    const map = {
      1: [0.16, base, 0.08],
      2: [-Math.PI / 2 + 0.14, base, 0.08],
      3: [0.16, -Math.PI / 2 + base, 0.08],
      4: [0.16, Math.PI / 2 + base, 0.08],
      5: [Math.PI / 2 + 0.14, base, 0.08],
      6: [0.16, Math.PI + base, 0.08]
    };
    return map[Number(value)] || map[1];
  }

  function ensureResultDice3d() {
    if (resultDice3d || !resultCaptureModalNode || !window.THREE) return resultDice3d;
    const canvas = resultCaptureModalNode.querySelector('[data-result-dice-canvas]');
    const stage = resultCaptureModalNode.querySelector('[data-result-dice-stage]');
    if (!canvas || !stage) return null;
    const THREE = window.THREE;
    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, 2.5, 0.1, 100);
    camera.position.set(0, 1.2, 5.4);
    camera.lookAt(0, 0, 0);
    const ambient = new THREE.AmbientLight(0xffffff, 1.2);
    scene.add(ambient);
    const key = new THREE.DirectionalLight(0xffffff, 2.1);
    key.position.set(3, 5, 4);
    scene.add(key);
    const blue = new THREE.PointLight(0x00c6ff, 1.5, 8);
    blue.position.set(-2.8, 0.8, 2.2);
    scene.add(blue);
    const red = new THREE.PointLight(0xff4b67, 1.3, 8);
    red.position.set(2.8, 0.8, 2.2);
    scene.add(red);
    const dieA = createResultDie3d('#dff8ff');
    const dieB = createResultDie3d('#fff1f3');
    dieA.position.x = -1.15;
    dieB.position.x = 1.15;
    scene.add(dieA, dieB);
    const resize = () => {
      const rect = stage.getBoundingClientRect();
      const width = Math.max(260, Math.floor(rect.width));
      const height = Math.max(120, Math.floor(rect.height));
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
    };
    resultDice3d = { renderer, scene, camera, dieA, dieB, stage, resize, targetA: 1, targetB: 1, rollingUntil: 0 };
    stage.classList.add('has-real-3d');
    resize();
    window.addEventListener('resize', resize);
    const render = () => {
      if (!resultDice3d) return;
      const now = performance.now();
      if (now < resultDice3d.rollingUntil) {
        resultDice3d.dieA.rotation.x += 0.17;
        resultDice3d.dieA.rotation.y += 0.23;
        resultDice3d.dieA.rotation.z += 0.11;
        resultDice3d.dieB.rotation.x += 0.19;
        resultDice3d.dieB.rotation.y -= 0.21;
        resultDice3d.dieB.rotation.z -= 0.13;
      }
      resultDice3d.renderer.render(resultDice3d.scene, resultDice3d.camera);
      requestAnimationFrame(render);
    };
    render();
    return resultDice3d;
  }

  function setResultDie3dValue(side, value, rolling = false) {
    const dice3d = ensureResultDice3d();
    if (!dice3d) return;
    const die = side === 'a' ? dice3d.dieA : dice3d.dieB;
    if (rolling) {
      dice3d.rollingUntil = performance.now() + 1050;
      return;
    }
    const [x, y, z] = resultDiceRotation(value, side);
    die.rotation.set(x, y, z);
    dice3d.renderer.render(dice3d.scene, dice3d.camera);
  }

  function updateResultDie(selector, value, rolling = false) {
    const die = resultCaptureModalNode ? resultCaptureModalNode.querySelector(selector) : null;
    if (!die) return;
    die.dataset.value = String(value || 1);
    die.classList.toggle('is-rolling', rolling);
    setResultDie3dValue(selector.includes('die-b') ? 'b' : 'a', value || 1, rolling);
  }

  function rollSixSidedDie() {
    if (window.crypto && window.crypto.getRandomValues) {
      const value = new Uint32Array(1);
      window.crypto.getRandomValues(value);
      return (value[0] % 6) + 1;
    }
    return Math.floor(Math.random() * 6) + 1;
  }

  function rollOpeningDice() {
    const attempts = [];
    let a = 0;
    let b = 0;
    do {
      a = rollSixSidedDie();
      b = rollSixSidedDie();
      attempts.push({ a, b });
    } while (a === b && attempts.length < 20);
    if (a === b) {
      b = b === 6 ? 1 : b + 1;
      attempts.push({ a, b, forced_tiebreak: true });
    }
    return { a, b, winner: a > b ? 'A' : 'B', attempts };
  }

  function selectedCaptureMatch() {
    return allMatchList().find((match) => String(match.id || '') === String(activeResultCaptureMatchId || '')) || null;
  }

  function oppositePlayer(player) {
    return player === 'A' ? 'B' : player === 'B' ? 'A' : '';
  }

  function inferStartsPlayer(startsPlayer, turnHistory = []) {
    const explicit = String(startsPlayer || '').toUpperCase();
    if (['A', 'B'].includes(explicit)) return explicit;
    const firstTurn = (Array.isArray(turnHistory) ? turnHistory : [])
      .find((entry) => Number(entry?.turn || 0) === 1 && ['A', 'B'].includes(String(entry?.side || '').toUpperCase()));
    return firstTurn ? String(firstTurn.side).toUpperCase() : '';
  }

  function normalizeTurnChoice(choice) {
    return choice === 'second' || choice === 'segundo' ? 'segundo' : choice === 'first' || choice === 'primero' ? 'primero' : '';
  }

  function computeStartsPlayer(chosenBy, choice) {
    const normalized = normalizeTurnChoice(choice);
    if (!['A', 'B'].includes(chosenBy) || !normalized) return '';
    return normalized === 'primero' ? chosenBy : oppositePlayer(chosenBy);
  }

  function turnText(chosenBy, choice, starts) {
    if (!chosenBy || !choice || !starts) return '';
    return `${chosenBy} eligio ${choice}. Empieza ${starts}.`;
  }

  function setResultArenaArt(selector, url) {
    const node = resultCaptureModalNode?.querySelector(selector);
    if (!node) return;
    const cleanUrl = String(url || '').trim();
    if (cleanUrl) {
      node.style.setProperty('--result-team-art', `url("${cleanUrl.replace(/"/g, '%22')}")`);
      node.classList.add('has-art');
    } else {
      node.style.removeProperty('--result-team-art');
      node.classList.remove('has-art');
    }
  }

