// Shared by the wizard and focused regression tests; the server repeats these rules.
export function sweetnessBranch(answers) {
    if (answers.avoid_sweet) return { skipped: true, value: 'none' };
    if (answers.avoid?.includes('gourmand')) return { skipped: true, value: 'any' };
    return { skipped: false, value: answers.sweetness };
}

export function activeQuestionKeys(keys, answers) {
    return keys.filter(key => key !== 'sweetness' || !sweetnessBranch(answers).skipped);
}

export function effectiveAnswers(answers) {
    return { ...answers, sweetness: sweetnessBranch(answers).value };
}

export function validBudget(minimum, maximum, unbounded) {
    return Number.isInteger(minimum) && minimum >= 0 && minimum <= 99999999
        && (unbounded || (Number.isInteger(maximum) && maximum >= 1 && maximum <= 99999999 && maximum >= minimum));
}
