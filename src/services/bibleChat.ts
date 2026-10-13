import AsyncStorage from '@react-native-async-storage/async-storage';

const CONTENT_API_URL = (process.env.EXPO_PUBLIC_CONTENT_API_URL ?? 'https://preces.criandoconteudo.com.br/api').replace(/\/$/, '');
const INSTALLATION_ID_KEY = '@preces/installation-id-v1';

export class BibleChatError extends Error {
  constructor(message: string, public code?: string, public status?: number) {
    super(message);
    this.name = 'BibleChatError';
  }
}

async function installationId(): Promise<string> {
  const existing = await AsyncStorage.getItem(INSTALLATION_ID_KEY);
  if (existing) return existing;
  const created = `preces-${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
  await AsyncStorage.setItem(INSTALLATION_ID_KEY, created);
  return created;
}

export async function askBible(message: string, premium = false): Promise<string> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 45000);
  try {
    const response = await fetch(`${CONTENT_API_URL}/bible-chat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message, installationId: await installationId(), premium }),
      signal: controller.signal,
    });
    const responseText = await response.text();
    const payload = (() => {
      try { return JSON.parse(responseText) as { answer?: string; error?: string; code?: string }; }
      catch { return {}; }
    })();
    if (!response.ok || !payload.answer) {
      const message = payload.error ?? `O servidor não conseguiu responder (erro ${response.status}).`;
      throw new BibleChatError(message, payload.code, response.status);
    }
    return payload.answer;
  } catch (error) {
    if (error instanceof BibleChatError) throw error;
    if (error instanceof Error && error.name === 'AbortError') {
      throw new BibleChatError('A resposta demorou mais que o esperado. Tente novamente.');
    }
    throw new BibleChatError('Não foi possível conectar ao serviço. Confira sua internet e tente novamente.');
  } finally {
    clearTimeout(timeout);
  }
}
