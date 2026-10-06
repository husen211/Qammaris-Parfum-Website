// Trim only proven white/transparent edges. Photography and edge-touching
// products keep the entire original image.
export function whiteMarginBounds({ data, width, height }) {
    if (width < 8 || height < 8 || data.length !== width * height * 4) return null;
    const blank = (x, y) => {
        const i = (y * width + x) * 4;
        return data[i + 3] < 16 || Math.min(data[i], data[i + 1], data[i + 2]) >= 247;
    };
    for (let x = 0; x < width; x++) {
        if (!blank(x, 0) || !blank(x, height - 1)) return null;
    }
    for (let y = 0; y < height; y++) {
        if (!blank(0, y) || !blank(width - 1, y)) return null;
    }
    let left = width;
    let top = height;
    let right = -1;
    let bottom = -1;
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (!blank(x, y)) {
                left = Math.min(left, x);
                top = Math.min(top, y);
                right = Math.max(right, x);
                bottom = Math.max(bottom, y);
            }
        }
    }
    if (right < 0 || right - left < width * .12 || bottom - top < height * .12) return null;
    // Preserve faint glass edges/shadows and limit the adjustment to at most 2x.
    const margin = Math.max(3, Math.ceil(Math.max(width, height) * .05));
    const cropWidth = Math.min(width, Math.max(width / 2, right - left + 1 + margin * 2));
    const cropHeight = Math.min(height, Math.max(height / 2, bottom - top + 1 + margin * 2));
    const x = Math.max(0, Math.min(width - cropWidth, (left + right + 1 - cropWidth) / 2));
    const y = Math.max(0, Math.min(height - cropHeight, (top + bottom + 1 - cropHeight) / 2));
    if (cropWidth > width * .96 && cropHeight > height * .96) return null;
    return { x, y, width: cropWidth, height: cropHeight };
}
