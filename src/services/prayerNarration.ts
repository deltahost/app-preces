import AsyncStorage from '@react-native-async-storage/async-storage';
import { Prayer } from '../data';
import { NarrationCancelledError, PlaybackPhase, playAudioUrl, speakNarration } from './tts';

const FREE_NARRATION_KEY = '@preces/free-narration-date-v2';

export class DailyNarrationLimitError extends Error {
  constructor() {
    super('Limite diário de narração utilizado.');
    this.name = 'DailyNarrationLimitError';
  }
}

export async function playPrayerNarration(
  prayer: Prayer,
  premium: boolean,
  onPhase?: (phase: PlaybackPhase) => void,
) {
  const today = new Date().toLocaleDateString('en-CA');
  if (!premium && await AsyncStorage.getItem(FREE_NARRATION_KEY) === today) {
    throw new DailyNarrationLimitError();
  }

  if (prayer.audioUrl) {
    try {
      await playAudioUrl(prayer.audioUrl, onPhase);
    } catch (error) {
      if (error instanceof NarrationCancelledError) throw error;
      await speakNarration(prayer.text, 'female', 'pt-BR', onPhase);
    }
  } else {
    await speakNarration(prayer.text, 'female', 'pt-BR', onPhase);
  }

  if (!premium) await AsyncStorage.setItem(FREE_NARRATION_KEY, today);
}
