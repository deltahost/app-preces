import * as Speech from 'expo-speech';
import { createAudioPlayer, setAudioModeAsync, type AudioPlayer, type AudioStatus } from 'expo-audio';

export type NarrationVoice = 'female' | 'male';
export type NarrationLocale = 'pt-BR' | 'en-US' | 'es-ES' | 'fr-FR' | 'it-IT';
export type PlaybackPhase = 'idle' | 'loading' | 'playing' | 'error';

export const NARRATION_PROFILES = {
  male: {
    label: 'Remy',
    azureVoice: 'fr-FR-RemyMultilingualNeural',
    language: 'pt-BR',
    rate: '-10%',
    localPitch: 0.92,
  },
  female: {
    label: 'Thalita',
    azureVoice: 'pt-BR-ThalitaMultilingualNeural',
    language: 'pt-BR',
    rate: '-10%',
    localPitch: 1,
  },
} as const;

export const edgeVoiceNames: Record<NarrationLocale, Record<NarrationVoice, string>> = {
  'pt-BR': { female: NARRATION_PROFILES.female.azureVoice, male: NARRATION_PROFILES.male.azureVoice },
  'en-US': { female: NARRATION_PROFILES.female.azureVoice, male: NARRATION_PROFILES.male.azureVoice },
  'es-ES': { female: NARRATION_PROFILES.female.azureVoice, male: NARRATION_PROFILES.male.azureVoice },
  'fr-FR': { female: NARRATION_PROFILES.female.azureVoice, male: NARRATION_PROFILES.male.azureVoice },
  'it-IT': { female: NARRATION_PROFILES.female.azureVoice, male: NARRATION_PROFILES.male.azureVoice },
};

export async function speakOnDevice(text: string, voice: NarrationVoice = 'female', locale: NarrationLocale = 'pt-BR', onPhase?: (phase: PlaybackPhase) => void) {
  const profile = NARRATION_PROFILES[voice];
  await Speech.stop();
  onPhase?.('loading');
  await new Promise<void>((resolve, reject) => {
    Speech.speak(text, {
      language: locale,
      pitch: profile.localPitch,
      rate: 0.9,
      onStart: () => { onPhase?.('playing'); resolve(); },
      onDone: () => onPhase?.('idle'),
      onStopped: () => onPhase?.('idle'),
      onError: error => { onPhase?.('error'); reject(error); },
    });
  });
}

// Contrato para o backend de produção. Nunca coloque credenciais do Azure/Edge no app.
export async function requestPremiumNarration(apiUrl: string, token: string | undefined, text: string, voice: NarrationVoice, locale: NarrationLocale) {
  const profile = NARRATION_PROFILES[voice];
  const response = await fetch(`${apiUrl}/v1/tts`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: JSON.stringify({ text, locale, gender: voice, voice: profile.azureVoice, rate: profile.rate, delivery: 'narrative', format: 'audio-24khz-48kbitrate-mono-mp3' }),
  });
  if (!response.ok) throw new Error('Não foi possível gerar a narração.');
  return response.json() as Promise<{ audioUrl: string; expiresAt: string }>;
}

let activePlayer: AudioPlayer | null = null;
let activeSubscription: { remove: () => void } | null = null;

export async function playAudioUrl(audioUrl: string, onPhase?: (phase: PlaybackPhase) => void) {
  await Speech.stop();
  await setAudioModeAsync({ playsInSilentMode: true, interruptionMode: 'doNotMix' });
  activeSubscription?.remove();
  activePlayer?.remove();
  onPhase?.('loading');

  const player = createAudioPlayer({ uri: audioUrl }, { downloadFirst: false, updateInterval: 250 });
  activePlayer = player;

  await new Promise<void>((resolve, reject) => {
    let started = false;
    const timeout = setTimeout(() => {
      if (started) return;
      onPhase?.('error');
      reject(new Error('Tempo esgotado ao carregar o áudio.'));
    }, 15000);

    activeSubscription = player.addListener('playbackStatusUpdate', (status: AudioStatus) => {
      if (status.error) {
        clearTimeout(timeout);
        onPhase?.('error');
        if (!started) reject(new Error(status.error));
        return;
      }
      if (status.playing) {
        onPhase?.('playing');
        if (!started) {
          started = true;
          clearTimeout(timeout);
          resolve();
        }
      } else if (status.didJustFinish) {
        onPhase?.('idle');
      } else if (status.isBuffering && !started) {
        onPhase?.('loading');
      }
    });

    player.play();
  });
}

export async function speakNarration(text: string, voice: NarrationVoice = 'female', locale: NarrationLocale = 'pt-BR', onPhase?: (phase: PlaybackPhase) => void) {
  const apiUrl = process.env.EXPO_PUBLIC_TTS_API_URL?.replace(/\/$/, '');
  if (!apiUrl) return speakOnDevice(text, voice, locale, onPhase);

  try {
    onPhase?.('loading');
    const demoToken = process.env.EXPO_PUBLIC_TTS_API_TOKEN;
    const result = await requestPremiumNarration(apiUrl, demoToken, text, voice, locale);
    await playAudioUrl(result.audioUrl, onPhase);
  } catch {
    await speakOnDevice(text, voice, locale, onPhase);
  }
}
