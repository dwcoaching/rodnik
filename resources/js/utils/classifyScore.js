// Keep in sync with App\Models\Spring::WATER_SCORE_THRESHOLD.
export const WATER_SCORE_THRESHOLD = 0.4;

export default function classifyScore(score) {
    if (score >= WATER_SCORE_THRESHOLD) {
        return 'good';
    }

    if (score <= -WATER_SCORE_THRESHOLD) {
        return 'bad';
    }

    return 'uncertain';
}
