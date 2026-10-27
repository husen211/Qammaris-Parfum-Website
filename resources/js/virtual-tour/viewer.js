// TOUR-01: full-screen walkable 360 viewer on the existing three.js dependency.
// Yaw follows the panorama (0 = image centre, positive to the right); see config/store_virtual_tour.php.
import { LinearFilter, Mesh, MeshBasicMaterial, PerspectiveCamera, Scene, SphereGeometry, SRGBColorSpace, TextureLoader, Vector3, WebGLRenderer } from 'three';

const DEG = Math.PI / 180;
// Zoom works like a phone camera: 0,5× is the widest view, 1× doubles the focal length.
// The widest view is limited horizontally on landscape screens and vertically on tall phones.
const MAX_HFOV = 115;
const MAX_VFOV = 128;
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 3;
const START_PITCH = -2;

export function createViewer(dialog, tour) {
    const $ = (name) => dialog.querySelector(`[data-tour-${name}]`);
    const els = {
        stage: $('stage'), canvas: $('canvas'), hotspots: $('hotspots'), status: $('status'), error: $('error'),
        retry: $('retry'), hint: $('hint'), intro: $('intro'), name: $('name'), count: $('count'), live: $('live'),
    };
    const stage = els.stage;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const spots = new Map(tour.spots.map((spot) => [spot.id, spot]));
    const order = tour.spots.map((spot) => spot.id);
    const abort = new AbortController();
    const on = (target, type, handler, options = {}) => target.addEventListener(type, handler, { ...options, signal: abort.signal });

    const renderer = new WebGLRenderer({ antialias: false, powerPreference: 'high-performance' });
    // The 6144 px panorama only where the GPU can hold it and the screen benefits; phones get 4096 px.
    const useLarge = renderer.capabilities.maxTextureSize >= 8192 && window.innerWidth >= 1024 && !navigator.connection?.saveData;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    els.canvas.appendChild(renderer.domElement);

    const scene = new Scene();
    const camera = new PerspectiveCamera(75, 1, 0.1, 100);
    const geometry = new SphereGeometry(10, 64, 32);
    geometry.scale(-1, 1, 1);
    const sphere = (renderOrder) => {
        const mesh = new Mesh(geometry, new MeshBasicMaterial({ transparent: true, depthTest: false, depthWrite: false }));
        mesh.renderOrder = renderOrder;
        mesh.visible = false;
        scene.add(mesh);
        return mesh;
    };
    let current = sphere(0);
    let incoming = sphere(1);

    const view = { yaw: 0, pitch: START_PITCH, zoom: MIN_ZOOM };
    const state = { spot: null, busy: false, dirty: true, interacted: false, retry: null, destroyed: false, raf: 0 };
    const tweens = [];
    const loader = new TextureLoader();
    const textures = new Map();

    // ---- textures ---------------------------------------------------------------------------
    function texture(id) {
        if (!textures.has(id)) {
            const spot = spots.get(id);
            textures.set(id, new Promise((resolve, reject) => loader.load(useLarge ? spot.images.large : spot.images.small, (map) => {
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
    const wideTan = () => Math.min(Math.tan((MAX_HFOV / 2) * DEG), Math.tan((MAX_VFOV / 2) * DEG) * aspect());
    const hfov = () => 2 * Math.atan(wideTan() * MIN_ZOOM / view.zoom) / DEG;
    const clampView = () => {
        view.pitch = Math.max(-85, Math.min(85, view.pitch));
        view.zoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, view.zoom));
    };

    function resize() {
        renderer.setSize(stage.clientWidth, stage.clientHeight, false);
        camera.aspect = aspect();
        state.dirty = true;
    }
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(stage);

    function render() {
        camera.fov = 2 * Math.atan(Math.tan((hfov() / 2) * DEG) / camera.aspect) / DEG;
        camera.updateProjectionMatrix();
        camera.lookAt(direction(view.yaw, view.pitch));
        renderer.render(scene, camera);
        placeArrows();
        syncZoom();
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
        state.raf = requestAnimationFrame(frame);
    }
    state.raf = requestAnimationFrame(frame);

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
            view.zoom = from.zoom + (target.zoom - from.zoom) * k;
        });
    }

    // ---- moving between spots -------------------------------------------------------------------
    async function show(id, arriveYaw, { walk = null } = {}) {
        if (state.busy) return;
        state.busy = true;
        const restZoom = state.spot ? view.zoom : MIN_ZOOM;
        const slow = setTimeout(() => setStatus('Memuat foto 360…'), 350);
        try {
            const [map] = await Promise.all([texture(id), walk ? animateView(walk, 650) : Promise.resolve()]);
            if (state.destroyed) return;
            clearTimeout(slow);
            setStatus(null);
            if (!state.spot) {
                // Entering the store: start close (like the cover photo), then open up to 0,5×.
                current.material.map = map;
                current.material.needsUpdate = true;
                current.visible = true;
                Object.assign(view, { yaw: arriveYaw, pitch: START_PITCH, zoom: reduceMotion ? MIN_ZOOM : introZoom() });
                els.intro.style.animationPlayState = 'paused';
                els.intro.classList.add('is-done');
            } else {
                // Rotate the next panorama so the arrival direction lines up with the current view,
                // fade it in, then hand over without a visible jump.
                incoming.material.map = map;
                incoming.material.needsUpdate = true;
                incoming.material.opacity = 0;
                incoming.rotation.y = (arriveYaw - view.yaw) * DEG;
                incoming.visible = true;
                await tween(450, (k) => { incoming.material.opacity = k; });
                if (state.destroyed) return;
                current.visible = false;
                [current, incoming] = [incoming, current];
                current.renderOrder = 0;
                incoming.renderOrder = 1;
                current.rotation.y = 0;
                view.yaw = arriveYaw;
            }
            state.spot = id;
            buildArrows();
            syncArea();
            state.busy = false;
            state.dirty = true;
            keepNear(id);
            await animateView({ yaw: view.yaw, pitch: START_PITCH, zoom: restZoom }, walk ? 650 : 1100);
            if (!state.interacted && !state.destroyed) els.hint.hidden = false;
        } catch {
            clearTimeout(slow);
            if (state.destroyed) return;
            setStatus(null);
            state.busy = false;
            showError(() => show(id, arriveYaw));
        }
    }

    // Zoom at which the panorama looks exactly like the intro photo as it is drawn right now
    // (square image, object-fit cover, scaled by its walk-in animation).
    function introZoom() {
        const box = stage.getBoundingClientRect();
        const size = els.intro.naturalWidth || 1400;
        const scale = els.intro.getBoundingClientRect().width / Math.max(box.width, 1);
        const cover = Math.max(box.width, box.height) / size * scale;
        const focal = size / 2 / Number(els.intro.dataset.tan);
        const tanVisible = box.width / 2 / (cover * focal);
        return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, wideTan() * MIN_ZOOM / tanVisible));
    }

    function walkTo(link) {
        show(link.to, link.arrive, { walk: { yaw: link.yaw, pitch: -6, zoom: Math.max(view.zoom * 2, 1) } });
    }

    function goTo(id) {
        if (id === state.spot || state.busy) return;
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
            on(button, 'click', () => walkTo(link));
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

    // ---- controls -----------------------------------------------------------------------------
    const areaButtons = [...dialog.querySelectorAll('[data-tour-goto]')];
    areaButtons.forEach((button) => on(button, 'click', () => goTo(button.dataset.tourGoto)));
    function syncArea() {
        const spot = spots.get(state.spot);
        areaButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.tourGoto === state.spot)));
        els.name.textContent = spot.name;
        els.count.textContent = `Area ${order.indexOf(state.spot) + 1} dari ${order.length}`;
        els.live.textContent = `Sekarang di area ${spot.name}`;
    }

    const zoomButtons = [...dialog.querySelectorAll('[data-tour-zoom]')];
    const formatZoom = (zoom) => `${String(Math.round(zoom * 10) / 10).replace('.', ',')}×`;
    zoomButtons.forEach((button) => on(button, 'click', () => {
        if (state.busy) return;
        animateView({ yaw: view.yaw, pitch: view.pitch, zoom: Number(button.dataset.tourZoom) }, 350);
        interacted();
    }));
    function syncZoom() {
        const active = view.zoom < 0.75 ? '0.5' : '1';
        for (const button of zoomButtons) {
            const pressed = button.dataset.tourZoom === active;
            const text = formatZoom(pressed ? view.zoom : Number(button.dataset.tourZoom));
            if (button.textContent !== text) button.textContent = text;
            button.setAttribute('aria-pressed', String(pressed));
        }
    }

    function setStatus(text) {
        els.status.hidden = !text;
        if (text) els.status.textContent = text;
    }
    function showError(retry) {
        state.retry = retry;
        els.error.hidden = false;
    }
    on(els.retry, 'click', () => {
        els.error.hidden = true;
        const retry = state.retry;
        state.retry = null;
        retry?.();
    });
    on(renderer.domElement, 'webglcontextlost', (event) => {
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
    on(stage, 'pointerdown', (event) => {
        if (event.target.closest('button')) return;
        try {
            stage.setPointerCapture(event.pointerId);
        } catch {
            // Capture is a convenience; dragging still works while the pointer stays on the stage.
        }
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        stage.classList.add('is-dragging');
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), zoom: view.zoom };
        }
    });
    on(stage, 'pointermove', (event) => {
        const last = pointers.get(event.pointerId);
        if (!last || state.busy) return;
        const next = { x: event.clientX, y: event.clientY };
        pointers.set(event.pointerId, next);
        if (pointers.size === 2 && pinch) {
            const [a, b] = [...pointers.values()];
            view.zoom = pinch.zoom * Math.hypot(a.x - b.x, a.y - b.y) / Math.max(pinch.distance, 1);
        } else if (pointers.size === 1) {
            view.yaw -= (next.x - last.x) * hfov() / stage.clientWidth;
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
    on(stage, 'pointerup', release);
    on(stage, 'pointercancel', release);
    on(stage, 'wheel', (event) => {
        event.preventDefault();
        if (state.busy) return;
        view.zoom *= 1 - Math.max(-0.25, Math.min(0.25, event.deltaY * 0.0015));
        clampView();
        state.dirty = true;
        interacted();
    }, { passive: false });
    on(stage, 'keydown', (event) => {
        if (event.target !== stage || state.busy) return;
        const step = { ArrowLeft: [-5, 0, 1], ArrowRight: [5, 0, 1], ArrowUp: [0, 5, 1], ArrowDown: [0, -5, 1], '+': [0, 0, 1.1], '=': [0, 0, 1.1], '-': [0, 0, 1 / 1.1] }[event.key];
        if (!step) return;
        event.preventDefault();
        view.yaw += step[0];
        view.pitch += step[1];
        view.zoom *= step[2];
        clampView();
        state.dirty = true;
        interacted();
    });

    resize();
    stage.focus({ preventScroll: true });
    show(tour.start, spots.get(tour.start).yaw);

    return {
        destroy() {
            state.destroyed = true;
            cancelAnimationFrame(state.raf);
            abort.abort();
            resizeObserver.disconnect();
            for (const promise of textures.values()) promise.then((map) => map.dispose(), () => {});
            textures.clear();
            geometry.dispose();
            current.material.dispose();
            incoming.material.dispose();
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
            els.hotspots.textContent = '';
            els.intro.classList.remove('is-done');
            els.intro.style.animationPlayState = '';
            els.hint.hidden = true;
            els.error.hidden = true;
            setStatus('Memuat foto 360…');
            const start = spots.get(tour.start);
            els.name.textContent = start.name;
            els.count.textContent = `Area 1 dari ${order.length}`;
            areaButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.tourGoto === tour.start)));
        },
    };
}
