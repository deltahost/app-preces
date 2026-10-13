import * as Speech from 'expo-speech';
import { createAudioPlayer, setAudioModeAsync, type AudioPlayer, type AudioStatus } from 'expo-audio';

export type NarrationVoice = 'female' | 'male';
export type NarrationLocale = 'pt-BR' | 'en-US' | 'es-ES' | 'fr-FR' | 'it-IT';
export type PlaybackPhase = 'idle' | 'loading' | 'playing' | 'error';
const DEFAULT_TTS_API_URL = 'https://preces-edge-tts.onrender.com';
const TTS_WAKE_TIMEOUT_MS = 60_000;

export class NarrationCancelledError extends Error {
  constructor() {
    super('Narração interrompida.');
    this.name = 'NarrationCancelledError';
  }
}

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
  await stopNarration();
  activePhaseCallback = onPhase ?? null;
  onPhase?.('loading');
  await new Promise<void>((resolve, reject) => {
    Speech.speak(text, {
      language: locale,
      pitch: profile.localPitch,
      rate: 0.9,
      onStart: () => { onPhase?.('playing'); resolve(); },
      onDone: () => { onPhase?.('idle'); activePhaseCallback = null; },
      onStopped: () => { onPhase?.('idle'); activePhaseCallback = null; },
      onError: error => { onPhase?.('error'); activePhaseCallback = null; reject(error); },
    });
  });
}

// Contrato para o backend de produção. Nunca coloque credenciais do Azure/Edge no app.
export async function requestPremiumNarration(apiUrl: string, token: string | undefined, text: string, voice: NarrationVoice, locale: NarrationLocale, signal?: AbortSignal) {
  const profile = NARRATION_PROFILES[voice];
  const response = await fetch(`${apiUrl}/v1/tts`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: JSON.stringify({ text, locale, gender: voice, voice: profile.azureVoice, rate: profile.rate, delivery: 'narrative', format: 'audio-24khz-48kbitrate-mono-mp3' }),
    signal,
  });
  if (!response.ok) throw new Error('Não foi possível gerar a narração.');
  return response.json() as Promise<{ audioUrl: string; expiresAt: string }>;
}

let activePlayer: AudioPlayer | null = null;
let activeSubscription: { remove: () => void } | null = null;
let activePhaseCallback: ((phase: PlaybackPhase) => void) | null = null;
let activeCancel: (() => void) | null = null;

function releaseActivePlayer() {
  activeSubscription?.remove();
  activeSubscription = null;
  if (activePlayer) {
    activePlayer.pause();
    activePlayer.remove();
    activePlayer = null;
  }
}

export async function stopNarration() {
  const notify = activePhaseCallback;
  const cancel = activeCancel;
  activePhaseCallback = null;
  activeCancel = null;
  releaseActivePlayer();
  await Speech.stop();
  notify?.('idle');
  cancel?.();
}

export async function playAudioUrl(audioUrl: string, onPhase?: (phase: PlaybackPhase) => void) {
  await stopNarration();
  await setAudioModeAsync({ playsInSilentMode: true, interruptionMode: 'doNotMix' });
  activePhaseCallback = onPhase ?? null;
  onPhase?.('loading');

  const player = createAudioPlayer({ uri: audioUrl }, { downloadFirst: false, updateInterval: 250 });
  activePlayer = player;

  await new Promise<void>((resolve, reject) => {
    let started = false;
    let settled = false;
    activeCancel = () => {
      if (settled) return;
      settled = true;
      clearTimeout(timeout);
      reject(new NarrationCancelledError());
    };
    const timeout = setTimeout(() => {
      if (started || settled) return;
      settled = true;
      activeCancel = null;
      releaseActivePlayer();
      activePhaseCallback = null;
      onPhase?.('error');
      reject(new Error('Tempo esgotado ao carregar o áudio.'));
    }, 15000);

    activeSubscription = player.addListener('playbackStatusUpdate', (status: AudioStatus) => {
      if (status.error) {
        clearTimeout(timeout);
        activeCancel = null;
        releaseActivePlayer();
        activePhaseCallback = null;
        onPhase?.('error');
        if (!started && !settled) {
          settled = true;
          reject(new Error(status.error));
        }
        return;
      }
      if (status.playing) {
        onPhase?.('playing');
        if (!started) {
          started = true;
          settled = true;
          activeCancel = null;
          clearTimeout(timeout);
          resolve();
        }
      } else if (status.didJustFinish) {
        onPhase?.('idle');
        activePhaseCallback = null;
        releaseActivePlayer();
      } else if (status.isBuffering && !started) {
        onPhase?.('loading');
      }
    });

    player.play();
  });
}

export async function speakNarration(text: string, voice: NarrationVoice = 'female', locale: NarrationLocale = 'pt-BR', onPhase?: (phase: PlaybackPhase) => void) {
  const apiUrl = (process.env.EXPO_PUBLIC_TTS_API_URL || DEFAULT_TTS_API_URL).replace(/\/$/, '');
  await stopNarration();
  activePhaseCallback = onPhase ?? null;
  onPhase?.('loading');
  const controller = new AbortController();
  let cancelled = false;
  const cancelRequest = () => { cancelled = true; controller.abort(); };
  activeCancel = cancelRequest;
  const timeout = setTimeout(() => controller.abort(), TTS_WAKE_TIMEOUT_MS);

  try {
    const demoToken = process.env.EXPO_PUBLIC_TTS_API_TOKEN;
    const result = await requestPremiumNarration(apiUrl, demoToken, text, voice, locale, controller.signal);
    clearTimeout(timeout);
    if (activeCancel === cancelRequest) activeCancel = null;
    await playAudioUrl(result.audioUrl, onPhase);
  } catch {
    clearTimeout(timeout);
    if (activeCancel === cancelRequest) activeCancel = null;
    if (cancelled) throw new NarrationCancelledError();
    await speakOnDevice(text, voice, locale, onPhase);
  }
}
