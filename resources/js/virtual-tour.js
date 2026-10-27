// TOUR-01: walkable 360 store tour on the existing three.js dependency.
// Yaw follows the panorama (0 = image centre, positive to the right); see config/store_virtual_tour.php.
import { LinearFilter, Mesh, MeshBasicMaterial, PerspectiveCamera, Scene, SphereGeometry, SRGBColorSpace, TextureLoader, Vector3, WebGLRenderer } from 'three';

const DEG = Math.PI / 180;
const MAX_VFOV = 100;
const WALK_HFOV = 55;

const stage = document.querySelector('[data-tour-stage]');
const dataElement = document.getElementById('virtual-tour-data');
if (stage && dataElement) init(JSON.parse(dataElement.textContent));

function init(tour) {
    const $ = (name) => document.querySelector(`[data-tour-${name}]`);
    const els = {
        canvas: $('canvas'), hotspots: $('hotspots'), status: $('status'), error: $('error'), retry: $('retry'),
        plan: $('plan'), hint: $('hint'), fallback: $('fallback'), areas: $('areas'), live: $('live'),
    };
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const spots = new Map(tour.spots.map((spot) => [spot.id, spot]));

    const renderer = createRenderer();
    if (!renderer || renderer.capabilities.maxTextureSize < 4096) return showFallback();
    // The 6144 px panorama only where the GPU can hold it and the screen benefits; phones get 4096 px.
    const useLarge = renderer.capabilities.maxTextureSize >= 8192 && window.innerWidth >= 1024 && !navigator.connection?.saveData;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    els.canvas.appendChild(renderer.domElement);

    const scene = new Scene();
    const camera = new PerspectiveCamera(75, 1, 0.1, 100);
    const geometry = new SphereGeometry(10, 64, 32);
    geometry.scale(-1, 1, 1);
    const sphere = (order) => {
        const mesh = new Mesh(geometry, new MeshBasicMaterial({ transparent: true, depthTest: false, depthWrite: false }));
        mesh.renderOrder = order;
        mesh.visible = false;
        scene.add(mesh);
        return mesh;
    };
    let current = sphere(0);
    let incoming = sphere(1);

    const view = { yaw: 0, pitch: -4, hfov: 100 };
    const state = { spot: null, busy: false, dirty: true, interacted: false, retry: null };
    const tweens = [];
    const loader = new TextureLoader();
    const textures = new Map();

    // ---- textures ---------------------------------------------------------------------------
    function texture(id) {
        if (!textures.has(id)) {
            const spot = spots.get(id);
            const url = useLarge ? spot.images.large : spot.images.small;
            textures.set(id, new Promise((resolve, reject) => loader.load(url, (map) => {
                map.colorSpace = SRGBColorSpace;
                map.minFilter = LinearFilter;
                map.generateMipmaps = false;
                resolve(map);
            }, undefined, (error) => {
                textures.delete(id);
                reject(error);
            })));
        }
        return textures.get(id);
    }
    function keepNear(id) {
        const keep = new Set([id, ...spots.get(id).links.map((link) => link.to)]);
        for (const [key, promise] of textures) {
            if (!keep.has(key)) {
                textures.delete(key);
                promise.then((map) => map.dispose(), () => {});
            }
        }
        keep.forEach((key) => texture(key).catch(() => {}));
    }

    // ---- camera -------------------------------------------------------------------------------
    const direction = (yaw, pitch) => new Vector3(
        -Math.cos(pitch * DEG) * Math.cos(yaw * DEG), Math.sin(pitch * DEG), -Math.cos(pitch * DEG) * Math.sin(yaw * DEG),
    );
    const aspect = () => Math.max(stage.clientWidth, 1) / Math.max(stage.clientHeight, 1);
    const maxHfov = () => Math.min(110, 2 * Math.atan(Math.tan((MAX_VFOV / 2) * DEG) * aspect()) / DEG);
    const baseHfov = () => Math.min(stage.clientWidth < 640 ? 85 : 100, maxHfov());
    const clampView = () => {
        view.pitch = Math.max(-85, Math.min(85, view.pitch));
        view.hfov = Math.max(30, Math.min(maxHfov(), view.hfov));
    };

    function resize() {
        renderer.setSize(stage.clientWidth, stage.clientHeight, false);
        camera.aspect = aspect();
        clampView();
        state.dirty = true;
    }
    new ResizeObserver(resize).observe(stage);

    function render() {
        camera.fov = 2 * Math.atan(Math.tan((view.hfov / 2) * DEG) / camera.aspect) / DEG;
        camera.updateProjectionMatrix();
        camera.lookAt(direction(view.yaw, view.pitch));
        renderer.render(scene, camera);
        placeArrows();
        turnCone();
    }

    function frame(time) {
        for (let i = tweens.length - 1; i >= 0; i--) {
            const tween = tweens[i];
            tween.start ??= time;
            const t = Math.min(1, (time - tween.start) / tween.duration);
            tween.update(t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
            if (t === 1) {
                tweens.splice(i, 1);
                tween.done();
            }
            state.dirty = true;
        }
        if (state.dirty) {
            state.dirty = false;
            render();
        }
        requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);

    function tween(duration, update) {
        if (reduceMotion || duration <= 0) {
            update(1);
            state.dirty = true;
            return Promise.resolve();
        }
        return new Promise((done) => tweens.push({ duration, update, done }));
    }
    function animateView(target, duration) {
        const from = { ...view };
        const yaw = from.yaw + ((((target.yaw - from.yaw) % 360) + 540) % 360) - 180;
        return tween(duration, (k) => {
            view.yaw = from.yaw + (yaw - from.yaw) * k;
            view.pitch = from.pitch + (target.pitch - from.pitch) * k;
            view.hfov = from.hfov + (target.hfov - from.hfov) * k;
        });
    }

    // ---- moving between spots -------------------------------------------------------------------
    async function show(id, arriveYaw, { walk = false } = {}) {
        if (state.busy) return;
        state.busy = true;
        const slow = setTimeout(() => setStatus('Memuat foto 360…'), 350);
        try {
            const [map] = await Promise.all([
                texture(id),
                walk ? animateView(walk, 650) : Promise.resolve(),
            ]);
            clearTimeout(slow);
            setStatus(null);
            if (!state.spot) {
                current.material.map = map;
                current.material.needsUpdate = true;
                current.visible = true;
                Object.assign(view, { yaw: arriveYaw, pitch: -4, hfov: baseHfov() });
            } else {
                // Rotate the next panorama so the arrival direction lines up with the current view,
                // fade it in, then hand over without a visible jump.
                const offset = arriveYaw - view.yaw;
                incoming.material.map = map;
                incoming.material.needsUpdate = true;
                incoming.material.opacity = 0;
                incoming.rotation.y = offset * DEG;
                incoming.visible = true;
                await tween(450, (k) => { incoming.material.opacity = k; });
                current.visible = false;
                [current, incoming] = [incoming, current];
                current.renderOrder = 0;
                incoming.renderOrder = 1;
                current.rotation.y = 0;
                view.yaw = arriveYaw;
            }
            state.spot = id;
            buildArrows();
            drawPlan();
            syncAreas();
            els.live.textContent = `Sekarang di area ${spots.get(id).name}`;
            state.busy = false;
            state.dirty = true;
            keepNear(id);
            if (walk || view.hfov !== baseHfov()) await animateView({ yaw: view.yaw, pitch: -4, hfov: baseHfov() }, 650);
        } catch {
            clearTimeout(slow);
            setStatus(null);
            state.busy = false;
            showError(() => show(id, arriveYaw));
        }
    }

    function walkTo(link) {
        show(link.to, link.arrive, { walk: { yaw: link.yaw, pitch: -6, hfov: Math.min(WALK_HFOV, view.hfov) } });
    }

    function goTo(id) {
        if (id === state.spot) return;
        const link = spots.get(state.spot)?.links.find((candidate) => candidate.to === id);
        if (link) walkTo(link);
        else show(id, spots.get(id).yaw);
    }

    // ---- walk arrows --------------------------------------------------------------------------
    let arrows = [];
    function buildArrows() {
        els.hotspots.textContent = '';
        arrows = spots.get(state.spot).links.map((link) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'vt-arrow';
            button.setAttribute('aria-label', `Jalan ke ${spots.get(link.to).name}`);
            button.innerHTML = '<span class="vt-arrow-ring"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 15l7-7 7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span class="vt-arrow-label"></span>';
            button.querySelector('.vt-arrow-label').textContent = spots.get(link.to).name;
            button.addEventListener('click', () => walkTo(link));
            els.hotspots.appendChild(button);
            return { button, point: direction(link.yaw, link.pitch).multiplyScalar(5) };
        });
    }
    const projected = new Vector3();
    function placeArrows() {
        const forward = direction(view.yaw, view.pitch);
        const width = stage.clientWidth;
        const height = stage.clientHeight;
        for (const arrow of arrows) {
            const visible = arrow.point.clone().normalize().dot(forward) > 0.1;
            projected.copy(arrow.point).project(camera);
            const x = (projected.x + 1) / 2 * width;
            const y = (1 - projected.y) / 2 * height;
            const inside = visible && x > -40 && x < width + 40 && y > -40 && y < height + 40;
            arrow.button.hidden = !inside || state.busy;
            if (inside) arrow.button.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)`;
        }
    }

    // ---- floor plan -----------------------------------------------------------------------------
    const svg = els.plan.querySelector('svg');
    const NS = 'http://www.w3.org/2000/svg';
    const plan = (() => {
        const xs = tour.spots.map((spot) => spot.plan[0]);
        const ys = tour.spots.map((spot) => spot.plan[1]);
        const scale = Math.min(68 / Math.max(Math.max(...xs) - Math.min(...xs), 0.1), 118 / Math.max(Math.max(...ys) - Math.min(...ys), 0.1));
        const cx = (Math.max(...xs) + Math.min(...xs)) / 2;
        const cy = (Math.max(...ys) + Math.min(...ys)) / 2;
        return ([x, y]) => [50 + (x - cx) * scale, 86 - (y - cy) * scale];
    })();
    const node = (name, attrs, text) => {
        const element = document.createElementNS(NS, name);
        Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, value));
        if (text) element.textContent = text;
        return element;
    };
    let cone = null;
    function drawPlan() {
        svg.textContent = '';
        svg.append(
            node('rect', { x: 4, y: 14, width: 92, height: 144, rx: 6, class: 'room' }),
            node('text', { x: 50, y: 10, 'text-anchor': 'middle', class: 'end' }, 'Kasir'),
            node('text', { x: 50, y: 170, 'text-anchor': 'middle', class: 'end' }, 'Pintu'),
            node('path', { class: 'route', d: tour.spots.map((spot, i) => `${i ? 'L' : 'M'}${plan(spot.plan).join(' ')}`).join(' ') }),
        );
        cone = node('path', { class: 'cone', d: 'M0 0 L-11 -20 A23 23 0 0 1 11 -20 Z' });
        svg.appendChild(cone);
        for (const spot of tour.spots) {
            const [x, y] = plan(spot.plan);
            const here = spot.id === state.spot;
            const dot = node('circle', { cx: x, cy: y, r: here ? 6 : 5, class: here ? 'dot is-here' : 'dot', tabindex: 0, role: 'button', 'aria-label': `Ke area ${spot.name}` });
            dot.appendChild(node('title', {}, spot.name));
            dot.addEventListener('click', () => goTo(spot.id));
            dot.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    goTo(spot.id);
                }
            });
            svg.appendChild(dot);
        }
        els.plan.hidden = false;
        turnCone();
    }
    function turnCone() {
        if (!cone || !state.spot) return;
        const spot = spots.get(state.spot);
        const [x, y] = plan(spot.plan);
        cone.setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${(spot.theta + view.yaw).toFixed(1)})`);
    }

    // ---- page controls ----------------------------------------------------------------------------
    const areaButtons = [...document.querySelectorAll('[data-tour-goto]')];
    areaButtons.forEach((button) => button.addEventListener('click', () => goTo(button.dataset.tourGoto)));
    function syncAreas() {
        areaButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.tourGoto === state.spot)));
    }

    function setStatus(text) {
        els.status.hidden = !text;
        if (text) els.status.textContent = text;
    }
    function showError(retry) {
        state.retry = retry;
        els.error.hidden = false;
    }
    els.retry.addEventListener('click', () => {
        els.error.hidden = true;
        const retry = state.retry;
        state.retry = null;
        retry?.();
    });
    renderer.domElement.addEventListener('webglcontextlost', (event) => {
        event.preventDefault();
        showError(() => window.location.reload());
    });

    function interacted() {
        if (state.interacted) return;
        state.interacted = true;
        els.hint.hidden = true;
    }

    // ---- look around: drag, pinch, wheel, keyboard ---------------------------------------------------
    const pointers = new Map();
    let pinch = null;
    stage.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button, .vt-plan')) return;
        try {
            stage.setPointerCapture(event.pointerId);
        } catch {
            // Capture is a convenience; dragging still works while the pointer stays on the stage.
        }
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        stage.classList.add('is-dragging');
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), hfov: view.hfov };
        }
    });
    stage.addEventListener('pointermove', (event) => {
        const last = pointers.get(event.pointerId);
        if (!last || state.busy) return;
        const next = { x: event.clientX, y: event.clientY };
        pointers.set(event.pointerId, next);
        if (pointers.size === 2 && pinch) {
            const [a, b] = [...pointers.values()];
            view.hfov = pinch.hfov * pinch.distance / Math.max(Math.hypot(a.x - b.x, a.y - b.y), 1);
        } else if (pointers.size === 1) {
            view.yaw -= (next.x - last.x) * view.hfov / stage.clientWidth;
            view.pitch += (next.y - last.y) * camera.fov / stage.clientHeight;
        }
        clampView();
        state.dirty = true;
        interacted();
    });
    const release = (event) => {
        pointers.delete(event.pointerId);
        if (pointers.size < 2) pinch = null;
        if (!pointers.size) stage.classList.remove('is-dragging');
    };
    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);
    // Plain wheel scrolls the page; zoom needs Ctrl (trackpad pinch also arrives as Ctrl+wheel).
    stage.addEventListener('wheel', (event) => {
        if (state.busy || !event.ctrlKey) return;
        event.preventDefault();
        view.hfov *= 1 + Math.max(-0.2, Math.min(0.2, event.deltaY * 0.001));
        clampView();
        state.dirty = true;
        interacted();
    }, { passive: false });
    stage.addEventListener('keydown', (event) => {
        if (event.target !== stage || state.busy) return;
        const step = { ArrowLeft: [-5, 0, 1], ArrowRight: [5, 0, 1], ArrowUp: [0, 5, 1], ArrowDown: [0, -5, 1], '+': [0, 0, 0.9], '=': [0, 0, 0.9], '-': [0, 0, 1.1] }[event.key];
        if (!step) return;
        event.preventDefault();
        view.yaw += step[0];
        view.pitch += step[1];
        view.hfov *= step[2];
        clampView();
        state.dirty = true;
        interacted();
    });

    function showFallback() {
        stage.hidden = true;
        els.areas.hidden = true;
        els.fallback.hidden = false;
    }

    resize();
    els.hint.hidden = false;
    show(tour.start, spots.get(tour.start).yaw);
}

function createRenderer() {
    try {
        const canvas = document.createElement('canvas');
        if (!canvas.getContext('webgl2') && !canvas.getContext('webgl')) return null;
        return new WebGLRenderer({ antialias: false, powerPreference: 'high-performance' });
    } catch {
        return null;
    }
}
