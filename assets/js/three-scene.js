/**
 * Finom Three.js háttér a Hello AI Audit felülethez.
 *
 * A jelenet szándékosan dekoratív: egy AI/citációs hálóra emlékeztető
 * pont-rendszer, amely a márka magenta és navy színeit használja. Ha a CDN
 * vagy WebGL nem elérhető, a felület változatlanul használható marad.
 */

const canvas = document.querySelector('#helloThreeCanvas');
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (canvas) {
    initHelloScene(canvas).catch(() => {
        canvas.classList.add('is-unavailable');
    });
}

async function initHelloScene(targetCanvas) {
    const THREE = await import('https://unpkg.com/three@0.165.0/build/three.module.js');
    const renderer = new THREE.WebGLRenderer({
        canvas: targetCanvas,
        antialias: true,
        alpha: true,
        powerPreference: 'high-performance',
    });

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(0, 0, 18);

    const group = new THREE.Group();
    scene.add(group);

    const nodeCount = window.innerWidth < 760 ? 54 : 86;
    const positions = [];
    const colors = [];
    const magenta = new THREE.Color('#ff00a8');
    const navy = new THREE.Color('#0d1321');
    const violet = new THREE.Color('#7c3aed');

    for (let index = 0; index < nodeCount; index++) {
        const ring = index % 4;
        const angle = index * 0.74;
        const radius = 2.6 + ring * 0.75 + Math.sin(index * 0.55) * 0.25;
        const x = Math.cos(angle) * radius + (index % 3) * 0.18;
        const y = Math.sin(angle * 0.86) * radius * 0.62;
        const z = (ring - 1.5) * 1.25 + Math.sin(index * 0.31) * 0.55;
        positions.push(x, y, z);

        const color = index % 5 === 0 ? magenta : (index % 3 === 0 ? violet : navy);
        colors.push(color.r, color.g, color.b);
    }

    const pointGeometry = new THREE.BufferGeometry();
    pointGeometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    pointGeometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));

    const pointMaterial = new THREE.PointsMaterial({
        size: window.innerWidth < 760 ? 0.12 : 0.11,
        vertexColors: true,
        transparent: true,
        opacity: 0.95,
        depthWrite: false,
    });

    const points = new THREE.Points(pointGeometry, pointMaterial);
    group.add(points);

    const linePositions = [];
    const lineColors = [];
    for (let index = 0; index < nodeCount; index++) {
        const next = (index + 5) % nodeCount;
        const nextAlt = (index + 13) % nodeCount;
        addLine(index, next, 0.26);
        if (index % 4 === 0) {
            addLine(index, nextAlt, 0.16);
        }
    }

    function addLine(from, to, opacityHint) {
        const fromOffset = from * 3;
        const toOffset = to * 3;
        linePositions.push(
            positions[fromOffset],
            positions[fromOffset + 1],
            positions[fromOffset + 2],
            positions[toOffset],
            positions[toOffset + 1],
            positions[toOffset + 2]
        );
        const tint = opacityHint > 0.2 ? magenta : navy;
        lineColors.push(tint.r, tint.g, tint.b, tint.r, tint.g, tint.b);
    }

    const lineGeometry = new THREE.BufferGeometry();
    lineGeometry.setAttribute('position', new THREE.Float32BufferAttribute(linePositions, 3));
    lineGeometry.setAttribute('color', new THREE.Float32BufferAttribute(lineColors, 3));
    const lineMaterial = new THREE.LineBasicMaterial({
        vertexColors: true,
        transparent: true,
        opacity: 0.26,
        depthWrite: false,
    });
    const lines = new THREE.LineSegments(lineGeometry, lineMaterial);
    group.add(lines);

    const haloGeometry = new THREE.TorusGeometry(4.6, 0.018, 8, 96);
    const haloMaterial = new THREE.MeshBasicMaterial({
        color: '#ff00a8',
        transparent: true,
        opacity: 0.22,
        depthWrite: false,
    });
    const halo = new THREE.Mesh(haloGeometry, haloMaterial);
    halo.rotation.set(0.8, 0.1, -0.35);
    group.add(halo);

    function resize() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(width, height, false);
        camera.aspect = width / Math.max(height, 1);
        camera.updateProjectionMatrix();
        group.position.set(width < 760 ? 1.2 : 3.55, width < 760 ? -1.05 : -0.3, 0);
        group.scale.setScalar(width < 760 ? 0.85 : 1.15);
    }

    window.addEventListener('resize', resize, { passive: true });
    resize();

    let frame = 0;
    function render() {
        frame += 0.01;
        if (!prefersReducedMotion) {
            group.rotation.y = Math.sin(frame * 0.55) * 0.18 - 0.28;
            group.rotation.x = Math.cos(frame * 0.4) * 0.05;
            halo.rotation.z += 0.0016;
        }
        renderer.render(scene, camera);
    }

    if (prefersReducedMotion) {
        render();
    } else {
        renderer.setAnimationLoop(render);
    }

    window.addEventListener('pagehide', () => {
        renderer.setAnimationLoop(null);
        window.removeEventListener('resize', resize);
        pointGeometry.dispose();
        pointMaterial.dispose();
        lineGeometry.dispose();
        lineMaterial.dispose();
        haloGeometry.dispose();
        haloMaterial.dispose();
        renderer.dispose();
    }, { once: true });
}
