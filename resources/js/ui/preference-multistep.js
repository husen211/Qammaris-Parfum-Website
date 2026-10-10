// Adapted from 21st.dev Multistep Form (arihantcodes_1f7b8c4d, demo 4883).
// Preserve exit-before-enter timing without converting the Blade form to React.
export const PREFERENCE_STAGES = [
    { label: 'Budget', keys: ['budget_max'] },
    { label: 'Pemakaian', keys: ['use', 'environment', 'time'] },
    { label: 'Aroma', keys: ['likes', 'avoid'] },
    { label: 'Karakter', keys: ['sweetness', 'projection', 'longevity'] },
    { label: 'Pilihan', keys: ['gender', 'favorite_product_id'] },
    { label: 'Ringkasan', keys: ['summary'] },
];

export function preferenceStage(key) {
    return PREFERENCE_STAGES.findIndex(stage => stage.keys.includes(key));
}

export function stageDestination(index, available) {
    return PREFERENCE_STAGES[index]?.keys.find(key => key === 'summary' || available.includes(key));
}

export async function transitionPreferencePanel({ frame, outgoing, render, direction = 1, reducedMotion = false }) {
    if (reducedMotion || !outgoing?.animate || !frame.animate) {
        render();
        return;
    }

    const previousHeight = frame.getBoundingClientRect().height;
    const frameSpacing = previousHeight - outgoing.getBoundingClientRect().height;
    const animations = [];
    frame.style.height = `${previousHeight}px`;
    outgoing.inert = true;
    try {
        const exit = outgoing.animate([
            { opacity: 1, transform: 'translateX(0)' },
            { opacity: 0, transform: `translateX(${-50 * direction}px)` },
        ], { duration: 200, easing: 'ease-in', fill: 'forwards' });
        animations.push(exit);
        await exit.finished.catch(() => {});

        const incoming = render();
        exit.cancel();
        const nextHeight = incoming.getBoundingClientRect().height + frameSpacing;
        const resize = frame.animate([{ height: `${previousHeight}px` }, { height: `${nextHeight}px` }],
            { duration: 300, easing: 'ease-out', fill: 'forwards' });
        const enter = incoming.animate([
            { opacity: 0, transform: `translateX(${50 * direction}px)` },
            { opacity: 1, transform: 'translateX(0)' },
        ], { duration: 300, easing: 'ease-out', fill: 'both' });
        animations.push(resize, enter);
        await Promise.all([resize.finished, enter.finished]).catch(() => {});
    } finally {
        animations.forEach(animation => animation.cancel());
        frame.style.height = '';
        outgoing.inert = outgoing.hidden;
    }
}
