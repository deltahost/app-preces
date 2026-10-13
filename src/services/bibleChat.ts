import AsyncStorage from '@react-native-async-storage/async-storage';

const CONTENT_API_URL = (process.env.EXPO_PUBLIC_CONTENT_API_URL ?? 'https://preces.criandoconteudo.com.br/api').replace(/\/$/, '');
const INSTALLATION_ID_KEY = '@preces/installation-id-v1';

export class BibleChatError extends Error {
  constructor(message: string, public code?: string) {
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

export async function askBible(message: string): Promise<string> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 45000);
  try {
    const response = await fetch(`${CONTENT_API_URL}/bible-chat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message, installationId: await installationId() }),
      signal: controller.signal,
    });
    const payload = await response.json().catch(() => ({})) as { answer?: string; error?: string; code?: string };
    if (!response.ok || !payload.answer) {
      throw new BibleChatError(payload.error ?? 'Não foi possível conversar agora.', payload.code);
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
