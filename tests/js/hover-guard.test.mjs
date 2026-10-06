import assert from 'node:assert/strict';
import test from 'node:test';
import postcss from 'postcss';
import { activatesHover, guardHover } from '../../tools/frontend/hover-guard.mjs';

test('guards positive hover without treating base states, escapes or attributes as hover', () => {
    for (const selector of ['.card:hover', ':is(.group:hover) a', '.item:not(:not(:hover))']) {
        assert.equal(activatesHover(selector), true);
    }
    for (const selector of ['.item:not(:hover)', '.item:not(:is(.group:hover))',
        '.hover\\:text-gold', '[data-label=":hover"]', '.item:hovering']) {
        assert.equal(activatesHover(selector), false);
    }
});

test('splits mixed hover/focus selectors so keyboard feedback remains unconditional', () => {
    const root = postcss.parse(guardHover('.item:hover, .item:focus-visible {color:gold}'));
    assert.equal(root.nodes[0].selector, '.item:focus-visible');
    assert.equal(root.nodes[1].params, '(hover: hover) and (pointer: fine)');
    assert.equal(root.nodes[1].nodes[0].selector, '.item:hover');
});

test('retains layers/supports/negated dropdown visibility and is idempotent', () => {
    const source = '@layer components {@supports(display:grid){.item:hover{color:gold}} .dropdown:not(:hover){display:none}}';
    const guarded = guardHover(source);
    assert.ok(guarded.includes('.dropdown:not(:hover){display:none}'));
    assert.equal(guardHover(guarded), guarded);
});

test('a comma-separated media alternative does not qualify as the mouse guard', () => {
    const root = postcss.parse(guardHover('@media (hover: hover), (pointer: fine){.item:hover{color:gold}}'));
    assert.equal(root.nodes[0].nodes[0].params, '(hover: hover) and (pointer: fine)');
});
