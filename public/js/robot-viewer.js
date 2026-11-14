import * as THREE from 'three';

(function () {
    'use strict';

    const stage = document.querySelector('[data-robot-viewer]');
    if (!stage) return;

    const canvas = stage.querySelector('[data-robot-canvas]');
    if (!canvas) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function showFallback() {
        stage.innerHTML =
            '<div class="hero-robot-fallback">' +
            '<img src="' + (stage.dataset.fallbackSrc || '/images/hero-ai-robot.svg') + '" alt="Asisten AI SIMALAS" draggable="false">' +
            '</div>';
    }

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({
            canvas,
            alpha: true,
            antialias: true,
            powerPreference: 'high-performance',
        });
    } catch (_) {
        showFallback();
        return;
    }

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(0, 1.05, 5.2);
    camera.lookAt(0, 0.85, 0);

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    // Lighting
    scene.add(new THREE.AmbientLight(0xffffff, 0.55));

    const keyLight = new THREE.DirectionalLight(0xffffff, 1.15);
    keyLight.position.set(4, 8, 6);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(1024, 1024);
    scene.add(keyLight);

    const fillLight = new THREE.DirectionalLight(0x93c5fd, 0.55);
    fillLight.position.set(-5, 3, 2);
    scene.add(fillLight);

    const rimLight = new THREE.DirectionalLight(0x67e8f9, 0.45);
    rimLight.position.set(0, 4, -6);
    scene.add(rimLight);

    // Materials
    const bodyMat = new THREE.MeshStandardMaterial({
        color: 0x2563eb,
        metalness: 0.42,
        roughness: 0.38,
    });
    const headMat = new THREE.MeshStandardMaterial({
        color: 0x1d4ed8,
        metalness: 0.48,
        roughness: 0.32,
    });
    const accentMat = new THREE.MeshStandardMaterial({
        color: 0x0ea5e9,
        metalness: 0.55,
        roughness: 0.28,
    });
    const darkMat = new THREE.MeshStandardMaterial({
        color: 0x0f172a,
        metalness: 0.35,
        roughness: 0.55,
    });
    const eyeMat = new THREE.MeshStandardMaterial({
        color: 0x67e8f9,
        emissive: 0x22d3ee,
        emissiveIntensity: 1.4,
        metalness: 0.15,
        roughness: 0.12,
    });
    const glowMat = new THREE.MeshStandardMaterial({
        color: 0x38bdf8,
        emissive: 0x0ea5e9,
        emissiveIntensity: 0.85,
        metalness: 0.2,
        roughness: 0.2,
        transparent: true,
        opacity: 0.92,
    });

    const robot = new THREE.Group();
    robot.position.y = 0.15;

    function addMesh(geometry, material, position, rotation, scale, castShadow) {
        const mesh = new THREE.Mesh(geometry, material);
        if (position) mesh.position.set(position.x, position.y, position.z);
        if (rotation) mesh.rotation.set(rotation.x || 0, rotation.y || 0, rotation.z || 0);
        if (scale) {
            if (typeof scale === 'number') mesh.scale.setScalar(scale);
            else mesh.scale.set(scale.x, scale.y, scale.z);
        }
        mesh.castShadow = castShadow !== false;
        mesh.receiveShadow = true;
        return mesh;
    }

    // Hover base
    robot.add(addMesh(new THREE.CylinderGeometry(0.72, 0.88, 0.22, 32), accentMat, { x: 0, y: -0.92, z: 0 }));
    robot.add(addMesh(new THREE.TorusGeometry(0.78, 0.04, 12, 48), glowMat, { x: 0, y: -0.78, z: 0 }, { x: Math.PI / 2 }));

    // Body
    robot.add(addMesh(new THREE.BoxGeometry(1.45, 1.55, 0.95), bodyMat, { x: 0, y: 0.05, z: 0 }));
    robot.add(addMesh(new THREE.BoxGeometry(0.55, 0.55, 0.12), darkMat, { x: 0, y: 0.35, z: 0.5 }));
    robot.add(addMesh(new THREE.BoxGeometry(0.38, 0.38, 0.08), glowMat, { x: 0, y: 0.35, z: 0.57 }));

    // Shoulders
    robot.add(addMesh(new THREE.SphereGeometry(0.22, 16, 16), bodyMat, { x: -0.88, y: 0.62, z: 0 }));
    robot.add(addMesh(new THREE.SphereGeometry(0.22, 16, 16), bodyMat, { x: 0.88, y: 0.62, z: 0 }));

    // Arms
    function createArm(side) {
        const arm = new THREE.Group();
        const upper = addMesh(
            new THREE.BoxGeometry(0.3, 0.72, 0.3),
            bodyMat,
            { x: side * 0.95, y: 0.18, z: 0 },
            { z: side * 0.15 }
        );
        const fore = addMesh(
            new THREE.BoxGeometry(0.26, 0.62, 0.26),
            accentMat,
            { x: side * 1.02, y: -0.42, z: 0.12 },
            { x: 0.55, z: side * 0.2 }
        );
        const hand = addMesh(
            new THREE.SphereGeometry(0.16, 16, 16),
            headMat,
            { x: side * 1.08, y: -0.78, z: 0.22 }
        );
        arm.add(upper, fore, hand);
        return arm;
    }

    robot.add(createArm(-1));
    robot.add(createArm(1));

    // Head
    robot.add(addMesh(new THREE.BoxGeometry(1.15, 0.95, 0.9), headMat, { x: 0, y: 1.28, z: 0 }));
    robot.add(addMesh(new THREE.BoxGeometry(0.88, 0.58, 0.06), darkMat, { x: 0, y: 1.3, z: 0.48 }));

    const eyeGeo = new THREE.SphereGeometry(0.11, 20, 20);
    const eyeL = addMesh(eyeGeo, eyeMat, { x: -0.24, y: 1.36, z: 0.52 });
    const eyeR = addMesh(eyeGeo, eyeMat, { x: 0.24, y: 1.36, z: 0.52 });
    robot.add(eyeL, eyeR);

    // Antenna
    robot.add(addMesh(new THREE.CylinderGeometry(0.05, 0.07, 0.18, 12), accentMat, { x: 0, y: 1.86, z: 0 }));
    const antennaTip = addMesh(new THREE.SphereGeometry(0.1, 16, 16), eyeMat, { x: 0, y: 2.0, z: 0 });
    robot.add(antennaTip);

    // Side panels (depth detail)
    robot.add(addMesh(new THREE.BoxGeometry(0.12, 0.9, 0.55), accentMat, { x: -0.78, y: 0.05, z: 0 }));
    robot.add(addMesh(new THREE.BoxGeometry(0.12, 0.9, 0.55), accentMat, { x: 0.78, y: 0.05, z: 0 }));

    scene.add(robot);

    // Floor shadow disc in 3D
    const shadowDisc = addMesh(
        new THREE.CircleGeometry(1.05, 48),
        new THREE.MeshBasicMaterial({ color: 0x2563eb, transparent: true, opacity: 0.14 }),
        { x: 0, y: -1.02, z: 0 },
        { x: -Math.PI / 2 },
        1,
        false
    );
    scene.add(shadowDisc);

    // Interaction
    let rotY = -0.35;
    let rotX = 0.12;
    let targetY = rotY;
    let targetX = rotX;
    let dragging = false;
    let lastX = 0;
    let lastY = 0;
    let autoSpin = !reduceMotion;
    let clock = new THREE.Clock();

    function resize() {
        const w = stage.clientWidth || 420;
        const h = stage.clientHeight || 420;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }

    function onPointerDown(e) {
        dragging = true;
        autoSpin = false;
        lastX = e.clientX;
        lastY = e.clientY;
        stage.setPointerCapture(e.pointerId);
        stage.classList.add('is-dragging');
    }

    function onPointerMove(e) {
        if (!dragging) return;
        const dx = e.clientX - lastX;
        const dy = e.clientY - lastY;
        lastX = e.clientX;
        lastY = e.clientY;
        targetY += dx * 0.008;
        targetX += dy * 0.006;
        targetX = Math.max(-0.45, Math.min(0.45, targetX));
    }

    function onPointerUp(e) {
        dragging = false;
        stage.classList.remove('is-dragging');
        try {
            stage.releasePointerCapture(e.pointerId);
        } catch (_) {}
    }

    stage.addEventListener('pointerdown', onPointerDown);
    stage.addEventListener('pointermove', onPointerMove);
    stage.addEventListener('pointerup', onPointerUp);
    stage.addEventListener('pointercancel', onPointerUp);

    resize();
    window.addEventListener('resize', resize);

    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(resize).observe(stage);
    }

    function animate() {
        requestAnimationFrame(animate);

        const t = clock.getElapsedTime();

        if (autoSpin && !reduceMotion) {
            targetY += 0.004;
        }

        rotY += (targetY - rotY) * 0.08;
        rotX += (targetX - rotX) * 0.08;
        robot.rotation.y = rotY;
        robot.rotation.x = rotX;

        if (!reduceMotion) {
            robot.position.y = 0.15 + Math.sin(t * 1.6) * 0.06;
            eyeMat.emissiveIntensity = 1.1 + Math.sin(t * 3.2) * 0.35;
            glowMat.emissiveIntensity = 0.65 + Math.sin(t * 2.4) * 0.25;
            antennaTip.scale.setScalar(1 + Math.sin(t * 2.8) * 0.06);
        }

        renderer.render(scene, camera);
    }

    animate();
})();
