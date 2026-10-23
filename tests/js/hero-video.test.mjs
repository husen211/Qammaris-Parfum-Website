import assert from 'node:assert/strict';
import test from 'node:test';
import { heroVideoAllowed, startVisibleHeroVideos } from '../../resources/js/ui/hero-video.js';

const video = (visible) => ({
    dataset: { src: '/images/hero/hero-1.mp4' },
    src: '',
    plays: 0,
    getClientRects: () => (visible ? [{}] : []),
    play() {
        this.plays++;
        return Promise.resolve();
    },
});

test('only a visible column gets its video, once', () => {
    const shown = video(true);
    const hidden = video(false);
    startVisibleHeroVideos([shown, hidden]);
    startVisibleHeroVideos([shown, hidden]);

    assert.equal(shown.src, '/images/hero/hero-1.mp4');
    assert.equal(shown.plays, 1);
    assert.equal(hidden.src, '');
    assert.equal(hidden.plays, 0);
});

test('reduced motion and data saver keep the poster', () => {
    assert.equal(heroVideoAllowed({ reducedMotion: false, saveData: false }), true);
    assert.equal(heroVideoAllowed({ reducedMotion: true, saveData: false }), false);
    assert.equal(heroVideoAllowed({ reducedMotion: false, saveData: true }), false);
});
