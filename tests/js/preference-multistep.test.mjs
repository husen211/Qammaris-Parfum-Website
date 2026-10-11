import test from 'node:test';
import assert from 'node:assert/strict';
import { preferenceStage, stageDestination, transitionPreferencePanel } from '../../resources/js/ui/preference-multistep.js';

test('group destination follows active questions when sweetness is skipped', () => {
    assert.equal(stageDestination(preferenceStage('projection'), ['budget_max','avoid','projection','longevity']), 'projection');
    assert.equal(stageDestination(preferenceStage('sweetness'), ['sweetness','projection']), 'sweetness');
    assert.equal(stageDestination(preferenceStage('summary'), []), 'summary');
});

test('panel waits for exit, then enters backwards and clears temporary height', async () => {
    let finishExit;
    let rendered = false;
    const calls = [];
    const animation = finished => ({ finished, cancel() { calls.push('cancel'); } });
    const outgoing = { hidden:false, inert:false, getBoundingClientRect:()=>({height:248}), animate(frames, options) { calls.push({frames,options}); return animation(new Promise(resolve => { finishExit=resolve; })); } };
    const incoming = { getBoundingClientRect:()=>({height:140}), animate(frames,options) { calls.push({frames,options}); return animation(Promise.resolve()); } };
    const frame = { style:{}, getBoundingClientRect:()=>({height:260}), animate(frames,options) { calls.push({frames,options}); return animation(Promise.resolve()); } };
    const transition = transitionPreferencePanel({frame,outgoing,direction:-1,render:()=>{rendered=true;outgoing.hidden=true;return incoming;}});
    assert.equal(rendered,false);
    assert.equal(outgoing.inert,true);
    assert.equal(frame.style.height,'260px');
    assert.equal(calls[0].frames[1].transform,'translateX(50px)');
    assert.equal(calls[0].options.duration,200);
    finishExit(); await transition;
    assert.equal(rendered,true);
    const motions=calls.filter(call=>typeof call==='object');
    assert.equal(motions[1].frames[1].height,'152px');
    assert.equal(motions[2].frames[0].transform,'translateX(-50px)');
    assert.equal(motions[2].options.duration,300);
    assert.equal(frame.style.height,'');
    assert.equal(outgoing.inert,true);
});

test('reduced motion and unavailable animation APIs render immediately', async () => {
    let renders=0;
    const animate=()=>{throw new Error('Animation should not run');};
    await transitionPreferencePanel({frame:{animate},outgoing:{animate},reducedMotion:true,render:()=>{renders++;}});
    await transitionPreferencePanel({frame:{},outgoing:{},render:()=>{renders++;}});
    assert.equal(renders,2);
});

test('animation cancellation cannot strand a visible panel inert or at fixed height', async () => {
    const canceled=()=>({finished:Promise.reject(new Error('Canceled')),cancel(){}});
    const outgoing={hidden:false,inert:false,getBoundingClientRect:()=>({height:138}),animate:canceled};
    const frame={style:{},getBoundingClientRect:()=>({height:150}),animate:canceled};
    const incoming={getBoundingClientRect:()=>({height:200}),animate:canceled};
    await transitionPreferencePanel({frame,outgoing,render:()=>{outgoing.hidden=true;return incoming;}});
    assert.equal(frame.style.height,'');
    assert.equal(outgoing.inert,true);
});
