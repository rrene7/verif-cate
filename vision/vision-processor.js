window.VerifCateVision = (() => {
    const CARD_RATIO = 85.60 / 53.98;

    const loadImage = (url) => new Promise((resolve, reject) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('No se pudo cargar la imagen.'));
        image.src = url;
    });

    const rotateMat = (source, turns) => {
        if (turns === 0) {
            return source.clone();
        }

        const output = new cv.Mat();

        if (turns === 1) {
            cv.rotate(source, output, cv.ROTATE_90_CLOCKWISE);
        } else if (turns === 2) {
            cv.rotate(source, output, cv.ROTATE_180);
        } else {
            cv.rotate(source, output, cv.ROTATE_90_COUNTERCLOCKWISE);
        }

        return output;
    };

    const candidateScore = (rect, contourArea, imageArea) => {
        const width = Math.max(rect.width, rect.height);
        const height = Math.min(rect.width, rect.height);

        if (height <= 0) {
            return 0;
        }

        const ratio = width / height;
        const ratioDifference = Math.abs(ratio - CARD_RATIO) / CARD_RATIO;
        const ratioScore = Math.max(0, 1 - ratioDifference);
        const areaScore = contourArea / imageArea;

        return (areaScore * 0.75) + (ratioScore * 0.25);
    };

    const findCardCandidate = (source) => {
        const gray = new cv.Mat();
        const blur = new cv.Mat();
        const brightMask = new cv.Mat();
        const edgeMask = new cv.Mat();
        const combined = new cv.Mat();
        const contours = new cv.MatVector();
        const hierarchy = new cv.Mat();

        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, blur, new cv.Size(5, 5), 0);

        /*
         * El carné suele ser más claro que el fondo. Esta máscara permite
         * detectarlo aunque las esquinas sean redondeadas o el borde no sea
         * un cuadrilátero perfecto.
         */
        cv.threshold(
            blur,
            brightMask,
            115,
            255,
            cv.THRESH_BINARY
        );

        const closeKernel = cv.getStructuringElement(
            cv.MORPH_RECT,
            new cv.Size(15, 15)
        );

        cv.morphologyEx(
            brightMask,
            brightMask,
            cv.MORPH_CLOSE,
            closeKernel,
            new cv.Point(-1, -1),
            2
        );

        cv.Canny(blur, edgeMask, 35, 140);

        const edgeKernel = cv.getStructuringElement(
            cv.MORPH_RECT,
            new cv.Size(7, 7)
        );

        cv.morphologyEx(
            edgeMask,
            edgeMask,
            cv.MORPH_CLOSE,
            edgeKernel,
            new cv.Point(-1, -1),
            2
        );

        cv.bitwise_or(brightMask, edgeMask, combined);

        cv.findContours(
            combined,
            contours,
            hierarchy,
            cv.RETR_EXTERNAL,
            cv.CHAIN_APPROX_SIMPLE
        );

        const imageArea = source.rows * source.cols;
        let best = null;

        for (let index = 0; index < contours.size(); index++) {
            const contour = contours.get(index);
            const area = cv.contourArea(contour);

            if (area < imageArea * 0.015) {
                contour.delete();
                continue;
            }

            const rect = cv.boundingRect(contour);
            const score = candidateScore(rect, area, imageArea);

            if (
                rect.width >= source.cols * 0.12 &&
                rect.height >= source.rows * 0.12 &&
                (!best || score > best.score)
            ) {
                best = {
                    rect,
                    area,
                    score,
                    coverage: (area / imageArea) * 100
                };
            }

            contour.delete();
        }

        gray.delete();
        blur.delete();
        brightMask.delete();
        edgeMask.delete();
        combined.delete();
        contours.delete();
        hierarchy.delete();
        closeKernel.delete();
        edgeKernel.delete();

        return best;
    };

    const detectAcrossRotations = (source) => {
        let best = null;

        for (let turns = 0; turns < 4; turns++) {
            const rotated = rotateMat(source, turns);
            const candidate = findCardCandidate(rotated);

            if (candidate && (!best || candidate.score > best.candidate.score)) {
                best?.rotated.delete();
                best = {
                    turns,
                    rotated,
                    candidate
                };
            } else {
                rotated.delete();
            }
        }

        return best;
    };

    const cropWithMargin = (source, rect, marginPercent = 0.025) => {
        const marginX = Math.round(rect.width * marginPercent);
        const marginY = Math.round(rect.height * marginPercent);

        const x = Math.max(0, rect.x - marginX);
        const y = Math.max(0, rect.y - marginY);
        const right = Math.min(source.cols, rect.x + rect.width + marginX);
        const bottom = Math.min(source.rows, rect.y + rect.height + marginY);

        const width = Math.max(1, right - x);
        const height = Math.max(1, bottom - y);

        const roi = source.roi(new cv.Rect(x, y, width, height));
        const cropped = roi.clone();
        roi.delete();

        /*
         * Asegura orientación horizontal del carné después del recorte.
         */
        if (cropped.rows > cropped.cols) {
            const horizontal = new cv.Mat();
            cv.rotate(cropped, horizontal, cv.ROTATE_90_CLOCKWISE);
            cropped.delete();
            return horizontal;
        }

        return cropped;
    };

    const normalizeCardSize = (source) => {
        const targetWidth = 1600;
        const targetHeight = Math.round(targetWidth / CARD_RATIO);
        const output = new cv.Mat();

        cv.resize(
            source,
            output,
            new cv.Size(targetWidth, targetHeight),
            0,
            0,
            cv.INTER_CUBIC
        );

        return output;
    };

    const enhanceForOcr = (source) => {
        const gray = new cv.Mat();
        const blurred = new cv.Mat();
        const enhanced = new cv.Mat();

        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(
            gray,
            blurred,
            new cv.Size(3, 3),
            0,
            0,
            cv.BORDER_DEFAULT
        );

        cv.adaptiveThreshold(
            blurred,
            enhanced,
            255,
            cv.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv.THRESH_BINARY,
            31,
            9
        );

        gray.delete();
        blurred.delete();

        return enhanced;
    };

    const matToDataUrl = (mat) => {
        const canvas = document.createElement('canvas');
        cv.imshow(canvas, mat);
        return canvas.toDataURL('image/png', 1);
    };

    const blurScore = (source) => {
        const gray = new cv.Mat();
        const laplacian = new cv.Mat();
        const mean = new cv.Mat();
        const stddev = new cv.Mat();

        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        cv.Laplacian(gray, laplacian, cv.CV_64F);
        cv.meanStdDev(laplacian, mean, stddev);

        const score = Math.pow(stddev.doubleAt(0, 0), 2);

        gray.delete();
        laplacian.delete();
        mean.delete();
        stddev.delete();

        return Math.round(score);
    };

    const glareScore = (source) => {
        const gray = new cv.Mat();
        const mask = new cv.Mat();

        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        cv.threshold(gray, mask, 245, 255, cv.THRESH_BINARY);

        const brightPixels = cv.countNonZero(mask);
        const score = Math.round(
            (brightPixels / (source.rows * source.cols)) * 100
        );

        gray.delete();
        mask.delete();

        return score;
    };

    const process = async (imageUrl) => {
        const image = await loadImage(imageUrl);
        const source = cv.imread(image);
        let detection = null;
        let rawCrop = null;
        let normalized = null;
        let enhanced = null;

        try {
            detection = detectAcrossRotations(source);

            if (!detection) {
                throw new Error(
                    'No se detectó el carné. Procure que esté completo, con fondo uniforme y los cuatro bordes visibles.'
                );
            }

            rawCrop = cropWithMargin(
                detection.rotated,
                detection.candidate.rect
            );

            normalized = normalizeCardSize(rawCrop);
            enhanced = enhanceForOcr(normalized);

            const coverage = Math.round(detection.candidate.coverage);
            const blur = blurScore(normalized);
            const glare = glareScore(normalized);

            return {
                croppedUrl: matToDataUrl(normalized),
                enhancedUrl: matToDataUrl(enhanced),
                rotationDegrees: detection.turns * 90,
                quality: {
                    coverage,
                    blur,
                    glare,
                    coverageOk: coverage >= 12,
                    blurOk: blur >= 60,
                    glareOk: glare <= 12
                }
            };
        } finally {
            source.delete();
            detection?.rotated.delete();
            rawCrop?.delete();
            normalized?.delete();
            enhanced?.delete();
        }
    };

    return { process };
})();
