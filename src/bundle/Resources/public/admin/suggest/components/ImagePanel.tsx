import React from 'react';

import { Button } from '../../shared/ids';
import { IMAGE_RATIOS } from '../state';
import type { ImageState } from '../state';

interface ImagePanelProps {
    image: ImageState;
    onRatio: (ratio: string) => void;
    onGenerate: () => void;
    onCancel: () => void;
    disabled: boolean;
}

/** Progress copy by elapsed time: the first seconds are the request, the rest is the rendering. */
function progressText(elapsed: number): string {
    if (elapsed < 5) {
        return 'Sending the request…';
    }
    if (elapsed < 12) {
        return 'Generating the image…';
    }
    if (elapsed < 22) {
        return 'Rendering the details…';
    }

    return 'Almost there…';
}

/** Generating a picture for an image field, from the description typed above. */
export function ImagePanel({ image, onRatio, onGenerate, onCancel, disabled }: ImagePanelProps) {
    const loading = image.status === 'loading';

    return (
        <div className="masilia-suggest__section masilia-suggest__imagepanel">
            <span className="masilia-suggest__label" id="masilia-suggest-ratio-label">Generate a picture</span>
            <p className="masilia-suggest__hint">Uses the description typed above. The picture replaces the current image only when you apply it.</p>
            <div className="masilia-suggest__ratios" role="radiogroup" aria-labelledby="masilia-suggest-ratio-label">
                {IMAGE_RATIOS.map((ratio) => (
                    <Button
                        key={ratio}
                        type={image.ratio === ratio ? 'primary' : 'secondary'}
                        size="small"
                        disabled={disabled || loading}
                        onClick={() => onRatio(ratio)}
                        extraAria={{ 'aria-checked': image.ratio === ratio, role: 'radio' }}
                    >
                        {ratio}
                    </Button>
                ))}
            </div>
            {loading ? (
                <p className="masilia-suggest__progress" role="status">
                    <span>{progressText(image.elapsed)}</span>
                    <span>{image.elapsed}s</span>
                    <button type="button" className="masilia-suggest__link" onClick={onCancel}>Cancel</button>
                </p>
            ) : (
                <Button type="secondary" icon="image" disabled={disabled} onClick={onGenerate}>
                    Generate image
                </Button>
            )}
        </div>
    );
}
